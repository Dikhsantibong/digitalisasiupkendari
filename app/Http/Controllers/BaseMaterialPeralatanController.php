<?php

namespace App\Http\Controllers;

use App\Enums\ActivityEvent;
use App\Enums\MaterialKelengkapan;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Models\OperasiMaterialPeralatan;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Laporan Material dan Peralatan" of a module (Operasi, Pemeliharaan): the
 * monthly list of peralatan & material with their stock, prepared by whoever
 * manages it, and each item's kelengkapan checked by the field staff —
 * Lengkap (kondisi normal), Tidak Lengkap or Tidak Ada with the uraian of the
 * abnormal condition. A module only adds a model + table (same columns), a
 * subclass (model, page, permissions) and routes {module}.input.material-peralatan.*.
 */
abstract class BaseMaterialPeralatanController extends Controller
{
    use AuthorizesFieldInput;

    protected const DEFAULT_MATERIALS = [
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

    protected const DEFAULT_PERALATAN = [
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

    public function __construct(protected readonly ActivityLogger $activityLogger) {}

    /**
     * @return class-string<OperasiMaterialPeralatan>
     */
    abstract protected function modelClass(): string;

    /** The Inertia page, e.g. operasi/input/material-peralatan/index. */
    abstract protected function page(): string;

    /** Opens the page and the PDF. */
    abstract protected function canView(User $user): bool;

    /** Prepares the list & stock (and may check too). */
    abstract protected function canManage(User $user): bool;

    /** The field staff who only check each item's kelengkapan. */
    abstract protected function canCheckField(User $user): bool;

    /** "operasi" / "pemeliharaan", for the activity log. */
    abstract protected function moduleName(): string;

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        [$units, $unit, $month, $year] = $this->target($request);

        $records = $this->records($unit, $month, $year);

        if ($records->isEmpty()) {
            $sort = 1;
            foreach (self::DEFAULT_PERALATAN as $per) {
                $this->query()->create([
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
                $this->query()->create([
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

            $records = $this->records($unit, $month, $year);
        }

        $records->load('checker:id,name');
        $present = fn (OperasiMaterialPeralatan $r): array => [
            ...$r->makeHidden('checker')->toArray(),
            'kelengkapan' => $r->kelengkapan?->value,
            'checked_by_name' => $r->checker?->name,
            'checked_at' => $r->checked_at?->translatedFormat('d M Y H:i'),
        ];

        $peralatan = $records->where('kategori', 'PERALATAN')->map($present)->values()->all();
        $material = $records->where('kategori', 'MATERIAL')->map($present)->values()->all();

        $totalNilaiStok = $records->sum(fn ($r) => (float) $r->stok_akhir * (float) $r->harga_satuan);
        $lowStockCount = $records->filter(fn ($r) => (float) $r->rop > 0 && (float) $r->stok_akhir <= (float) $r->rop)->count();

        $canManage = $this->canManage($user);
        $canCheck = $this->canCheck($user);

        return Inertia::render($this->page(), [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'peralatan' => $peralatan,
            'material' => $material,
            'summary' => [
                'total_peralatan' => count($peralatan),
                'total_material' => count($material),
                'total_nilai_stok' => $totalNilaiStok,
                'low_stock_count' => $lowStockCount,
                'checked_count' => $records->whereNotNull('kelengkapan')->count(),
                'tidak_normal_count' => $records->filter(fn (OperasiMaterialPeralatan $r): bool => $r->kelengkapan !== null && ! $r->kelengkapan->isNormal())->count(),
            ],
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'kelengkapan' => MaterialKelengkapan::options(),
            ],
            // Whoever manages prepares the list & stock; the field staff check each item.
            'can_manage' => $canManage,
            'can_check' => $canCheck,
            'can_write' => $canManage || $canCheck,
        ]);
    }

    /**
     * Whoever manages saves the whole sheet: the items, their stock and
     * (optionally) their kelengkapan.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);

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
            ...$this->checkRules(),
        ], $this->checkMessages());

        $existing = $this->query()
            ->where('unit_id', $unit->id)
            ->whereIn('id', collect($validated['items'])->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all())
            ->get()
            ->keyBy('id');

        foreach ($validated['items'] as $index => $item) {
            $stokAwal = (float) ($item['stok_awal'] ?? 0);
            $masuk = (float) ($item['masuk'] ?? 0);
            $keluar = (float) ($item['keluar'] ?? 0);

            $values = [
                'kategori' => $item['kategori'],
                'kode_material' => $item['kode_material'] ?? null,
                'stok_code' => $item['stok_code'] ?? null,
                'nama_item' => $item['nama_item'],
                'stok_awal' => $stokAwal,
                'masuk' => $masuk,
                'keluar' => $keluar,
                'stok_akhir' => max(0, $stokAwal + $masuk - $keluar),
                'satuan' => $item['satuan'] ?? null,
                'harga_satuan' => (float) ($item['harga_satuan'] ?? 0),
                'pemakaian_rata_rata' => (float) ($item['pemakaian_rata_rata'] ?? 0),
                'safety_stock' => (float) ($item['safety_stock'] ?? 0),
                'ilt' => (float) ($item['ilt'] ?? 0),
                'rop' => (float) ($item['rop'] ?? 0),
                'roq' => (float) ($item['roq'] ?? 0),
                'sort_order' => (int) ($item['sort_order'] ?? $index + 1),
            ];

            if (! empty($item['id'])) {
                $record = $existing->get((int) $item['id']);
                if ($record === null) {
                    continue;
                }

                $record->fill($values);
                // A payload without the kelengkapan keys (an older page) leaves the check as it is.
                if (array_key_exists('kelengkapan', $item)) {
                    $this->applyCheck($record, $item, $user);
                }
                $record->save();
            } else {
                $record = $this->query()->make([
                    ...$values,
                    'unit_id' => $unit->id,
                    'year' => (int) $validated['year'],
                    'month' => (int) $validated['month'],
                    'input_by' => $user->id,
                ]);
                $this->applyCheck($record, $item, $user);
                $record->save();
            }
        }

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan laporan material & peralatan {$this->moduleName()} {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan material & peralatan berhasil disimpan.']);

        return back();
    }

    /**
     * The field staff's check of the listed items: only the kelengkapan and
     * the uraian of an abnormal condition.
     */
    public function check(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canCheck($user), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'items' => ['required', 'array', 'max:500'],
            'items.*.id' => ['required', 'integer'],
            ...$this->checkRules(),
        ], $this->checkMessages());

        $records = $this->query()
            ->where('unit_id', $unit->id)
            ->where('year', (int) $validated['year'])
            ->where('month', (int) $validated['month'])
            ->get()
            ->keyBy('id');

        foreach ($validated['items'] as $index => $item) {
            if (! $records->has((int) $item['id'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.id" => 'Item ini tidak ada di unit & bulan tersebut. Muat ulang halaman.',
                ]);
            }
        }

        $changed = 0;
        foreach ($validated['items'] as $item) {
            $record = $records->get((int) $item['id']);
            $this->applyCheck($record, $item, $user);
            if ($record->isDirty()) {
                $record->save();
                $changed++;
            }
        }

        $abnormal = $records->filter(fn (OperasiMaterialPeralatan $r): bool => $r->kelengkapan !== null && ! $r->kelengkapan->isNormal())->count();

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Mengecek kelengkapan material & peralatan {$this->moduleName()} {$unit->name} {$validated['month']}/{$validated['year']}: {$changed} item diperbarui, {$abnormal} tidak normal",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pengecekan tersimpan ({$changed} item diperbarui)."]);

        return back();
    }

    public function destroy(Request $request, int $materialPeralatan): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);

