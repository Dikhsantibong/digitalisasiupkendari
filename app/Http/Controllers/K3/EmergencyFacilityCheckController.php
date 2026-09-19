<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3EmergencyFacilityCheck;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pemeriksaan Emergency Facility (modul K3): matriks kesiapan peralatan darurat
 * dikelompokkan per grup. % Kesiapan = jml_ready ÷ jml_total (dihitung di UI).
 */
class EmergencyFacilityCheckController extends Controller
{
    /**
     * Default equipment list per group, used to seed the matrix the first time a
     * unit/period is opened.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULTS = [
        'Fire Pump PLTD' => [
            'Fire Protection Jockey Pump', 'Fire Protection Electric Pump', 'Fire Protection Diesel Pump',
            'Sea Water Fire Protection Pump', 'Fire Water Level',
        ],
        'Fire Pump PLTG' => [
            'Fire Protection Jockey Pump', 'Fire Protection Electric Pump', 'Fire Protection Diesel Pump',
            'Sea Water Fire Protection Pump', 'Fire Water Level',
        ],
        'Fire Protection System' => [
            'Deluge System', 'Sprinkler System', 'Low Pressure Gas Fire Suppression System',
            'High Pressure Gas Fire Suppression System', 'Foam System',
        ],
        'Fire Detector & Alarm System PLTD' => [
            'Smoke Detector', 'Heat Detector', 'UV/IR Detector', 'Gas Detector', 'Flame Detector', 'FACP',
        ],
        'Fire Detector & Alarm System PLTG' => [
            'Smoke Detector', 'Heat Detector', 'Linear Heat Detector (LHD)', 'UV/IR Detector', 'Gas Detector', 'Flame Detector', 'FACP',
        ],
        'Emergency Preparedness' => [
            'SOP Tanggap Darurat', 'Simulasi Tanggap Darurat / Emergency Drill', 'Pejabat K3 Bersertifikasi Pemadam Kebakaran',
            'Tangga Darurat', 'PMK Mobile / Fire Truck', 'Emergency Response Team',
        ],
        'Emergency Medical Service' => [
            'Ambulance Mobile', 'Dokter Perusahaan', 'Perawat Perusahaan', 'Ruang Pelayanan Kesehatan / Ruang Dokter',
        ],
        'Alat Pemadam Api PLTD' => [
            'APAR Jenis Foam', 'APAR Jenis CO2', 'APAR Jenis AF 31', 'APAR Jenis Powder', 'APAT',
            'Hydrant Pilar', 'Box Hydrant Out door', 'Box Hydrant In door',
        ],
        'Alat Pemadam Api PLTG' => [
            'APAR Jenis Foam', 'APAR Jenis CO2', 'APAR Jenis Powder', 'Hydrant Pilar', 'Box Hydrant Out door',
        ],
        'Alat Pelindung Diri' => [
            'Self Contain Breathing Apparatus Lengkap', 'Baju Tahan Api', 'Baju Tahan Panas / suit fire fighting',
            'Life Jacket / Life Vest', 'Ring Buoy', 'Body Harness', 'Liquid & Particel Protection (Coverall suit)',
            'Safety Helmet (visitor)', 'Safety Shoes (visitor)', 'Gloves / Sarung tangan 20 KV', 'Safety Shoes 20 KV',
        ],
        'Lain - Lain' => [
            'SLO', 'EXIT Lighting', 'Speed Boat', 'Kotak P3K Lengkap Isi', 'Peralatan Rescue lengkap',
            'Peralatan PPGD lengkap', 'Pest Controll', 'Cleaning Service', 'Security Team', 'Security Task Force',
            'Guard Patrol', 'Security Check', 'Tespen 6,3 KV - 20 KV', 'Baju Rompi Security',
        ],
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::K3InputView) ||
            $user->hasPermissionTo(PermissionName::K3LaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        $saved = K3EmergencyFacilityCheck::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get();

        if ($saved->isEmpty()) {
            $rows = [];
            $sort = 0;
            foreach (self::DEFAULTS as $grup => $items) {
                foreach ($items as $nama) {
                    $rows[] = [
                        'id' => null, 'grup' => $grup, 'nama_peralatan' => $nama,
                        'jml_total' => 0, 'jml_ready' => 0, 'jml_not_ready' => 0,
                        'lokasi' => '', 'kendala' => '', 'tindak_lanjut' => '', 'sort_order' => $sort++,
                    ];
                }
            }
        } else {
            $rows = $saved->map(fn (K3EmergencyFacilityCheck $r): array => [
                'id' => $r->id, 'grup' => $r->grup, 'nama_peralatan' => $r->nama_peralatan,
                'jml_total' => $r->jml_total, 'jml_ready' => $r->jml_ready, 'jml_not_ready' => $r->jml_not_ready,
                'lokasi' => $r->lokasi ?? '', 'kendala' => $r->kendala ?? '', 'tindak_lanjut' => $r->tindak_lanjut ?? '',
                'sort_order' => $r->sort_order,
            ])->all();
        }

        return Inertia::render('k3/input/emergency-facility', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'groups' => array_keys(self::DEFAULTS),
            'rows' => $rows,
            'options' => ['units' => $units->all(), 'years' => range($now->year - 3, $now->year + 1)],
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.grup' => ['required', 'string', 'max:100'],
            'rows.*.nama_peralatan' => ['required', 'string', 'max:255'],
            'rows.*.jml_total' => ['nullable', 'integer', 'min:0'],
            'rows.*.jml_ready' => ['nullable', 'integer', 'min:0'],
            'rows.*.jml_not_ready' => ['nullable', 'integer', 'min:0'],
            'rows.*.lokasi' => ['nullable', 'string', 'max:500'],
            'rows.*.kendala' => ['nullable', 'string', 'max:500'],
            'rows.*.tindak_lanjut' => ['nullable', 'string', 'max:500'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            $existingIds = [];
            foreach ($validated['rows'] ?? [] as $index => $row) {
                $attributes = [
                    'grup' => $row['grup'],
                    'no_urut' => $index + 1,
                    'nama_peralatan' => $row['nama_peralatan'],
                    'jml_total' => (int) ($row['jml_total'] ?? 0),
                    'jml_ready' => (int) ($row['jml_ready'] ?? 0),
                    'jml_not_ready' => (int) ($row['jml_not_ready'] ?? 0),
                    'lokasi' => $row['lokasi'] ?? null,
                    'kendala' => $row['kendala'] ?? null,
                    'tindak_lanjut' => $row['tindak_lanjut'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ];

                if (! empty($row['id'])) {
                    $record = K3EmergencyFacilityCheck::query()->where('id', $row['id'])->where('unit_id', $unit->id)->first();
                    if ($record) {
                        $record->update($attributes);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $existingIds[] = K3EmergencyFacilityCheck::create([...$attributes, 'unit_id' => $unit->id, 'year' => $year, 'month' => $month])->id;
            }

            K3EmergencyFacilityCheck::query()
                ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
                ->when($existingIds !== [], fn ($q) => $q->whereNotIn('id', $existingIds))
                ->delete();
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Pemeriksaan Emergency Facility {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Pemeriksaan Emergency Facility berhasil disimpan.']);
    }
}
