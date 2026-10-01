<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Models\AccidentReport;
use App\Models\HarUnsafeCondition;
use App\Models\KondisiAbnormal;
use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowStep;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ReminderNotification;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function Illuminate\Support\defer;

/**
 * Notifications for things that just happened, sent after the response so a
 * save is never slowed down:
 * - kejadian: kondisi abnormal / gangguan, unsafe action & condition, kecelakaan;
 * - pekerjaan: Work Order & Service Request baru or with a new status;
 * - laporan: each step of the Laporan Pembangkit workflow.
 *
 * Recipients are resolved from permissions and unit scope (never role names),
 * the person who did it is never notified, and each link is only given to a
 * recipient who may open that page.
 */
class EventNotifier
{
    public function __construct(private ReminderSender $sender) {}

    /**
     * Kondisi abnormal / gangguan rows that just became abnormal or gangguan.
     *
     * @param  iterable<KondisiAbnormal>  $rows
     */
    public function kondisiAbnormal(Unit $unit, iterable $rows, User $actor): void
    {
        $url = route('operasi.input.kondisi-abnormal.index', ['unit_id' => $unit->id], false);
        $canOpen = [PermissionName::OperasiInputView, PermissionName::OperasiLapanganKondisiAbnormal, PermissionName::OperasiLaporanView];

        foreach ($rows as $row) {
            foreach (['abnormal' => $row->is_abnormal, 'gangguan' => $row->is_gangguan] as $kind => $flag) {
                if (! $flag) {
                    continue;
                }

                $audience = [PermissionName::OperasiInputView, PermissionName::OperasiPengusahaanView, PermissionName::OperasiLaporanView];
                if ($kind === 'gangguan') {
                    // A gangguan mesin concerns Pemeliharaan too.
                    $audience = [...$audience, PermissionName::HarInputView, PermissionName::HarPengusahaanView];
                }

                $durasi = (float) ($kind === 'abnormal' ? $row->durasi_abnormal : $row->durasi_gangguan);
                $this->toAudience($unit->id, $audience, $actor, NotificationCategory::Kejadian, 'operasi',
                    ($kind === 'gangguan' ? 'Gangguan mesin' : 'Kondisi abnormal').' · '.$unit->name,
                    Str::limit(trim(($row->uraian_kondisi ?: 'Tanpa uraian').($row->tanggal ? ' — '.$row->tanggal->locale('id')->isoFormat('D MMM YYYY') : '').($durasi > 0 ? " ({$durasi} jam)" : '')), 220),
                    $url, $canOpen, "kejadian:{$kind}:{$row->id}");
            }
        }
    }

    /**
     * A new unsafe action / condition finding (module operasi or har).
     */
    public function unsafeFound(HarUnsafeCondition $finding, string $module, User $actor): void
    {
        [$view, $field, $laporan, $pengusahaan] = $module === 'operasi'
            ? [PermissionName::OperasiInputView, PermissionName::OperasiLapanganUnsafeCondition, PermissionName::OperasiLaporanView, PermissionName::OperasiPengusahaanView]
            : [PermissionName::HarInputView, PermissionName::HarLapanganUnsafeCondition, PermissionName::HarLaporanView, PermissionName::HarPengusahaanView];

        $this->toAudience($finding->unit_id, [$view, $pengusahaan, $laporan, PermissionName::K3InputView, PermissionName::K3PengusahaanView], $actor,
            NotificationCategory::Kejadian, $module,
            Str::title(strtolower($finding->kategori)).' baru · '.($finding->unit?->name ?? 'Unit'),
            Str::limit($finding->temuan.($finding->lokasi ? " — {$finding->lokasi}" : ''), 220),
            route("{$module}.input.unsafe-condition.index", ['unit_id' => $finding->unit_id, 'month' => $finding->month, 'year' => $finding->year], false),
            [$view, $field, $laporan], "kejadian:unsafe:{$finding->id}");
    }

