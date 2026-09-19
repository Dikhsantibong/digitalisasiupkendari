<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\OperasiMaterialPeralatan;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class MaterialPeralatanController extends Controller
{
    private const DEFAULT_MATERIALS = [
        ['nama_item' => 'Air Aki Tambah kemasan 1L/Btl Reff Yuasa', 'satuan' => 'Botol', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Air Aki Zuur 1L/btl', 'satuan' => 'Botol', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Alat Pel', 'satuan' => 'bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Amplas Grip 80', 'satuan' => 'Lembar', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Amplas Grip 150', 'satuan' => 'Lembar', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Amplas Grip 400', 'satuan' => 'Lembar', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Amplas Grip 500', 'satuan' => 'Lembar', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Amplas Grip 800', 'satuan' => 'Lembar', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Amplas Grip 1000', 'satuan' => 'Lembar', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Autosol', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Balon lampu Heater 300 watt', 'satuan' => 'Pcs', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Baterai AA', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Baterai AAA', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Baterai Kotak 9 Volt c-', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Botol (BOTTLE, OIL SAMPLE) 120ml', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Brasso', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Cat Kaleng Abu-Abu', 'satuan' => 'Kaleng', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Contact Cleaner @360 ml', 'satuan' => 'Kaleng', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Earmuff', 'satuan' => 'Dus', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Gas Tourch', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Gas Portable (Winn Gas Butane @235gr)', 'satuan' => 'Kaleng', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Gris', 'satuan' => 'Kaleng', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Gagang Sapu', 'satuan' => 'bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Isolasi 20 Kv Scotch 3M', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Isolasi Listrik 0,13mmx19mmx20mtr Unibel', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Kabel Serabut 2 x 1,5 mm', 'satuan' => 'Meter', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Kabel Ties 10 Cm @100pcs/pack', 'satuan' => 'Bungkus', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Kabel Ties 20 Cm @100pcs/pack', 'satuan' => 'Bungkus', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Kabel Ties 30 Cm @100pcs/pack', 'satuan' => 'Bungkus', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Kaos Tangan (Bintik)', 'satuan' => 'Bungkus', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Kawat Las RD-260 2,0 mm', 'satuan' => 'Kg', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Klem kabel Nomor 9 @100pcs/pack', 'satuan' => 'Pack', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Keran Air', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Kunci Inggris', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lampu Sorot 500W', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lampu Merkuri 160W', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lampu LED 40 watt Reff Philips', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lampu LED 60 Watt', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lem Besi Epoxy @48gr (Dextone)', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lem Castol', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lem Epoxy Avian Super (Resin & Hardness) K', 'satuan' => 'Kaleng', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lem Korea @30gr', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lem RTV High Temperature Dextone @30gr', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lem RTV High Temperature Dextone @75gr', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lem Loctite', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Lem Pipa', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Majun Putih Kain Perca', 'satuan' => 'Kg', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Majun Handuk', 'satuan' => 'Lembar', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Masker', 'satuan' => 'Dus', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Mata Gurinda Potong Kecil Tipis 4"', 'satuan' => 'Dus', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Mata Gurinda Amplas Kasar', 'satuan' => 'Lembar', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Mata Gurinda Amplas Halus', 'satuan' => 'Lembar', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Mata Cutter', 'satuan' => 'Pcs', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Mistar 60cm', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Mesin Las', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Mata Gergaji Besi', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Pasta Minyak @62gr Reff Kolor Kut', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Pasta Grinding', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Pisau Cutter', 'satuan' => 'Bh', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
        ['nama_item' => 'Paku Rivet', 'satuan' => 'Pcs', 'safety_stock' => 2, 'ilt' => 3, 'rop' => 2, 'roq' => 2],
    ];

    private const DEFAULT_PERALATAN = [
        ['nama_item' => 'Toolkit Mekanik Lengkap', 'satuan' => 'Set'],
        ['nama_item' => 'Kunci Pas Ring Set (6-32 mm)', 'satuan' => 'Set'],
        ['nama_item' => 'Kunci Socket Set', 'satuan' => 'Set'],
        ['nama_item' => 'Kunci Momen / Torque Wrench', 'satuan' => 'Unit'],
        ['nama_item' => 'Multitester Digital', 'satuan' => 'Unit'],
        ['nama_item' => 'Tang Kombinasi', 'satuan' => 'Unit'],
        ['nama_item' => 'Tang Buaya', 'satuan' => 'Unit'],
        ['nama_item' => 'Gerinda Tangan 4 Inch', 'satuan' => 'Unit'],
        ['nama_item' => 'Bor Listrik', 'satuan' => 'Unit'],
        ['nama_item' => 'Hydraulic Jack / Dongkrak', 'satuan' => 'Unit'],
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::OperasiInputView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView) ||
            $user->hasPermissionTo(PermissionName::OperasiInputView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        $records = OperasiMaterialPeralatan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            $sort = 1;
            foreach (self::DEFAULT_PERALATAN as $per) {
                OperasiMaterialPeralatan::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'kategori' => 'PERALATAN',
                    'nama_item' => $per['nama_item'],
                    'satuan' => $per['satuan'],
                    'stok_awal' => 0,
                    'masuk' => 0,
                    'keluar' => 0,
                    'stok_akhir' => 0,
                    'harga_satuan' => 0,
                    'pemakaian_rata_rata' => 0,
                    'safety_stock' => 0,
                    'ilt' => 0,
                    'rop' => 0,
                    'roq' => 0,
                    'sort_order' => $sort++,
                    'input_by' => $user->id,
                ]);
            }

            foreach (self::DEFAULT_MATERIALS as $mat) {
                OperasiMaterialPeralatan::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'kategori' => 'MATERIAL',
                    'nama_item' => $mat['nama_item'],
                    'satuan' => $mat['satuan'],
                    'stok_awal' => 0,
                    'masuk' => 0,
                    'keluar' => 0,
                    'stok_akhir' => 0,
                    'harga_satuan' => 0,
                    'pemakaian_rata_rata' => 0,
                    'safety_stock' => $mat['safety_stock'],
                    'ilt' => $mat['ilt'],
                    'rop' => $mat['rop'],
                    'roq' => $mat['roq'],
                    'sort_order' => $sort++,
                    'input_by' => $user->id,
                ]);
            }

            $records = OperasiMaterialPeralatan::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        $peralatan = $records->where('kategori', 'PERALATAN')->values()->all();
        $material = $records->where('kategori', 'MATERIAL')->values()->all();

        $totalPeralatanCount = count($peralatan);
        $totalMaterialCount = count($material);
        $totalNilaiStok = $records->sum(fn ($r) => (float) $r->stok_akhir * (float) $r->harga_satuan);
        $lowStockCount = $records->filter(fn ($r) => (float) $r->rop > 0 && (float) $r->stok_akhir <= (float) $r->rop)->count();

        $canWrite = $user->hasPermissionTo(PermissionName::OperasiInputWrite) ||
            $user->hasPermissionTo(PermissionName::OperasiInputWrite);

        return Inertia::render('operasi/input/material-peralatan', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'peralatan' => $peralatan,
            'material' => $material,
            'summary' => [
                'total_peralatan' => $totalPeralatanCount,
                'total_material' => $totalMaterialCount,
                'total_nilai_stok' => $totalNilaiStok,
                'low_stock_count' => $lowStockCount,
            ],
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
            ],
            'can_write' => $canWrite,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::OperasiInputWrite) ||
            $user->hasPermissionTo(PermissionName::OperasiInputWrite),
            403
        );

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.kategori' => ['required', 'string', 'in:PERALATAN,MATERIAL'],
            'items.*.kode_material' => ['nullable', 'string', 'max:100'],
            'items.*.stok_code' => ['nullable', 'string', 'max:100'],
            'items.*.nama_item' => ['required', 'string', 'max:255'],
            'items.*.stok_awal' => ['nullable', 'numeric', 'min:0'],
            'items.*.masuk' => ['nullable', 'numeric', 'min:0'],
            'items.*.keluar' => ['nullable', 'numeric', 'min:0'],
            'items.*.satuan' => ['nullable', 'string', 'max:50'],
            'items.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'items.*.pemakaian_rata_rata' => ['nullable', 'numeric', 'min:0'],
            'items.*.safety_stock' => ['nullable', 'numeric', 'min:0'],
            'items.*.ilt' => ['nullable', 'numeric', 'min:0'],
            'items.*.rop' => ['nullable', 'numeric', 'min:0'],
            'items.*.roq' => ['nullable', 'numeric', 'min:0'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        foreach ($validated['items'] as $index => $item) {
            $stokAwal = (float) ($item['stok_awal'] ?? 0);
            $masuk = (float) ($item['masuk'] ?? 0);
            $keluar = (float) ($item['keluar'] ?? 0);
            $stokAkhir = max(0, $stokAwal + $masuk - $keluar);

            if (! empty($item['id'])) {
                OperasiMaterialPeralatan::query()
                    ->where('id', (int) $item['id'])
                    ->where('unit_id', $unit->id)
                    ->update([
                        'kategori' => $item['kategori'],
                        'kode_material' => $item['kode_material'] ?? null,
                        'stok_code' => $item['stok_code'] ?? null,
                        'nama_item' => $item['nama_item'],
                        'stok_awal' => $stokAwal,
                        'masuk' => $masuk,
                        'keluar' => $keluar,
                        'stok_akhir' => $stokAkhir,
                        'satuan' => $item['satuan'] ?? null,
                        'harga_satuan' => (float) ($item['harga_satuan'] ?? 0),
                        'pemakaian_rata_rata' => (float) ($item['pemakaian_rata_rata'] ?? 0),
                        'safety_stock' => (float) ($item['safety_stock'] ?? 0),
                        'ilt' => (float) ($item['ilt'] ?? 0),
                        'rop' => (float) ($item['rop'] ?? 0),
                        'roq' => (float) ($item['roq'] ?? 0),
                        'sort_order' => (int) ($item['sort_order'] ?? $index + 1),
                    ]);
            } else {
                OperasiMaterialPeralatan::query()->create([
                    'unit_id' => $unit->id,
                    'year' => (int) $validated['year'],
                    'month' => (int) $validated['month'],
                    'kategori' => $item['kategori'],
                    'kode_material' => $item['kode_material'] ?? null,
                    'stok_code' => $item['stok_code'] ?? null,
                    'nama_item' => $item['nama_item'],
                    'stok_awal' => $stokAwal,
                    'masuk' => $masuk,
                    'keluar' => $keluar,
                    'stok_akhir' => $stokAkhir,
                    'satuan' => $item['satuan'] ?? null,
                    'harga_satuan' => (float) ($item['harga_satuan'] ?? 0),
                    'pemakaian_rata_rata' => (float) ($item['pemakaian_rata_rata'] ?? 0),
                    'safety_stock' => (float) ($item['safety_stock'] ?? 0),
                    'ilt' => (float) ($item['ilt'] ?? 0),
                    'rop' => (float) ($item['rop'] ?? 0),
                    'roq' => (float) ($item['roq'] ?? 0),
                    'sort_order' => (int) ($item['sort_order'] ?? $index + 1),
                    'input_by' => $user->id,
                ]);
            }
        }

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan laporan material & peralatan {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan material & peralatan berhasil disimpan.']);

        return back();
    }

    public function destroy(Request $request, OperasiMaterialPeralatan $materialPeralatan): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::OperasiInputWrite) ||
            $user->hasPermissionTo(PermissionName::OperasiInputWrite),
            403
        );
        abort_unless($user->canAccessUnit($materialPeralatan->unit_id), 403);

        $materialPeralatan->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus item material/peralatan #{$materialPeralatan->id}",
            unit: $materialPeralatan->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item berhasil dihapus.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::OperasiInputView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView) ||
            $user->hasPermissionTo(PermissionName::OperasiInputView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        [$view, $data] = $this->pdfView($unit, $month, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Laporan_Material_Peralatan_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $records = OperasiMaterialPeralatan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $peralatan = $records->where('kategori', 'PERALATAN')->values()->all();
        $material = $records->where('kategori', 'MATERIAL')->values()->all();

        $monthNames = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
            5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
        ];
        $monthName = $monthNames[$month] ?? '';

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        return ['operasi.input.material-peralatan-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'peralatan' => $peralatan,
            'material' => $material,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ]];
    }
}
