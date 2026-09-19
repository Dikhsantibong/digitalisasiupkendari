<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3ApdInventory;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar Inventaris Alat Pelindung Diri (modul K3). Dikelompokkan per grup dan
 * subkategori; jumlah, satuan, lokasi, dan foto diisi per item.
 */
class ApdInventoryController extends Controller
{
    /**
     * Default items in display order: [grup, subkategori, nama, satuan].
     *
     * @var list<array{grup: string, subkategori: string, nama: string, satuan: string}>
     */
    public const DEFAULTS = [
        // I. Peralatan Keselamatan Kerja Utama
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Sarung Tangan', 'nama' => 'Sarung tangan kain (bintik)', 'satuan' => 'pasang'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Sarung Tangan', 'nama' => 'Sarung tangan karet', 'satuan' => 'pasang'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Sarung Tangan', 'nama' => 'Sarung tangan kulit (las)', 'satuan' => 'pasang'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Helm Safety', 'nama' => 'Helm safety putih', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Helm Safety', 'nama' => 'Helm safety merah', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Helm Safety', 'nama' => 'Helm safety hijau', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Helm Safety', 'nama' => 'Helm safety kuning', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Helm Safety', 'nama' => 'Helm safety biru', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Helm Safety', 'nama' => 'Helm safety orange', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Sepatu', 'nama' => 'Safety shoes', 'satuan' => 'pasang'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Sepatu', 'nama' => 'Sepatu karet / boot', 'satuan' => 'pasang'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pelindung Muka & Mata', 'nama' => 'Kacamata bengkel', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pelindung Muka & Mata', 'nama' => 'Kacamata las karbit', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pelindung Muka & Mata', 'nama' => 'Pelindung muka bengkel', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pelindung Muka & Mata', 'nama' => 'Pelindung muka las', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pakaian Pelindung', 'nama' => 'Apron medis', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pakaian Pelindung', 'nama' => 'Apron las', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pakaian Pelindung', 'nama' => 'Rompi biru', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pakaian Pelindung', 'nama' => 'Rompi orange', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pakaian Pelindung', 'nama' => 'Rompi merah', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pelindung Telinga', 'nama' => 'Ear muff / Ear protector', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pelindung Telinga', 'nama' => 'Ear plug', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pelindung Pernafasan', 'nama' => 'Masker kain', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pelindung Pernafasan', 'nama' => 'Masker medis', 'satuan' => 'box'],
        ['grup' => 'Peralatan Keselamatan Kerja Utama', 'subkategori' => 'Pelindung Pernafasan', 'nama' => 'Respirator', 'satuan' => 'buah'],
        // II. Peralatan Keselamatan Kerja Pelengkap
        ['grup' => 'Peralatan Keselamatan Kerja Pelengkap', 'subkategori' => '', 'nama' => 'Tangga biasa', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Pelengkap', 'subkategori' => '', 'nama' => 'Tongkat pentanahan', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Pelengkap', 'subkategori' => 'Lampu Penerangan', 'nama' => 'Senter', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Pelengkap', 'subkategori' => 'Lampu Penerangan', 'nama' => 'Lampu darurat / emergency', 'satuan' => 'buah'],
        ['grup' => 'Peralatan Keselamatan Kerja Pelengkap', 'subkategori' => 'Lampu Penerangan', 'nama' => 'Lampu TR (penerangan tempat kerja)', 'satuan' => 'buah'],
        // IV. APD Tanggap Darurat
        ['grup' => 'APD Tanggap Darurat', 'subkategori' => '', 'nama' => 'Fireman Suit (Jacket and Trouser)', 'satuan' => 'buah'],
        ['grup' => 'APD Tanggap Darurat', 'subkategori' => '', 'nama' => 'Fireman Gloves', 'satuan' => 'pasang'],
        ['grup' => 'APD Tanggap Darurat', 'subkategori' => '', 'nama' => 'Fireman Helmet', 'satuan' => 'buah'],
        ['grup' => 'APD Tanggap Darurat', 'subkategori' => '', 'nama' => 'Fireman Boot', 'satuan' => 'pasang'],
        ['grup' => 'APD Tanggap Darurat', 'subkategori' => '', 'nama' => 'Fireman Hood', 'satuan' => 'buah'],
        ['grup' => 'APD Tanggap Darurat', 'subkategori' => '', 'nama' => 'SCBA', 'satuan' => 'buah'],
        ['grup' => 'APD Tanggap Darurat', 'subkategori' => '', 'nama' => 'Tandu', 'satuan' => 'buah'],
        // V. APD Ketinggian
        ['grup' => 'APD Ketinggian', 'subkategori' => '', 'nama' => 'Hot Stick 20 KV', 'satuan' => 'buah'],
        ['grup' => 'APD Ketinggian', 'subkategori' => '', 'nama' => 'Tangga 40ft (12 mtr)', 'satuan' => 'buah'],
        ['grup' => 'APD Ketinggian', 'subkategori' => '', 'nama' => 'Body Harness', 'satuan' => 'buah'],
        ['grup' => 'APD Ketinggian', 'subkategori' => '', 'nama' => 'Kaos Tangan 20KV', 'satuan' => 'pasang'],
        ['grup' => 'APD Ketinggian', 'subkategori' => '', 'nama' => 'Sepatu 20KV', 'satuan' => 'pasang'],
        ['grup' => 'APD Ketinggian', 'subkategori' => '', 'nama' => 'Tespen 6,3KV-20KV', 'satuan' => 'buah'],
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

        $saved = K3ApdInventory::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get();

        if ($saved->isEmpty()) {
            $rows = collect(self::DEFAULTS)->map(fn (array $d, int $i): array => [
                'id' => null, 'grup' => $d['grup'], 'subkategori' => $d['subkategori'], 'nama' => $d['nama'],
                'jumlah' => 0, 'satuan' => $d['satuan'], 'lokasi' => '', 'keterangan' => '', 'foto' => '', 'sort_order' => $i,
            ])->all();
        } else {
            $rows = $saved->map(fn (K3ApdInventory $r): array => [
                'id' => $r->id, 'grup' => $r->grup, 'subkategori' => $r->subkategori ?? '', 'nama' => $r->nama,
                'jumlah' => $r->jumlah, 'satuan' => $r->satuan ?? '', 'lokasi' => $r->lokasi ?? '',
                'keterangan' => $r->keterangan ?? '', 'foto' => $r->foto ?? '', 'sort_order' => $r->sort_order,
            ])->all();
        }

        $groups = collect(self::DEFAULTS)->pluck('grup')->unique()->values()->all();

        return Inertia::render('k3/input/apd-inventory', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'groups' => $groups,
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
            'rows.*.grup' => ['required', 'string', 'max:150'],
            'rows.*.subkategori' => ['nullable', 'string', 'max:150'],
            'rows.*.nama' => ['required', 'string', 'max:255'],
            'rows.*.jumlah' => ['nullable', 'integer', 'min:0'],
            'rows.*.satuan' => ['nullable', 'string', 'max:50'],
            'rows.*.lokasi' => ['nullable', 'string', 'max:255'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
            'rows.*.foto' => ['nullable', 'string', 'max:1000'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            $existingIds = [];
            foreach ($validated['rows'] ?? [] as $index => $row) {
                $attributes = [
                    'grup' => $row['grup'],
                    'subkategori' => $row['subkategori'] ?? null,
                    'no_urut' => $index + 1,
                    'nama' => $row['nama'],
                    'jumlah' => (int) ($row['jumlah'] ?? 0),
                    'satuan' => $row['satuan'] ?? null,
                    'lokasi' => $row['lokasi'] ?? null,
                    'keterangan' => $row['keterangan'] ?? null,
                    'foto' => $row['foto'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ];

                if (! empty($row['id'])) {
                    $record = K3ApdInventory::query()->where('id', $row['id'])->where('unit_id', $unit->id)->first();
                    if ($record) {
                        $record->update($attributes);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $existingIds[] = K3ApdInventory::create([...$attributes, 'unit_id' => $unit->id, 'year' => $year, 'month' => $month])->id;
            }

            K3ApdInventory::query()
                ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
                ->when($existingIds !== [], fn ($q) => $q->whereNotIn('id', $existingIds))
                ->delete();
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Daftar Inventaris APD {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Daftar Inventaris APD berhasil disimpan.']);
    }
}