    /**
     * An open finding was closed: tell the person who reported it.
     */
    public function unsafeClosed(HarUnsafeCondition $finding, string $module, User $actor): void
    {
        $reporter = $finding->input_by === null ? null : User::query()->find($finding->input_by);

        if ($reporter === null || $reporter->is($actor)) {
            return;
        }

        $this->later([$reporter], fn (User $user): ReminderNotification => new ReminderNotification(
            NotificationCategory::Kejadian, $module,
            'Temuan Anda sudah ditutup',
            Str::limit($finding->temuan, 200)." — ditutup oleh {$actor->name}.",
            route("{$module}.input.unsafe-condition.index", ['unit_id' => $finding->unit_id, 'month' => $finding->month, 'year' => $finding->year], false),
            "kejadian:unsafe-close:{$finding->id}",
        ));
    }

    /**
     * Identity of a kecelakaan row; the rows are re-created on every save, so
     * this tells a newly reported accident from one saved before.
     */
    public static function accidentKey(AccidentReport $row): string
    {
        return md5(implode('|', [$row->unit_id, $row->category->value, $row->incident_date?->toDateString(), $row->lokasi, $row->fungsi]));
    }

    /**
     * Newly reported kecelakaan kerja rows (not "nihil") with victims or
     * material loss.
     *
     * @param  iterable<AccidentReport>  $rows
     * @param  list<string>  $previousKeys  {@see accidentKey} of the rows before this save
     */
    public function accidents(Unit $unit, iterable $rows, array $previousKeys, User $actor): void
    {
        foreach ($rows as $row) {
            $victims = $row->luka_ringan + $row->luka_berat + $row->meninggal;

            if (in_array(self::accidentKey($row), $previousKeys, true) || $row->is_nihil || ($victims === 0 && (float) $row->kerugian_material <= 0)) {
                continue;
            }

            $detail = collect([
                $row->meninggal > 0 ? "{$row->meninggal} meninggal" : null,
                $row->luka_berat > 0 ? "{$row->luka_berat} luka berat" : null,
                $row->luka_ringan > 0 ? "{$row->luka_ringan} luka ringan" : null,
            ])->filter()->implode(', ');

            $this->toAudience($unit->id, [PermissionName::K3InputView, PermissionName::K3PengusahaanView, PermissionName::K3LaporanView], $actor,
                NotificationCategory::Kejadian, 'k3',
                'Kecelakaan '.$row->category->label().' · '.$unit->name,
                Str::limit(trim(($row->incident_date?->locale('id')->isoFormat('D MMM YYYY') ?? '').' '.($row->lokasi ? "di {$row->lokasi}" : '').($detail ? " — {$detail}" : '')), 220),
                route('k3.input.accident.index', ['unit_id' => $unit->id, 'month' => $row->month, 'year' => $row->year], false),
                [PermissionName::K3InputView],
                'kejadian:kecelakaan:'.self::accidentKey($row));
        }
    }

    /**
     * Work orders / service requests saved in one go: one summary per save.
     *
     * @param  list<string>  $created  numbers of new WO / SR
     * @param  list<string>  $changed  "number → status" of WO / SR whose status changed
     */
    public function workItems(string $kind, Unit $unit, int $month, int $year, array $created, array $changed, User $actor): void
    {
        if ($created === [] && $changed === []) {
            return;
        }

        $label = $kind === 'wo' ? 'Work Order' : 'Service Request';
        $parts = array_filter([
            $created === [] ? null : count($created)." {$label} baru: ".Str::limit(implode(', ', $created), 90),
            $changed === [] ? null : count($changed).' berubah status: '.Str::limit(implode(', ', $changed), 90),
        ]);

        $this->toAudience($unit->id, [PermissionName::HarInputView, PermissionName::HarPengusahaanView], $actor,
            NotificationCategory::Pekerjaan, 'har',
            "{$label} diperbarui · {$unit->name}",
            implode(' · ', $parts).". Oleh {$actor->name}.",
            route($kind === 'wo' ? 'har.input.work-order.index' : 'har.input.service-request.index', ['unit_id' => $unit->id, 'month' => $month, 'year' => $year], false),
            [PermissionName::HarInputView, PermissionName::HarPengusahaanView, $kind === 'wo' ? PermissionName::HarLapanganWorkOrder : PermissionName::HarLapanganServiceRequest],
            "pekerjaan:{$kind}:{$unit->id}:".Str::uuid());
    }

