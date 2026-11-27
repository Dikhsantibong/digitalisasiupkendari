<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Http\Controllers\Operasi\Program5s5rController;
use App\Models\Employee;
use App\Models\Operasi5s5rJadwal;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class Program5s5rJadwalTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    public function test_default_pelaksana_rows_are_pulled_from_unit_shift_operators_ordered_by_shift(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $otherUnit = Unit::factory()->create(['is_active' => true]);

        $opB = Employee::factory()->forUnit($unit)->create([
            'name' => 'Budi Shift B', 'position' => 'Operator', 'regu' => 'B', 'is_shift_leader' => true, 'is_active' => true,
        ]);
        $opAMember = Employee::factory()->forUnit($unit)->create([
            'name' => 'Agus Shift A', 'position' => 'Operator', 'regu' => 'A', 'is_shift_leader' => false, 'is_active' => true,
        ]);
        $opALeader = Employee::factory()->forUnit($unit)->create([
            'name' => 'Zain Shift A', 'position' => 'Operator', 'regu' => 'A', 'is_shift_leader' => true, 'is_active' => true,
        ]);
        $nonShift = Employee::factory()->forUnit($unit)->create([
            'name' => 'La Aka Fuel', 'position' => 'Teknisi Administrasi Fuel Facility', 'regu' => null, 'is_active' => true,
        ]);
        Employee::factory()->forUnit($unit)->create([
            'name' => 'Operator Nonaktif', 'position' => 'Operator', 'regu' => 'C', 'is_active' => false,
        ]);
        Employee::factory()->forUnit($otherUnit)->create([
            'name' => 'Operator Unit Lain', 'position' => 'Operator', 'regu' => 'A', 'is_active' => true,
        ]);

        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $this->actingAs($user)
            ->get(route('operasi.jadwal.program-5s-5r.index', ['unit_id' => $unit->id, 'month' => 11, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operasi/jadwal/program-5s-5r/index')
                ->has('rows', 3)
                ->where('rows.0.employee_id', $opALeader->id)
                ->where('rows.0.shift', 'A')
                ->where('rows.0.pelaksana', 'Zain Shift A')
                ->where('rows.1.employee_id', $opAMember->id)
                ->where('rows.1.shift', 'A')
                ->where('rows.1.pelaksana', 'Agus Shift A')
                ->where('rows.2.employee_id', $opB->id)
                ->where('rows.2.shift', 'B')
                ->where('rows.2.pelaksana', 'Budi Shift B')
                ->has('options.operators', 3)
                ->has('options.employees', 1)
                ->where('options.employees.0.id', $nonShift->id)
            );
    }

    public function test_user_can_save_both_shift_operator_rows_and_custom_pelaksana_rows(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $opA = Employee::factory()->forUnit($unit)->create([
            'name' => 'Muh. Zainuddin', 'position' => 'Operator', 'regu' => 'A', 'is_shift_leader' => true, 'is_active' => true,
        ]);
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.jadwal.program-5s-5r.store'), [
                'unit_id' => $unit->id,
                'month' => 11,
                'year' => 2026,
                'rows' => [
                    [
                        'employee_id' => $opA->id,
                        'shift' => 'A',
                        'pelaksana' => 'Muh. Zainuddin',
                        'rencana' => [3, 10, 17, 24],
                        'realisasi' => [3, 10],
                        'target' => 4,
                    ],
                    [
                        'employee_id' => null,
                        'shift' => null,
                        'pelaksana' => 'Tim Kebersihan Custom',
                        'rencana' => [5, 12],
                        'realisasi' => [5],
                        'target' => 2,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $saved = Operasi5s5rJadwal::query()
            ->where('unit_id', $unit->id)
            ->where('year', 2026)
            ->where('month', 11)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $saved);
        $this->assertSame($opA->id, $saved[0]->employee_id);
        $this->assertSame('A', $saved[0]->shift);
        $this->assertSame('Muh. Zainuddin', $saved[0]->pelaksana);
        $this->assertSame('Muh. Zainuddin (Shift A)', $saved[0]->displayPelaksana());

        $this->assertNull($saved[1]->employee_id);
        $this->assertNull($saved[1]->shift);
        $this->assertSame('Tim Kebersihan Custom', $saved[1]->pelaksana);
        $this->assertSame('Tim Kebersihan Custom', $saved[1]->displayPelaksana());

        // PDF export renders cleanly with both shift operator and custom pelaksana, without a separate Shift column.
        $this->actingAs($user)
            ->get(route('operasi.jadwal.program-5s-5r.pdf', ['unit_id' => $unit->id, 'month' => 11, 'year' => 2026]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        [$view, $data] = app(Program5s5rController::class)->pdfView($unit, 11, 2026);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('Muh. Zainuddin', $html);
        $this->assertStringContainsString('Tim Kebersihan Custom', $html);
        $this->assertStringNotContainsString('>Shift<', $html);
    }

    public function test_employee_id_from_another_unit_is_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $otherUnit = Unit::factory()->create(['is_active' => true]);
        $foreignEmp = Employee::factory()->forUnit($otherUnit)->create(['regu' => 'A', 'is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.jadwal.program-5s-5r.store'), [
                'unit_id' => $unit->id,
                'month' => 11,
                'year' => 2026,
                'rows' => [
                    [
                        'employee_id' => $foreignEmp->id,
                        'shift' => 'A',
                        'pelaksana' => $foreignEmp->name,
                        'rencana' => [1],
                        'realisasi' => [],
                        'target' => 1,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows.0.employee_id');
    }
}
