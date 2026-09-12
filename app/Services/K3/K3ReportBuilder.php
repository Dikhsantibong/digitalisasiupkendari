<?php

namespace App\Services\K3;

use App\Models\AccidentReport;
use App\Models\EmergencyFacilityCheck;
use App\Models\EquipmentCertificate;
use App\Models\FireExtinguisher;
use App\Models\FireExtinguisherCheck;
use App\Models\Inspection;
use App\Models\K3ActivityPlan;
use App\Models\K3Attachment;
use App\Models\SecurityPatrol;
use App\Models\Unit;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Assembles the K3 monthly report from every K3 input for a unit/period, reusing
 * {@see K3MonitoringService} for the derived expiry statuses. Feeds both the
 * printable report and the editable document.
 */
class K3ReportBuilder
{
    public function __construct(private readonly K3MonitoringService $monitoring) {}

    /**
     * @return array<string, mixed>
     */
    public function monthly(Unit $unit, int $month, int $year): array
    {
        $unit->loadMissing('serviceUnit:id,name');

        return [
            'unit' => ['name' => $unit->name, 'service_unit' => $unit->serviceUnit?->name],
            'period' => ['month' => $month, 'year' => $year, 'label' => Indonesian::monthName($month).' '.$year],
            'time_frame' => $this->timeFrame($unit->id, $month, $year),
            'accidents' => $this->accidents($unit->id, $month, $year),
            'inspections' => $this->inspections($unit->id, $month, $year),
            'apar' => $this->apar($unit, $month, $year),
            'emergency' => $this->emergency($unit->id, $month, $year),
            'patrol' => $this->patrol($unit->id, $month, $year),
            'certificates' => $this->certificates($unit),
            'attachments' => $this->attachments($unit->id, $month, $year),
        ];
    }

    /**
     * @return list<array{activity: string, pic: string|null, plan: int, real: int}>
     */
    private function timeFrame(int $unitId, int $month, int $year): array
    {
        return K3ActivityPlan::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->with('activityType:id,name')
            ->get()
            ->map(fn (K3ActivityPlan $p): array => [
                'activity' => $p->activityType?->name ?? '—',
                'pic' => $p->pic,
                'plan' => count($p->plan_days ?? []),
                'real' => count($p->real_days ?? []),
            ])
            ->all();
    }

    /**
     * @return array{nihil: bool, casualties: int, rows: list<array<string, mixed>>}
     */
    private function accidents(int $unitId, int $month, int $year): array
    {
        $reports = AccidentReport::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->get();

        return [
            'nihil' => $reports->isEmpty() || $reports->every(fn (AccidentReport $a): bool => (bool) $a->is_nihil),
            'casualties' => (int) $reports->sum('luka_ringan') + (int) $reports->sum('luka_berat') + (int) $reports->sum('meninggal'),
            'rows' => $reports->reject(fn (AccidentReport $a): bool => (bool) $a->is_nihil)->map(fn (AccidentReport $a): array => [
                'category' => $a->category->label(),
                'lokasi' => $a->lokasi,
                'luka_ringan' => $a->luka_ringan,
                'luka_berat' => $a->luka_berat,
                'meninggal' => $a->meninggal,
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array{form_code: string, date: string|null, items: int, ketua_tim: string|null}>
     */
    private function inspections(int $unitId, int $month, int $year): array
    {
        return Inspection::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->withCount('results')
            ->orderBy('form_code')
            ->get()
            ->map(fn (Inspection $i): array => [
                'form_code' => $i->form_code,
                'date' => $i->inspection_date?->format('Y-m-d'),
                'items' => (int) $i->results_count,
                'ketua_tim' => $i->ketua_tim,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function apar(Unit $unit, int $month, int $year): array
    {
        $latest = FireExtinguisherCheck::query()
            ->where('unit_id', $unit->id)
            ->where(fn ($q) => $q->where('year', '<', $year)->orWhere(fn ($q2) => $q2->where('year', $year)->where('month', '<=', $month)))
            ->orderByDesc('year')->orderByDesc('month')->get()
            ->groupBy('fire_extinguisher_id')->map(fn ($g) => $g->first());

        return FireExtinguisher::query()
            ->where('unit_id', $unit->id)->where('is_active', true)->orderBy('location')->get()
            ->map(function (FireExtinguisher $ext) use ($latest): array {
                $check = $latest->get($ext->id);

                return [
                    'rfid' => $ext->rfid,
                    'location' => $ext->location,
                    'kondisi' => $check?->kondisi_tabung,
                    'exp_date' => $check?->exp_date?->format('Y-m-d'),
                    ...$this->monitoring->statusFor($check?->exp_date),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function emergency(int $unitId, int $month, int $year): array
    {
        return EmergencyFacilityCheck::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->whereNull('week')
            ->with('equipment:id,name')
            ->get()
            ->map(function (EmergencyFacilityCheck $c): array {
                $total = (int) $c->jml_total;

                return [
                    'name' => $c->equipment?->name ?? '—',
                    'total' => $c->jml_total,
                    'ready' => $c->jml_ready,
                    'not_ready' => $c->jml_not_ready,
                    'percent' => $total > 0 ? round((int) $c->jml_ready / $total * 100, 1).'%' : '—',
                ];
            })
            ->all();
    }

    /**
     * @return list<array{location: string, total: int}>
     */
    private function patrol(int $unitId, int $month, int $year): array
    {
        return SecurityPatrol::query()
            ->where('unit_id', $unitId)
            ->whereBetween('patrol_date', [Carbon::create($year, $month, 1)->toDateString(), Carbon::create($year, $month, 1)->endOfMonth()->toDateString()])
            ->with('location:id,code,name')
            ->get()
            ->groupBy('patrol_location_id')
            ->map(fn ($group): array => [
                'location' => trim(($group->first()->location?->code ?? '').' — '.($group->first()->location?->name ?? '')),
                'total' => (int) $group->sum('total_scan'),
                'code' => $group->first()->location?->code ?? '',
            ])
            ->sortBy('code')
            ->map(fn (array $row): array => ['location' => $row['location'], 'total' => $row['total']])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function certificates(Unit $unit): array
    {
        return EquipmentCertificate::query()
            ->where('unit_id', $unit->id)->with('category:id,name')->orderBy('uji_ulang_tanggal')->get()
            ->map(fn (EquipmentCertificate $c): array => [
                'jenis' => $c->jenis,
                'category' => $c->category?->name,
                'lokasi' => $c->lokasi,
                'uji_ulang_tanggal' => $c->uji_ulang_tanggal?->format('Y-m-d'),
                ...$this->monitoring->statusFor($c->uji_ulang_tanggal),
            ])
            ->all();
    }

    /**
     * @return list<array{title: string, category: string|null, url: string}>
     */
    private function attachments(int $unitId, int $month, int $year): array
    {
        return K3Attachment::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->orderBy('id')->get()
            ->map(fn (K3Attachment $a): array => [
                'title' => $a->title,
                'category' => $a->category,
                'url' => Storage::disk('public')->url($a->file_path),
            ])
            ->all();
    }
}