    /**
     * A step of the Laporan Pembangkit workflow was taken: the signer whose
     * turn it is now, and the submitter, hear about it.
     */
    public function reportWorkflow(ReportWorkflow $workflow, string $action, User $actor, ?string $reason = null): void
    {
        $workflow->loadMissing(['steps.employee.user', 'submitter', 'unit']);
        $module = $workflow->module;
        $what = $module->label().' '.($workflow->unit?->name ?? '').' · '.Indonesian::monthName($workflow->month).' '.$workflow->year;
        $url = $module === ReportModule::Operasi
            ? route('operasi.laporan.index', ['unit_id' => $workflow->unit_id, 'month' => $workflow->month, 'year' => $workflow->year], false)
            : route("{$module->value}.laporan.document.edit", ['unit_id' => $workflow->unit_id, 'month' => $workflow->month, 'year' => $workflow->year], false);
        $stamp = Carbon::now()->format('YmdHisv');
        $signer = fn (int $sequence): ?User => $workflow->steps->first(fn (ReportWorkflowStep $s): bool => $s->isPengesahan() && $s->sequence === $sequence)?->employee?->user;

        [$next, $nextTitle] = match ($action) {
            'ajukan' => [$signer(1), 'Laporan menunggu verifikasi Anda'],
            'verifikasi' => [$signer(2), 'Laporan menunggu persetujuan Anda'],
            'setujui' => [$signer(3), 'Laporan menunggu pengesahan Anda'],
            default => [null, null],
        };

        if ($next !== null && ! $next->is($actor)) {
            $this->later([$next], fn (User $user): ReminderNotification => new ReminderNotification(
                NotificationCategory::Laporan, $module->value, $nextTitle, "{$what} — dari {$actor->name}.", $url, "laporan:{$workflow->id}:{$action}:next:{$stamp}",
            ));
        }

        $submitterTitle = match ($action) {
            'verifikasi' => 'Laporan Anda sudah diverifikasi',
            'setujui' => 'Laporan Anda sudah disetujui',
            'sahkan' => 'Laporan Anda sudah disahkan (FINAL)',
            'tolak' => 'Laporan Anda ditolak',
            default => null,
        };

        $submitter = $workflow->submitter;
        if ($submitterTitle !== null && $submitter !== null && ! $submitter->is($actor)) {
            $body = "{$what} — oleh {$actor->name}".($reason ? ": {$reason}" : '.');
            $this->later([$submitter], fn (User $user): ReminderNotification => new ReminderNotification(
                NotificationCategory::Laporan, $module->value, $submitterTitle, Str::limit($body, 240), $url, "laporan:{$workflow->id}:{$action}:submitter:{$stamp}",
            ));
        }
    }

    /**
     * Users at the unit holding any of the permissions (except the actor and
     * the Super Admin), each with the link only when they may open it.
     *
     * @param  list<PermissionName>  $audience
     * @param  list<PermissionName>  $canOpen
     */
    private function toAudience(int $unitId, array $audience, User $actor, NotificationCategory $category, string $module, string $title, string $body, string $url, array $canOpen, string $key): void
    {
        $recipients = $this->audience($unitId, $audience, $actor);

        $this->later($recipients, fn (User $user): ReminderNotification => new ReminderNotification(
            $category, $module, $title, $body,
            collect($canOpen)->contains(fn (PermissionName $p): bool => $user->hasPermissionTo($p)) ? $url : null,
            $key,
        ));
    }

    /**
     * @param  list<PermissionName>  $permissions
     * @return Collection<int, User>
     */
    public function audience(int $unitId, array $permissions, ?User $except = null): Collection
    {
        return User::query()->where('is_active', true)->whereKeyNot($except?->id ?? 0)->get()
            ->filter(fn (User $user): bool => ! $user->isSuperAdmin()
                && $user->canAccessUnit($unitId)
                && collect($permissions)->contains(fn (PermissionName $p): bool => $user->hasPermissionTo($p)))
            ->values();
    }

    /**
     * Send after the response (or at the end of the command / job).
     *
     * @param  iterable<User>  $users
     * @param  callable(User): ReminderNotification  $make
     */
    private function later(iterable $users, callable $make): void
    {
        $users = collect($users);

        if ($users->isEmpty()) {
            return;
        }

        defer(function () use ($users, $make): void {
            foreach ($users as $user) {
                $this->sender->send($user, $make($user));
            }
        });
    }
}
