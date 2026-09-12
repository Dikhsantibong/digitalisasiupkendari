<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\EquipmentCategory;
use App\Models\EquipmentCertificate;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Equipment certificate registry (modul K3). A per-unit grid of certificates;
 * the category is typed as a code and resolved to the global master. Saving
 * replaces the unit's certificate rows wholesale (Excel-paste friendly).
 */
class CertificateController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $rows = EquipmentCertificate::query()
            ->where('unit_id', $unit->id)->with('category:id,code')->orderBy('jenis')
            ->get()
            ->map(fn (EquipmentCertificate $c): array => [
                'category_code' => $c->category?->code,
                'jenis' => $c->jenis,
                'kapasitas' => $c->kapasitas,
                'lokasi' => $c->lokasi,
                'merk_manufacture' => $c->merk_manufacture,
                'no_seri' => $c->no_seri,
                'regulasi' => $c->regulasi,
                'ijin_awal_nomor' => $c->ijin_awal_nomor,
                'ijin_awal_tanggal' => $c->ijin_awal_tanggal?->format('Y-m-d'),
                'uji_terakhir_nomor' => $c->uji_terakhir_nomor,
                'uji_terakhir_tanggal' => $c->uji_terakhir_tanggal?->format('Y-m-d'),
                'uji_ulang_tanggal' => $c->uji_ulang_tanggal?->format('Y-m-d'),
                'batasan_uji' => $c->batasan_uji,
                'masa_berlaku_tahun' => $c->masa_berlaku_tahun,
                'keterangan' => $c->keterangan,
            ])
            ->all();

        return Inertia::render('k3/input/certificates', [
            'filters' => ['unit_id' => $unit->id],
            'rows' => $rows,
            'options' => [
                'units' => $units->all(),
                'categories' => EquipmentCategory::query()->orderBy('code')->get(['code', 'name'])
                    ->map(fn (EquipmentCategory $c): array => ['code' => $c->code, 'name' => $c->name])->all(),
            ],
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
            'rows' => ['array'],
            'rows.*.category_code' => ['nullable', 'string', 'max:255'],
            'rows.*.jenis' => ['required', 'string', 'max:255'],
            'rows.*.kapasitas' => ['nullable', 'string', 'max:255'],
            'rows.*.lokasi' => ['nullable', 'string', 'max:255'],
            'rows.*.merk_manufacture' => ['nullable', 'string', 'max:255'],
            'rows.*.no_seri' => ['nullable', 'string', 'max:255'],
            'rows.*.regulasi' => ['nullable', 'string', 'max:255'],
            'rows.*.ijin_awal_nomor' => ['nullable', 'string', 'max:255'],
            'rows.*.ijin_awal_tanggal' => ['nullable', 'date'],
            'rows.*.uji_terakhir_nomor' => ['nullable', 'string', 'max:255'],
            'rows.*.uji_terakhir_tanggal' => ['nullable', 'date'],
            'rows.*.uji_ulang_tanggal' => ['nullable', 'date'],
            'rows.*.batasan_uji' => ['nullable', 'string', 'max:255'],
            'rows.*.masa_berlaku_tahun' => ['nullable', 'integer', 'min:0', 'max:255'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $categories = EquipmentCategory::query()->pluck('id', 'code');

        DB::transaction(function () use ($validated, $unit, $categories, $user): void {
            EquipmentCertificate::query()->where('unit_id', $unit->id)->delete();

            foreach ($validated['rows'] ?? [] as $row) {
                if (trim((string) ($row['jenis'] ?? '')) === '') {
                    continue;
                }

                EquipmentCertificate::query()->create([
                    'unit_id' => $unit->id,
                    'equipment_category_id' => $categories[$row['category_code'] ?? ''] ?? null,
                    'jenis' => $row['jenis'],
                    'kapasitas' => $row['kapasitas'] ?? null,
                    'lokasi' => $row['lokasi'] ?? null,
                    'merk_manufacture' => $row['merk_manufacture'] ?? null,
                    'no_seri' => $row['no_seri'] ?? null,
                    'regulasi' => $row['regulasi'] ?? null,
                    'ijin_awal_nomor' => $row['ijin_awal_nomor'] ?? null,
                    'ijin_awal_tanggal' => $row['ijin_awal_tanggal'] ?? null,
                    'uji_terakhir_nomor' => $row['uji_terakhir_nomor'] ?? null,
                    'uji_terakhir_tanggal' => $row['uji_terakhir_tanggal'] ?? null,
                    'uji_ulang_tanggal' => $row['uji_ulang_tanggal'] ?? null,
                    'batasan_uji' => $row['batasan_uji'] ?? null,
                    'masa_berlaku_tahun' => $row['masa_berlaku_tahun'] ?? null,
                    'keterangan' => $row['keterangan'] ?? null,
                    'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan sertifikat peralatan {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data sertifikat disimpan.']);

        return back();
    }
}
