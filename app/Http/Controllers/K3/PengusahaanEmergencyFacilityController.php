<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanEmergencyFacility;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanEmergencyFacilityController extends Controller
{
    public const ALLOWED_PERIODS = ['M1', 'M2', 'M3', 'M4', 'BULANAN'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3PengusahaanView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        if ($request->filled('unit_id')) {
            $unit = Unit::query()->findOrFail($request->integer('unit_id'));
            abort_unless($user->canAccessUnit($unit), 403);
        } else {
            $unit = $units->first();
        }

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);
        $periode = strtoupper(trim((string) ($request->input('periode') ?: 'M1')));
        if (! in_array($periode, self::ALLOWED_PERIODS, true)) {
            $periode = 'M1';
        }

        $savedRows = K3PengusahaanEmergencyFacility::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('periode', $periode)
            ->orderBy('sort_order')
            ->orderBy('no_urut')
            ->get();

        $hasSaved = $savedRows->isNotEmpty();

        $sampleRows = K3PengusahaanEmergencyFacility::buildSampleRows($periode);

        $rows = $hasSaved
            ? $savedRows->map(fn (K3PengusahaanEmergencyFacility $r): array => [
                'id' => $r->id,
                'grup' => $r->grup,
                'no_urut' => $r->no_urut,
                'nama_peralatan' => $r->nama_peralatan,
                'jml_total' => $r->jml_total ?? '',
                'jml_ready' => $r->jml_ready ?? '',
                'jml_not_ready' => $r->jml_not_ready ?? '',
                'persen_kesiapan' => $r->persen_kesiapan ?? '',
                'lokasi' => $r->lokasi ?? '',
                'kendala' => $r->kendala ?? '',
                'tindak_lanjut' => $r->tindak_lanjut ?? '',
                'sort_order' => $r->sort_order,
            ])->all()
            : $sampleRows;

        $firstDoc = $savedRows->first();
        $metadata = [
            'no_dokumen' => $firstDoc?->no_dokumen ?? 'SMT-FM-AK3-12.01',
            'revisi' => $firstDoc?->revisi ?? '00',
            'tanggal_dokumen' => $firstDoc?->tanggal_dokumen ?? '23 September 2019',
            'halaman' => $firstDoc?->halaman ?? '1 dari 1',
        ];

        // Check if there are other periods available in the same month/year to copy from
        $availableSourcePeriods = K3PengusahaanEmergencyFacility::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('periode', '!=', $periode)
            ->select('periode')
            ->distinct()
            ->pluck('periode')
            ->all();

        // Also check previous month if none in current month
        if (empty($availableSourcePeriods)) {
            $prevDate = Carbon::createFromDate($year, $month, 1)->subMonth();
            $prevMonthPeriods = K3PengusahaanEmergencyFacility::query()
                ->where('unit_id', $unit->id)
                ->where('year', $prevDate->year)
                ->where('month', $prevDate->month)
                ->select('periode')
                ->distinct()
                ->pluck('periode')
                ->all();
        } else {
            $prevMonthPeriods = [];
        }

        return Inertia::render('k3/pengusahaan/emergency-facility/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
                'periode' => $periode,
            ],
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
                'periods' => self::ALLOWED_PERIODS,
            ],
            'rows' => $rows,
            'sample_rows' => $sampleRows,
            'metadata' => $metadata,
            'has_saved' => $hasSaved,
            'copy_sources' => [
                'current_month' => $availableSourcePeriods,
                'prev_month' => $prevMonthPeriods,
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::K3PengusahaanWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3PengusahaanWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'periode' => ['required', 'string', 'in:M1,M2,M3,M4,BULANAN'],
            'no_dokumen' => ['nullable', 'string', 'max:100'],
            'revisi' => ['nullable', 'string', 'max:50'],
            'tanggal_dokumen' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'string', 'max:50'],
            'rows' => ['required', 'array'],
            'rows.*.grup' => ['required', 'string', 'max:100'],
            'rows.*.no_urut' => ['required', 'integer'],
            'rows.*.nama_peralatan' => ['required', 'string', 'max:255'],
            'rows.*.jml_total' => ['nullable', 'string', 'max:100'],
            'rows.*.jml_ready' => ['nullable', 'string', 'max:100'],
            'rows.*.jml_not_ready' => ['nullable', 'string', 'max:100'],
            'rows.*.persen_kesiapan' => ['nullable', 'string', 'max:50'],
            'rows.*.lokasi' => ['nullable', 'string', 'max:255'],
            'rows.*.kendala' => ['nullable', 'string'],
            'rows.*.tindak_lanjut' => ['nullable', 'string'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $periode = $validated['periode'];
        $noDokumen = $validated['no_dokumen'] ?? null;
        $revisi = $validated['revisi'] ?? null;
        $tanggalDokumen = $validated['tanggal_dokumen'] ?? null;
        $halaman = $validated['halaman'] ?? null;

        DB::transaction(function () use ($unit, $year, $month, $periode, $noDokumen, $revisi, $tanggalDokumen, $halaman, $validated, $user): void {
            // Delete existing records for this unit/year/month/periode
            K3PengusahaanEmergencyFacility::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->where('periode', $periode)
                ->delete();

            // Insert new rows
            $insertRows = [];
            $now = Carbon::now();
            foreach ($validated['rows'] as $index => $row) {
                $insertRows[] = [
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'periode' => $periode,
                    'no_dokumen' => $noDokumen,
                    'revisi' => $revisi,
                    'tanggal_dokumen' => $tanggalDokumen,
                    'halaman' => $halaman,
                    'grup' => $row['grup'],
                    'no_urut' => (int) $row['no_urut'],
                    'nama_peralatan' => $row['nama_peralatan'],
                    'jml_total' => $row['jml_total'] ?? null,
                    'jml_ready' => $row['jml_ready'] ?? null,
                    'jml_not_ready' => $row['jml_not_ready'] ?? null,
                    'persen_kesiapan' => $row['persen_kesiapan'] ?? null,
                    'lokasi' => $row['lokasi'] ?? null,
                    'kendala' => $row['kendala'] ?? null,
                    'tindak_lanjut' => $row['tindak_lanjut'] ?? null,
                    'sort_order' => $index + 1,
                    'input_by' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($insertRows)) {
                K3PengusahaanEmergencyFacility::query()->insert($insertRows);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Pemeriksaan Emergency Facility Pengusahaan {$unit->name} Periode {$periode} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pemeriksaan Emergency Facility periode {$periode} berhasil disimpan.",
        ]);

        return back();
    }

    public function copy(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3PengusahaanWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'target_year' => ['required', 'integer', 'between:2000,2100'],
            'target_month' => ['required', 'integer', 'between:1,12'],
            'target_periode' => ['required', 'string', 'in:M1,M2,M3,M4,BULANAN'],
            'source_year' => ['required', 'integer', 'between:2000,2100'],
            'source_month' => ['required', 'integer', 'between:1,12'],
            'source_periode' => ['required', 'string', 'in:M1,M2,M3,M4,BULANAN'],
        ]);

        $sourceRows = K3PengusahaanEmergencyFacility::query()
            ->where('unit_id', $unit->id)
            ->where('year', (int) $validated['source_year'])
            ->where('month', (int) $validated['source_month'])
            ->where('periode', $validated['source_periode'])
            ->orderBy('sort_order')
            ->orderBy('no_urut')
            ->get();

        if ($sourceRows->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Tidak ada data pada periode sumber {$validated['source_periode']} untuk disalin.",
            ]);

            return back();
        }

        DB::transaction(function () use ($unit, $validated, $sourceRows, $user): void {
            K3PengusahaanEmergencyFacility::query()
                ->where('unit_id', $unit->id)
                ->where('year', (int) $validated['target_year'])
                ->where('month', (int) $validated['target_month'])
                ->where('periode', $validated['target_periode'])
                ->delete();

            $now = Carbon::now();
            $newRows = [];
            foreach ($sourceRows as $row) {
                $newRows[] = [
                    'unit_id' => $unit->id,
                    'year' => (int) $validated['target_year'],
                    'month' => (int) $validated['target_month'],
                    'periode' => $validated['target_periode'],
                    'no_dokumen' => $row->no_dokumen,
                    'revisi' => $row->revisi,
                    'tanggal_dokumen' => $row->tanggal_dokumen,
                    'halaman' => $row->halaman,
                    'grup' => $row->grup,
                    'no_urut' => $row->no_urut,
                    'nama_peralatan' => $row->nama_peralatan,
                    'jml_total' => $row->jml_total,
                    'jml_ready' => $row->jml_ready,
                    'jml_not_ready' => $row->jml_not_ready,
                    'persen_kesiapan' => $row->persen_kesiapan,
                    'lokasi' => $row->lokasi,
                    'kendala' => $row->kendala,
                    'tindak_lanjut' => $row->tindak_lanjut,
                    'sort_order' => $row->sort_order,
                    'input_by' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            K3PengusahaanEmergencyFacility::query()->insert($newRows);
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyalin Pemeriksaan Emergency Facility Pengusahaan {$unit->name} dari {$validated['source_periode']} ke {$validated['target_periode']}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Data berhasil disalin dari periode {$validated['source_periode']} ke {$validated['target_periode']}.",
        ]);

        return back();
    }
}
