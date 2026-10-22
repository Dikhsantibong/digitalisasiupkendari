<?php

namespace Tests\Feature\Operator;

use App\Enums\AttendanceRequestStatus;
use App\Enums\RoleName;
use App\Models\AttendanceCode;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleEntry;
use Database\Seeders\AttendanceCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Pengajuan Absensi: izin / sakit / cuti and tukar shift — submit, the
 * pengganti's confirmation, the approval writing the Jadwal Shift, rejection,
 * cancellation (undoing the schedule) and who may do what.
 */
class PengajuanAbsensiTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private User $userA;

    private Employee $operatorA;

    private User $userB;

    private Employee $operatorB;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();
        $this->seedAccessControl();
        $this->seed(AttendanceCodeSeeder::class);
        $this->travelTo('2026-08-01 08:00:00');

        $this->unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Uji']);
        [$this->userA, $this->operatorA] = $this->operator('Andi', 'A');
        [$this->userB, $this->operatorB] = $this->operator('Budi', 'B');
        $this->approver = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
    }

    public function test_sick_leave_is_approved_onto_the_working_days_only(): void
    {
        Storage::fake('local');
        $this->schedule($this->operatorA, ['2026-08-10' => 'P', '2026-08-11' => 'OFF', '2026-08-12' => 'S']);

        $this->actingAs($this->userA)->post(route('operator.pengajuan.store'), [
            'type' => 'sakit', 'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'reason' => 'Demam tinggi',
            'attachment' => UploadedFile::fake()->create('surat-dokter.pdf', 50, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $request = AttendanceRequest::query()->firstOrFail();
        $this->assertSame(AttendanceRequestStatus::MenungguPersetujuan, $request->status);
        $this->assertSame($this->operatorA->id, $request->employee_id);
        Storage::disk('local')->assertExists($request->attachment_path);
        $this->assertSame(['Pengajuan sakit · Andi'], $this->titles($this->approver));

        $this->actingAs($this->approver)->post(route('operator.pengajuan.approve', $request), ['note' => 'Semoga lekas sembuh'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame(AttendanceRequestStatus::Disetujui, $request->status);
        $this->assertSame($this->approver->id, $request->decided_by);
        // The OFF day stays OFF; the working days become SKT.
        $this->assertSame(['2026-08-10' => 'SKT', '2026-08-11' => 'OFF', '2026-08-12' => 'SKT'], $this->codes($this->operatorA, ['2026-08-10', '2026-08-11', '2026-08-12']));
        $this->assertSame([['employee_id' => $this->operatorA->id, 'date' => '2026-08-10', 'from' => 'P', 'to' => 'SKT'], ['employee_id' => $this->operatorA->id, 'date' => '2026-08-12', 'from' => 'S', 'to' => 'SKT']], $request->applied);
        $this->assertSame(['Pengajuan Sakit Disetujui'], $this->titles($this->userA));

        // The Jadwal Shift shows where the code came from.
        $this->actingAs($this->approver)->get(route('operator.absensi.index', ['unit_id' => $this->unit->id, 'year' => 2026, 'month' => 8]))
            ->assertInertia(fn ($page) => $page->where('sections.0.employees.0.notes.10', fn (string $note): bool => str_contains($note, 'Sakit (pengajuan #'.$request->id.')') && str_contains($note, 'Demam tinggi')));
    }

    public function test_a_tukar_shift_is_confirmed_by_the_pengganti_then_approved(): void
    {
        $this->schedule($this->operatorA, ['2026-08-15' => 'P']);
        $this->schedule($this->operatorB, ['2026-08-15' => 'OFF']);

        $this->actingAs($this->userA)->post(route('operator.pengajuan.store'), [
            'type' => 'tukar_shift', 'start_date' => '2026-08-15', 'substitute_employee_id' => $this->operatorB->id, 'reason' => 'Acara keluarga',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $request = AttendanceRequest::query()->firstOrFail();
        $this->assertSame(AttendanceRequestStatus::MenungguPengganti, $request->status);
        $this->assertSame('2026-08-15', $request->end_date->toDateString());
        $this->assertSame(['Permintaan tukar shift dari Andi'], $this->titles($this->userB));
        $this->assertSame([], $this->titles($this->approver), 'The approver waits for the pengganti.');

        // Not before the pengganti answers.
        $this->actingAs($this->approver)->post(route('operator.pengajuan.approve', $request))->assertStatus(422);
        // Only the pengganti answers.
        $this->actingAs($this->userA)->post(route('operator.pengajuan.respond', $request), ['accept' => true])->assertForbidden();

        $this->actingAs($this->userB)->post(route('operator.pengajuan.respond', $request), ['accept' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AttendanceRequestStatus::MenungguPersetujuan, $request->refresh()->status);
        $this->assertSame(['Tukar shift disetujui pengganti · Andi'], $this->titles($this->approver));
        $this->assertSame(['Budi bersedia menggantikan shift Anda'], $this->titles($this->userA));

        $this->actingAs($this->approver)->post(route('operator.pengajuan.approve', $request))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['2026-08-15' => 'OFF'], $this->codes($this->operatorA, ['2026-08-15']));
        $this->assertSame(['2026-08-15' => 'P'], $this->codes($this->operatorB, ['2026-08-15']));
        $this->assertStringContainsString('Menggantikan Andi', (string) $this->entry($this->operatorB, '2026-08-15')->note);
        $this->assertStringContainsString('Digantikan Budi', (string) $this->entry($this->operatorA, '2026-08-15')->note);
        $this->assertContains('Tukar shift Andi Disetujui', $this->titles($this->userB));

        // "Isi Otomatis" keeps the swapped cells.
        $this->actingAs($this->approver)->post(route('operator.absensi.generate'), ['unit_id' => $this->unit->id, 'year' => 2026, 'month' => 8])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['2026-08-15' => 'OFF'], $this->codes($this->operatorA, ['2026-08-15']));
        $this->assertSame(['2026-08-15' => 'P'], $this->codes($this->operatorB, ['2026-08-15']));

        // Cancelling the approval puts the schedule back.
        $this->actingAs($this->approver)->post(route('operator.pengajuan.cancel', $request))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AttendanceRequestStatus::Dibatalkan, $request->refresh()->status);
        $this->assertSame(['2026-08-15' => 'P'], $this->codes($this->operatorA, ['2026-08-15']));
        $this->assertSame(['2026-08-15' => 'OFF'], $this->codes($this->operatorB, ['2026-08-15']));
        $this->assertNull($this->entry($this->operatorA, '2026-08-15')->attendance_request_id);
    }

    public function test_a_reciprocal_tukar_shift_also_moves_the_return_day(): void
    {
        $this->schedule($this->operatorA, ['2026-08-15' => 'S', '2026-08-20' => 'OFF']);
        $this->schedule($this->operatorB, ['2026-08-15' => 'OFF', '2026-08-20' => 'M']);
        $request = $this->tukar(['swap_date' => '2026-08-20']);

        $this->actingAs($this->userB)->post(route('operator.pengajuan.respond', $request), ['accept' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->approver)->post(route('operator.pengajuan.approve', $request))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['2026-08-15' => 'OFF', '2026-08-20' => 'M'], $this->codes($this->operatorA, ['2026-08-15', '2026-08-20']));
        $this->assertSame(['2026-08-15' => 'S', '2026-08-20' => 'OFF'], $this->codes($this->operatorB, ['2026-08-15', '2026-08-20']));
        $this->assertCount(4, $request->refresh()->applied);
    }

    public function test_the_rules_of_a_new_request(): void
    {
        $this->schedule($this->operatorA, ['2026-08-15' => 'OFF', '2026-08-16' => 'P', '2026-08-21' => 'OFF']);
        $this->schedule($this->operatorB, ['2026-08-16' => 'P', '2026-08-21' => 'OFF']);
        [, $otherUnitEmployee] = $this->operator('Citra', 'C', Unit::factory()->create(['is_active' => true]));
        $post = fn (array $data) => $this->actingAs($this->userA)->post(route('operator.pengajuan.store'), ['reason' => 'Keperluan', ...$data]);

        // Nothing to hand over on a day off; a busy pengganti; a pengganti of another unit.
        $post(['type' => 'tukar_shift', 'start_date' => '2026-08-15', 'substitute_employee_id' => $this->operatorB->id])->assertSessionHasErrors('start_date');
        $post(['type' => 'tukar_shift', 'start_date' => '2026-08-16', 'substitute_employee_id' => $this->operatorB->id])->assertSessionHasErrors('substitute_employee_id');
        $post(['type' => 'tukar_shift', 'start_date' => '2026-08-16', 'substitute_employee_id' => $otherUnitEmployee->id])->assertSessionHasErrors('substitute_employee_id');
        $post(['type' => 'tukar_shift', 'start_date' => '2026-08-16', 'substitute_employee_id' => $this->operatorA->id])->assertSessionHasErrors('substitute_employee_id');
        $post(['type' => 'tukar_shift', 'start_date' => '2026-08-16'])->assertSessionHasErrors('substitute_employee_id');

        // The return day must be one the pengganti works and the employee does not.
        $this->schedule($this->operatorB, ['2026-08-16' => 'OFF']);
        $post(['type' => 'tukar_shift', 'start_date' => '2026-08-16', 'substitute_employee_id' => $this->operatorB->id, 'swap_date' => '2026-08-21'])->assertSessionHasErrors('swap_date');

        // A range too long, an end before the start, a short reason, a non-document attachment.
        $post(['type' => 'cuti', 'start_date' => '2026-08-01', 'end_date' => '2026-09-05'])->assertSessionHasErrors('end_date');
        $post(['type' => 'cuti', 'start_date' => '2026-08-10', 'end_date' => '2026-08-09'])->assertSessionHasErrors('end_date');
        $post(['type' => 'izin', 'start_date' => '2026-08-10', 'reason' => 'x'])->assertSessionHasErrors('reason');
        $post(['type' => 'sakit', 'start_date' => '2026-08-10', 'attachment' => UploadedFile::fake()->create('a.exe', 5, 'application/octet-stream')])->assertSessionHasErrors('attachment');
        $this->assertSame(0, AttendanceRequest::query()->count());

        // A second request over the same days.
        $post(['type' => 'izin', 'start_date' => '2026-08-10', 'end_date' => '2026-08-11'])->assertRedirect()->assertSessionHasNoErrors();
        $post(['type' => 'cuti', 'start_date' => '2026-08-11', 'end_date' => '2026-08-13'])->assertSessionHasErrors('start_date');
        $this->assertSame(1, AttendanceRequest::query()->count());
    }

    public function test_the_approval_checks_the_schedule_again(): void
    {
        $this->schedule($this->operatorA, ['2026-08-15' => 'P']);
        $this->schedule($this->operatorB, ['2026-08-15' => 'OFF']);
        $request = $this->tukar();
        $this->actingAs($this->userB)->post(route('operator.pengajuan.respond', $request), ['accept' => true])->assertRedirect()->assertSessionHasNoErrors();

        // Meanwhile the pengganti was put on a shift that day.
        $this->schedule($this->operatorB, ['2026-08-15' => 'S']);

        $this->actingAs($this->approver)->post(route('operator.pengajuan.approve', $request))->assertSessionHasErrors('substitute_employee_id');

        $this->assertSame(AttendanceRequestStatus::MenungguPersetujuan, $request->refresh()->status);
        $this->assertSame(['2026-08-15' => 'P'], $this->codes($this->operatorA, ['2026-08-15']));
    }

    public function test_decline_reject_and_cancel(): void
    {
        $this->schedule($this->operatorA, ['2026-08-15' => 'P']);
        $this->schedule($this->operatorB, ['2026-08-15' => 'OFF']);

        // The pengganti declines: the request is closed and the employee told.
        $request = $this->tukar();
        $this->actingAs($this->userB)->post(route('operator.pengajuan.respond', $request), ['accept' => false, 'note' => 'Ada acara'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AttendanceRequestStatus::Ditolak, $request->refresh()->status);
        $this->assertSame(['Budi tidak bersedia menggantikan shift Anda'], $this->titles($this->userA));

        // The approver rejects with a reason (required).
        $izin = $this->izin('2026-08-20');
        $this->actingAs($this->approver)->post(route('operator.pengajuan.reject', $izin), ['note' => ''])->assertSessionHasErrors('note');
        $this->actingAs($this->approver)->post(route('operator.pengajuan.reject', $izin), ['note' => 'Personel kurang'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AttendanceRequestStatus::Ditolak, $izin->refresh()->status);
        $this->assertSame('Personel kurang', $izin->decision_note);

        // The employee withdraws a pending one; a colleague may not.
        $cuti = $this->izin('2026-08-25', 'cuti');
        $this->actingAs($this->userB)->post(route('operator.pengajuan.cancel', $cuti))->assertForbidden();
        $this->actingAs($this->userA)->post(route('operator.pengajuan.cancel', $cuti))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AttendanceRequestStatus::Dibatalkan, $cuti->refresh()->status);
        // A closed request cannot be decided again.
        $this->actingAs($this->approver)->post(route('operator.pengajuan.approve', $cuti))->assertStatus(422);
    }

    public function test_only_the_approvers_decide_and_never_their_own_request(): void
    {
        $izin = $this->izin('2026-08-20');
        $this->actingAs($this->userB)->post(route('operator.pengajuan.approve', $izin))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit))->post(route('operator.pengajuan.approve', $izin))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, Unit::factory()->create()))->post(route('operator.pengajuan.approve', $izin))->assertForbidden();

        // The Project Leader approves the others, but their own request goes to another approver.
        [$leaderUser, $leader] = $this->employeeWithRole('Lukman', RoleName::ProjectLeaderOperasi, ['regu' => null, 'position' => 'Project Leader']);
        $this->actingAs($leaderUser)->post(route('operator.pengajuan.approve', $izin))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AttendanceRequestStatus::Disetujui, $izin->refresh()->status);

        $this->actingAs($leaderUser)->post(route('operator.pengajuan.store'), ['type' => 'izin', 'start_date' => '2026-08-21', 'reason' => 'Urusan bank'])->assertRedirect()->assertSessionHasNoErrors();
        $own = AttendanceRequest::query()->where('employee_id', $leader->id)->firstOrFail();
        $this->actingAs($leaderUser)->post(route('operator.pengajuan.approve', $own))->assertForbidden();
        $this->assertSame([], $this->titles($leaderUser), 'The one asking is never sent their own request to approve.');
        $this->actingAs($this->approver)->post(route('operator.pengajuan.approve', $own))->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_who_sees_which_requests_and_the_attachment(): void
    {
        Storage::fake('local');
        $this->schedule($this->operatorA, ['2026-08-15' => 'P']);
        $this->schedule($this->operatorB, ['2026-08-15' => 'OFF']);
        $this->actingAs($this->userA)->post(route('operator.pengajuan.store'), [
            'type' => 'sakit', 'start_date' => '2026-08-03', 'reason' => 'Flu', 'attachment' => UploadedFile::fake()->image('surat.jpg'),
        ]);
        $sakit = AttendanceRequest::query()->firstOrFail();
        $tukar = $this->tukar();
        [$userC] = $this->operator('Citra', 'C');
        $page = ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($this->userA)->get(route('operator.pengajuan.index', $page))->assertOk()
            ->assertInertia(fn ($p) => $p->component('operator/pengajuan/index')->has('requests', 2)->where('can_submit', true)->where('can_approve', false)->where('sees_all', false)
                ->where('me.codes.2026-08-15', 'P')
                ->where('colleagues', fn ($colleagues): bool => collect($colleagues)->pluck('name')->sort()->values()->all() === ['Budi', 'Citra']));
        // The pengganti sees the tukar shift (and may answer it), not the sick leave.
        $this->actingAs($this->userB)->get(route('operator.pengajuan.index', $page))
            ->assertInertia(fn ($p) => $p->has('requests', 1)->where('requests.0.id', $tukar->id)->where('requests.0.can.respond', true));
        $this->actingAs($userC)->get(route('operator.pengajuan.index', $page))->assertInertia(fn ($p) => $p->has('requests', 0));
        $this->actingAs($this->approver)->get(route('operator.pengajuan.index', $page))
            ->assertInertia(fn ($p) => $p->has('requests', 2)->where('sees_all', true)->where('can_approve', true)->where('can_submit', false));
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit))->get(route('operator.pengajuan.index', $page))
            ->assertInertia(fn ($p) => $p->has('requests', 2)->where('can_approve', false));

        $this->actingAs($this->userA)->get(route('operator.pengajuan.attachment', $sakit))->assertOk();
        $this->actingAs($this->approver)->get(route('operator.pengajuan.attachment', $sakit))->assertOk();
        $this->actingAs($userC)->get(route('operator.pengajuan.attachment', $sakit))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $this->unit))->get(route('operator.pengajuan.index', $page))->assertForbidden();
    }

    public function test_a_pengganti_without_an_account_goes_straight_to_the_approver(): void
    {
        $this->schedule($this->operatorA, ['2026-08-15' => 'P']);
        $noAccount = Employee::factory()->create(['unit_id' => $this->unit->id, 'is_active' => true, 'regu' => 'D', 'position' => 'Operator', 'name' => 'Dedi']);

        $request = $this->tukar(['substitute_employee_id' => $noAccount->id]);

        $this->assertSame(AttendanceRequestStatus::MenungguPersetujuan, $request->status);
        $this->assertSame(['Pengajuan tukar shift · Andi'], $this->titles($this->approver));
    }

    public function test_editing_a_cell_by_hand_releases_it_from_the_request(): void
    {
        $this->schedule($this->operatorA, ['2026-08-20' => 'P']);
        $izin = $this->izin('2026-08-20');
        $this->actingAs($this->approver)->post(route('operator.pengajuan.approve', $izin))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($izin->id, $this->entry($this->operatorA, '2026-08-20')->attendance_request_id);

        $this->actingAs($this->approver)->post(route('operator.absensi.store'), [
            'unit_id' => $this->unit->id, 'year' => 2026, 'month' => 8,
            'cells' => [['employee_id' => $this->operatorA->id, 'day' => 20, 'code' => 'P']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entry = $this->entry($this->operatorA, '2026-08-20');
        $this->assertNull($entry->attendance_request_id);
        $this->assertNull($entry->note);
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    private function operator(string $name, string $regu, ?Unit $unit = null): array
    {
        return $this->employeeWithRole($name, RoleName::Operator, ['regu' => $regu, 'position' => 'Operator'], $unit);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: User, 1: Employee}
     */
    private function employeeWithRole(string $name, RoleName $role, array $attributes, ?Unit $unit = null): array
    {
        $unit ??= $this->unit;
        $user = $this->userWithRole($role, $unit);
        $employee = Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'name' => $name, 'user_id' => $user->id, ...$attributes]);

        return [$user, $employee];
    }

    /**
     * @param  array<string, string>  $codes  Y-m-d => code
     */
    private function schedule(Employee $employee, array $codes): void
    {
        foreach ($codes as $date => $code) {
            $schedule = WorkSchedule::query()->firstOrCreate([
                'unit_id' => $employee->unit_id, 'year' => (int) substr($date, 0, 4), 'month' => (int) substr($date, 5, 2),
                'group_type' => $employee->regu !== null ? 'shift' : 'non_shift',
            ]);
            $entry = $this->entry($employee, $date);
            $values = ['attendance_code_id' => AttendanceCode::query()->where('code', $code)->value('id'), 'regu' => $employee->regu];

            $entry !== null ? $entry->update($values) : $schedule->entries()->create(['employee_id' => $employee->id, 'work_date' => $date, ...$values]);
        }
    }

    private function entry(Employee $employee, string $date): ?WorkScheduleEntry
    {
        return WorkScheduleEntry::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->first();
    }

    /**
     * @param  list<string>  $dates
     * @return array<string, string|null>
     */
    private function codes(Employee $employee, array $dates): array
    {
        return collect($dates)->mapWithKeys(fn (string $date): array => [
            $date => ($id = $this->entry($employee, $date)?->attendance_code_id) === null ? null : AttendanceCode::query()->find($id)?->code,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tukar(array $data = []): AttendanceRequest
    {
        $this->actingAs($this->userA)->post(route('operator.pengajuan.store'), [
            'type' => 'tukar_shift', 'start_date' => '2026-08-15', 'substitute_employee_id' => $this->operatorB->id, 'reason' => 'Acara keluarga', ...$data,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return AttendanceRequest::query()->latest('id')->firstOrFail();
    }

    private function izin(string $date, string $type = 'izin'): AttendanceRequest
    {
        $this->actingAs($this->userA)->post(route('operator.pengajuan.store'), ['type' => $type, 'start_date' => $date, 'end_date' => $date, 'reason' => 'Urusan keluarga'])
            ->assertRedirect()->assertSessionHasNoErrors();

        return AttendanceRequest::query()->latest('id')->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function titles(User $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }
}