        $record = $this->query()->findOrFail($materialPeralatan);
        abort_unless($user->canAccessUnit($record->unit_id), 403);

        $record->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus item material/peralatan {$this->moduleName()} #{$record->id}",
            unit: $record->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item berhasil dihapus.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        abort_unless($this->canView($request->user()), 403);

        [, $unit, $month, $year] = $this->target($request);

        [$view, $data] = $this->pdfView($unit, $month, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Laporan_Material_Peralatan_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the module's Laporan Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $records = $this->records($unit, $month, $year);

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

    /**
     * Checks each item's kelengkapan: whoever manages, and the field staff.
     */
    protected function canCheck(User $user): bool
    {
        return $this->canManage($user) || $this->canCheckField($user);
    }

    /**
     * @return Builder<OperasiMaterialPeralatan>
     */
    protected function query(): Builder
    {
        return ($this->modelClass())::query();
    }

    /**
     * @return Collection<int, OperasiMaterialPeralatan>
     */
    protected function records(Unit $unit, int $month, int $year): Collection
    {
        return $this->query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    protected function target(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        return [$units, $unit, $month, $year];
    }

    /**
     * Lengkap means normal (no uraian); tidak lengkap / tidak ada needs the uraian.
     *
     * @return array<string, list<mixed>>
     */
    private function checkRules(): array
    {
        return [
            'items.*.kelengkapan' => ['nullable', Rule::enum(MaterialKelengkapan::class)],
            'items.*.uraian_kondisi' => [
                'nullable', 'string', 'max:2000',
                'required_if:items.*.kelengkapan,'.MaterialKelengkapan::TidakLengkap->value.','.MaterialKelengkapan::TidakAda->value,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function checkMessages(): array
    {
        return [
            'items.*.uraian_kondisi.required_if' => 'Uraian kondisi wajib diisi bila item tidak lengkap / tidak ada.',
        ];
    }

    /**
     * Sets the item's kelengkapan & uraian (cleared when lengkap or unchecked)
     * and who checked it, when the check changes.
     *
     * @param  array<string, mixed>  $item
     */
    private function applyCheck(OperasiMaterialPeralatan $record, array $item, User $user): void
    {
        $kelengkapan = MaterialKelengkapan::tryFrom((string) ($item['kelengkapan'] ?? ''));
        $uraian = $kelengkapan === null || $kelengkapan->isNormal() ? null : (trim((string) ($item['uraian_kondisi'] ?? '')) ?: null);

        $record->kelengkapan = $kelengkapan;
        $record->uraian_kondisi = $uraian;

        if ($record->isDirty(['kelengkapan', 'uraian_kondisi'])) {
            $record->checked_by = $kelengkapan === null ? null : $user->id;
            $record->checked_at = $kelengkapan === null ? null : now();
        }
    }
}
