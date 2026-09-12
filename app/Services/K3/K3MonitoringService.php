<?php

namespace App\Services\K3;

use App\Enums\CertificateStatus;
use App\Models\AccidentReport;
use App\Models\EmergencyFacilityCheck;
use App\Models\EquipmentCertificate;
use App\Models\FireExtinguisher;
use App\Models\FireExtinguisherCheck;
use App\Models\K3ActivityPlan;
use App\Models\SecurityPatrol;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Computes the K3 monitoring dashboard: certificate & extinguisher expiry
 * statuses (badge = aktif / mendekati / expired, derived from the retest/expiry
 * date against today) plus the running month's summary. Labels in the system,
 * not push notifications.
 */
class K3MonitoringService
{
    /**
     * Derive the status and remaining time for a retest/expiry date.
     *
     * @return array{status: string, tone: string, days: int|null, months: int|null}
     */
    public function statusFor(?CarbonInterface $date): array
    {
        if ($date === null) {
            return ['status' => CertificateStatus::Belum->value, 'tone' => CertificateStatus::Belum->tone(), 'days' => null, 'months' => null];
        }

        $days = (int) Carbon::today()->diffInDays($date, false);
        $threshold = (int) config('k3.expiry_warning_days', 60);

        $status = match (true) {
            $days < 0 => CertificateStatus::Expired,
            $days <= $threshold => CertificateStatus::Mendekati,
            default => CertificateStatus::Aktif,
        };

        return [
            'status' => $status->value,
            'tone' => $status->tone(),
            'days' => $days,
            'months' => intdiv(abs($days), 30) * ($days < 0 ? -1 : 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Unit $unit, int $month, int $year): array
    {
        return [
            'unit' => ['name' => $unit->name],
            'period' => ['month' => $month, 'year' => $year],
            'threshold_days' => (int) config('k3.expiry_warning_days', 60),
            'certificates' => $this->certificates($unit),
            'extinguishers' => $this->extinguishers($unit, $month, $year),
            'summary' => $this->summary($unit, $month, $year),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function certificates(Unit $unit): array
    {
        return EquipmentCertificate::query()
            ->where('unit_id', $unit->id)
            ->with('category:id,name')
            ->orderBy('uji_ulang_tanggal')
            ->get()
            ->map(function (EquipmentCertificate $cert): array {
                $status = $this->statusFor($cert->uji_ulang_tanggal);

                return [
                    'jenis' => $cert->jenis,
                    'category' => $cert->category?->name,
                    'kapasitas' => $cert->kapasitas,
                    'lokasi' => $cert->lokasi,
                    'no_seri' => $cert->no_seri,
                    'uji_terakhir_tanggal' => $cert->uji_terakhir_tanggal?->format('Y-m-d'),
                    'uji_ulang_tanggal' => $cert->uji_ulang_tanggal?->format('Y-m-d'),
                    ...$status,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extinguishers(Unit $unit, int $month, int $year): array
    {
        $latestChecks = FireExtinguisherCheck::query()
            ->where('unit_id', $unit->id)
            ->where(fn ($q) => $q->where('year', '<', $year)->orWhere(fn ($q2) => $q2->where('year', $year)->where('month', '<=', $month)))
            ->orderByDesc('year')->orderByDesc('month')
            ->get()
            ->groupBy('fire_extinguisher_id')
            ->map(fn ($group) => $group->first());

        return FireExtinguisher::query()
            ->where('unit_id', $unit->id)->where('is_active', true)
            ->orderBy('location')
            ->get()
            ->map(function (FireExtinguisher $ext) use ($latestChecks): array {
                $check = $latestChecks->get($ext->id);
                $status = $this->statusFor($check?->exp_date);

                return [
                    'rfid' => $ext->rfid,
                    'location' => $ext->location,
                    'jenis' => $ext->jenis,
                    'exp_date' => $check?->exp_date?->format('Y-m-d'),
                    'kondisi_tabung' => $check?->kondisi_tabung,
                    ...$status,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Unit $unit, int $month, int $year): array
    {
        $accidents = AccidentReport::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->get();
        $realCount = K3ActivityPlan::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->get()
            ->filter(fn (K3ActivityPlan $p): bool => ! empty($p->real_days))
            ->count();

        $patrolTotal = (int) SecurityPatrol::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('patrol_date', [Carbon::create($year, $month, 1)->toDateString(), Carbon::create($year, $month, 1)->endOfMonth()->toDateString()])
            ->sum('total_scan');

        $emergency = EmergencyFacilityCheck::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->whereNull('week')->get();
        $emTotal = (int) $emergency->sum('jml_total');
        $emReady = (int) $emergency->sum('jml_ready');

        $certificates = $this->certificates($unit);
        $extinguishers = $this->extinguishers($unit, $month, $year);
        $countBy = fn (array $items, string $status): int => collect($items)->where('status', $status)->count();

        $accidentCasualties = (int) $accidents->sum('luka_ringan') + (int) $accidents->sum('luka_berat') + (int) $accidents->sum('meninggal');

        return [
            'accident_nihil' => $accidents->isEmpty() || $accidents->every(fn (AccidentReport $a): bool => (bool) $a->is_nihil),
            'accident_casualties' => $accidentCasualties,
            'activities_realized' => $realCount,
            'patrol_total_scan' => $patrolTotal,
            'emergency_readiness' => $emTotal > 0 ? round($emReady / $emTotal * 100, 1) : null,
            'cert_expired' => $countBy($certificates, CertificateStatus::Expired->value),
            'cert_mendekati' => $countBy($certificates, CertificateStatus::Mendekati->value),
            'apar_expired' => $countBy($extinguishers, CertificateStatus::Expired->value),
            'apar_mendekati' => $countBy($extinguishers, CertificateStatus::Mendekati->value),
        ];
    }
}
