<?php

namespace App\Services\Dashboard;

use App\Enums\CertificateStatus;
use App\Models\AccidentReport;
use App\Models\EquipmentCertificate;
use App\Models\FireExtinguisher;
use App\Models\FireExtinguisherCheck;
use App\Models\HarUnsafeCondition;
use App\Models\Inspection;
use App\Models\K3ActivityPlan;
use App\Models\SecurityPatrol;
use App\Models\Unit;
use App\Services\K3\K3MonitoringService;
use App\Support\Indonesian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The K3 section of the dashboard, read from the K3 inputs of the visible
 * units: Laporan Kecelakaan (hari tanpa kecelakaan), Inspeksi, Unsafe Action &
 * Condition (temuan without tindak lanjut), sertifikat alat & APAR (status by
 * {@see K3MonitoringService::statusFor()}), Patroli Security and the Time
 * Frame kegiatan K3 (rencana vs realisasi hari).
 */
class K3Dashboard
{
    /** Day ranges of the weeks of the compliance heatmap. */
    private const WEEKS = [[1, 7], [8, 14], [15, 21], [22, 28], [29, 31]];

    public function __construct(private readonly K3MonitoringService $monitoring) {}

    /**
     * @param  Collection<int, Unit>  $units
     * @return array<string, mixed>
     */
    public function build(Collection $units, Carbon $now): array
    {
        $unitIds = $units->pluck('id')->all();
        $months = DashboardSupport::months($now);
        $last = count($months) - 1;
        $countPerMonth = fn (string $model): array => array_map(
            fn (array $m): int => $model::query()->whereIn('unit_id', $unitIds)->where('year', $m['year'])->where('month', $m['month'])->count(),
            $months,
        );

        $inspections = $countPerMonth(Inspection::class);
        $unsafeOpen = array_map(fn (array $m): int => HarUnsafeCondition::query()
            ->whereIn('unit_id', $unitIds)->where('year', $m['year'])->where('month', $m['month'])
            ->where(fn ($q) => $q->whereNull('tindak_lanjut')->orWhere('tindak_lanjut', ''))
            ->count(), $months);

        $lastAccident = AccidentReport::query()
            ->whereIn('unit_id', $unitIds)
            ->where('is_nihil', false)
            ->whereNotNull('incident_date')
            ->whereRaw('(coalesce(luka_ringan, 0) + coalesce(luka_berat, 0) + coalesce(meninggal, 0)) > 0')
            ->max('incident_date');
        $daysWithoutAccident = $lastAccident === null ? null : (int) Carbon::parse($lastAccident)->startOfDay()->diffInDays($now->copy()->startOfDay());

        $certificates = $this->certificateStatuses($unitIds);
        $extinguishers = $this->extinguisherStatuses($unitIds, $now);
        $aparTotal = array_sum($extinguishers);
        // No APAR checked yet (every tube "Belum") is no data, not 0% ready.
        $aparReady = $aparTotal === 0 || $extinguishers[CertificateStatus::Belum->value] === $aparTotal ? null : round(($extinguishers[CertificateStatus::Aktif->value] + $extinguishers[CertificateStatus::Mendekati->value]) / $aparTotal * 100, 1);

        $plans = K3ActivityPlan::query()->whereIn('unit_id', $unitIds)->where('year', $now->year)->where('month', $now->month)->get(['unit_id', 'plan_days', 'real_days']);

        return [
            'kpis' => [
                DashboardSupport::kpi('zero', 'Hari Tanpa Kecelakaan', $daysWithoutAccident, 'hari', 'number', 'up'),
                DashboardSupport::kpi('inspeksi', 'Inspeksi K3', $inspections[$last], null, 'number', 'up', $inspections[$last - 1], $inspections),
                DashboardSupport::kpi('unsafe', 'Unsafe Terbuka', $unsafeOpen[$last], null, 'number', 'down', $unsafeOpen[$last - 1], $unsafeOpen),
                DashboardSupport::kpi('cert', 'Sertifikat Expired', $certificates[CertificateStatus::Expired->value], null, 'number', 'down'),
                DashboardSupport::kpi('apar', 'APAR Siap Pakai', $aparReady, '%', 'decimal', 'up'),
            ],
            's_curve' => ['month_label' => Indonesian::monthName($now->month).' '.$now->year, 'points' => $this->sCurve($plans, $now)],
            'cert_status' => $this->statusData($certificates),
            'apar_status' => $this->statusData($extinguishers),
            'patrol_top' => SecurityPatrol::query()
                ->whereIn('unit_id', $unitIds)
                ->whereBetween('patrol_date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
                ->with('location:id,name')
                ->get(['patrol_location_id', 'total_scan'])
                ->groupBy(fn (SecurityPatrol $patrol): string => $patrol->location?->name ?? 'Tanpa lokasi')
                ->map(fn ($group, string $label): array => ['label' => $label, 'value' => (int) $group->sum('total_scan')])
                ->sortByDesc('value')->take(6)->values()->all(),
            'compliance' => $this->compliance($units, $plans, $now),
        ];
    }

    /**
     * @param  list<int>  $unitIds
     * @return array<string, int> status => count
     */
    private function certificateStatuses(array $unitIds): array
    {
        $counts = array_fill_keys(array_map(fn (CertificateStatus $s): string => $s->value, CertificateStatus::cases()), 0);

        EquipmentCertificate::query()->whereIn('unit_id', $unitIds)->get(['uji_ulang_tanggal'])
            ->each(function (EquipmentCertificate $cert) use (&$counts): void {
                $counts[$this->monitoring->statusFor($cert->uji_ulang_tanggal)['status']]++;
            });

        return $counts;
    }

    /**
     * Status of each active APAR by the expiry date of its latest check.
     *
     * @param  list<int>  $unitIds
     * @return array<string, int> status => count
     */
    private function extinguisherStatuses(array $unitIds, Carbon $now): array
    {
        $counts = array_fill_keys(array_map(fn (CertificateStatus $s): string => $s->value, CertificateStatus::cases()), 0);
        $latest = FireExtinguisherCheck::query()
            ->whereIn('unit_id', $unitIds)
            ->where(fn ($q) => $q->where('year', '<', $now->year)->orWhere(fn ($q2) => $q2->where('year', $now->year)->where('month', '<=', $now->month)))
            ->orderByDesc('year')->orderByDesc('month')
            ->get(['fire_extinguisher_id', 'exp_date'])
            ->unique('fire_extinguisher_id')
            ->keyBy('fire_extinguisher_id');

        FireExtinguisher::query()->whereIn('unit_id', $unitIds)->where('is_active', true)->pluck('id')
            ->each(function (int $id) use (&$counts, $latest): void {
                $counts[$this->monitoring->statusFor($latest->get($id)?->exp_date)['status']]++;
            });

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{label: string, value: int}>
     */
    private function statusData(array $counts): array
    {
        $rows = [
            ['label' => 'Berlaku', 'value' => $counts[CertificateStatus::Aktif->value]],
            ['label' => 'Segera Habis', 'value' => $counts[CertificateStatus::Mendekati->value]],
            ['label' => 'Expired', 'value' => $counts[CertificateStatus::Expired->value]],
        ];

        if ($counts[CertificateStatus::Belum->value] > 0) {
            $rows[] = ['label' => 'Belum Ada Data', 'value' => $counts[CertificateStatus::Belum->value]];
        }

        return $rows;
    }

    /**
     * Cumulative rencana and realisasi days of the Time Frame kegiatan K3.
     *
     * @param  Collection<int, K3ActivityPlan>  $plans
     * @return list<array{day: int, plan: int, real: int|null}>
     */
    private function sCurve(Collection $plans, Carbon $now): array
    {
        $planPerDay = [];
        $realPerDay = [];
        foreach ($plans as $plan) {
            foreach ($this->days($plan->plan_days) as $day) {
                $planPerDay[$day] = ($planPerDay[$day] ?? 0) + 1;
            }
            foreach ($this->days($plan->real_days) as $day) {
                $realPerDay[$day] = ($realPerDay[$day] ?? 0) + 1;
            }
        }

        $points = [];
        $plan = 0;
        $real = 0;
        for ($day = 1; $day <= $now->daysInMonth; $day++) {
            $plan += $planPerDay[$day] ?? 0;
            $real += $realPerDay[$day] ?? 0;
            $points[] = ['day' => $day, 'plan' => $plan, 'real' => $day <= $now->day ? $real : null];
        }

        return $points;
    }

    /**
     * Realisasi ÷ rencana days per unit per week (percent); null when the
     * week has no rencana. Only units with a Time Frame this month.
     *
     * @param  Collection<int, Unit>  $units
     * @param  Collection<int, K3ActivityPlan>  $plans
     * @return array{columns: list<string>, rows: list<array{label: string, values: list<int|null>}>}
     */
    private function compliance(Collection $units, Collection $plans, Carbon $now): array
    {
        $weeks = array_values(array_filter(self::WEEKS, fn (array $w): bool => $w[0] <= $now->daysInMonth));
        $rows = [];

        foreach ($units as $unit) {
            $own = $plans->where('unit_id', $unit->id);
            if ($own->isEmpty()) {
                continue;
            }

            $rows[] = [
                'label' => $unit->name,
                'values' => array_map(function (array $week) use ($own): ?int {
                    $inWeek = fn (int $day): bool => $day >= $week[0] && $day <= $week[1];
                    $plan = $own->sum(fn (K3ActivityPlan $p): int => count(array_filter($this->days($p->plan_days), $inWeek)));
                    $real = $own->sum(fn (K3ActivityPlan $p): int => count(array_filter($this->days($p->real_days), $inWeek)));

                    return $plan === 0 ? null : (int) min(100, round($real / $plan * 100));
                }, $weeks),
            ];
        }

        return ['columns' => array_map(fn (int $i): string => 'M'.($i + 1), array_keys($weeks)), 'rows' => $rows];
    }

    /**
     * Days of a Time Frame map ("day" => mark).
     *
     * @param  array<array-key, mixed>|null  $map
     * @return list<int>
     */
    private function days(?array $map): array
    {
        $days = [];
        foreach ($map ?? [] as $day => $mark) {
            if ($mark !== null && trim((string) $mark) !== '' && (int) $day >= 1 && (int) $day <= 31) {
                $days[] = (int) $day;
            }
        }

        return $days;
    }
}
