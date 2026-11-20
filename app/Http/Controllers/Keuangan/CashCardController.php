<?php

namespace App\Http\Controllers\Keuangan;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesKeuangan;
use App\Http\Controllers\Controller;
use App\Models\CashCardEntry;
use App\Models\CashCardReport;
use App\Models\ServiceUnit;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\Keuangan\CashCardDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Laporan Realisasi Cash Card of a UL (user, 2026-11-20 — Administrasi &
 * Keuangan, kept by the JOF Administrasi Umum): one table per UL & month
 * (tanggal, period, no. entry, uraian, supplier from the Master Supplier,
 * penerimaan, pengeluaran, running saldo) with photo attachments per row; PDF
 * of the laporan, and per row (or for every row at once) the Bukti
 * Penerimaan Kas Kecil with its Lampiran — the row's photos plus the
 * supplier's KTP, e-mail and NPWP.
 */
class CashCardController extends Controller
{
    use HandlesKeuangan;

    /** Photos per row (nota, barang, …). */
    private const MAX_PHOTOS = 8;

    public function __construct(
        private readonly CashCardDocument $documents,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): Response
    {
        [$uls, $ul, $month, $year] = $this->keuanganTarget($request);
        $report = $this->report($ul, $month, $year);
        $user = $request->user();

        return Inertia::render('keuangan/cash-card/index', [
            'ul' => ['id' => $ul->id, 'name' => $ul->name],
            'filters' => ['ul_id' => $ul->id, 'month' => $month, 'year' => $year],
            'options' => $this->keuanganOptions($uls),
            'saldo_awal' => (float) ($report?->saldo_awal ?? $this->previousSaldo($ul, $month, $year)),
            'has_saved' => $report !== null,
            'entries' => $report === null ? [] : $report->entries()->get()->map(fn (CashCardEntry $entry): array => [
                'id' => $entry->id,
                'tanggal' => $entry->tanggal?->toDateString(),
                'period' => $entry->period,
                'no_entry' => $entry->no_entry,
                'uraian' => $entry->uraian,
                'supplier_id' => $entry->supplier_id,
                'penerimaan' => (float) $entry->penerimaan,
                'pengeluaran' => (float) $entry->pengeluaran,
                'kode_perkiraan' => $entry->kode_perkiraan,
                'kode_sap' => $entry->kode_sap,
                'cost_centre' => $entry->cost_centre,
                'photos' => collect($entry->photos ?? [])->map(fn (string $path): array => ['path' => $path, 'url' => CashCardDocument::url($path)])->filter(fn (array $photo): bool => $photo['url'] !== null)->values()->all(),
            ])->all(),
            'suppliers' => Supplier::query()->where('service_unit_id', $ul->id)->orderBy('name')->get()
                ->map(fn (Supplier $supplier): array => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'email' => $supplier->email,
                    'npwp' => $supplier->npwp,
                    'has_ktp' => $supplier->ktp_path !== null,
                    'is_active' => $supplier->is_active,
                ])->all(),
            'can_write' => $user->hasPermissionTo(PermissionName::KeuanganWrite) && ! $user->isReadOnly(),
            'can_manage_suppliers' => $user->hasPermissionTo(PermissionName::KeuanganSupplierManage) && ! $user->isReadOnly(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::KeuanganWrite) && ! $user->isReadOnly(), 403);

        $validated = $request->validate([
            'ul_id' => ['required', 'integer', 'exists:service_units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'saldo_awal' => ['nullable', 'numeric', 'between:-999999999999,999999999999'],
            'rows' => ['present', 'array', 'max:500'],
            'rows.*.tanggal' => ['nullable', 'date'],
            'rows.*.period' => ['nullable', 'integer', 'between:0,9999'],
            'rows.*.no_entry' => ['nullable', 'integer', 'between:0,9999'],
            'rows.*.uraian' => ['nullable', 'string', 'max:500'],
            'rows.*.supplier_id' => ['nullable', 'integer'],
            'rows.*.penerimaan' => ['nullable', 'numeric', 'between:0,999999999999'],
            'rows.*.pengeluaran' => ['nullable', 'numeric', 'between:0,999999999999'],
            'rows.*.kode_perkiraan' => ['nullable', 'string', 'max:60'],
            'rows.*.kode_sap' => ['nullable', 'string', 'max:60'],
            'rows.*.cost_centre' => ['nullable', 'string', 'max:60'],
            'rows.*.photos_keep' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'rows.*.photos_keep.*' => ['string', 'max:255'],
            'rows.*.files' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'rows.*.files.*' => ['image', 'max:5120'],
        ], [], ['rows.*.files.*' => 'foto lampiran']);

        $ul = ServiceUnit::query()->findOrFail($validated['ul_id']);
        abort_unless($this->canSeeUl($user, $ul->id), 403);
        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $folder = "keuangan/cash-card/{$ul->id}/{$year}-".sprintf('%02d', $month);
        $suppliers = Supplier::query()->where('service_unit_id', $ul->id)->pluck('id')->flip();

        $report = CashCardReport::query()->firstOrNew(['service_unit_id' => $ul->id, 'month' => $month, 'year' => $year]);
        $before = $report->exists ? collect($report->entries()->pluck('photos'))->flatten()->filter()->all() : [];
        $kept = [];

        DB::transaction(function () use ($validated, $request, $report, $user, $folder, $suppliers, &$kept): void {
            if (! $report->exists) {
                $report->created_by = $user->id;
            }
            $report->fill(['saldo_awal' => (float) ($validated['saldo_awal'] ?? 0), 'updated_by' => $user->id])->save();
            $report->entries()->delete();

            foreach (array_values($validated['rows']) as $index => $row) {
                $photos = collect($row['photos_keep'] ?? [])
                    ->filter(fn (string $path): bool => str_starts_with($path, $folder.'/'))
                    ->values()->all();
                foreach ((array) $request->file("rows.{$index}.files", []) as $file) {
                    if ($file instanceof UploadedFile && count($photos) < self::MAX_PHOTOS) {
                        $photos[] = $file->store($folder, 'public');
                    }
                }

                $penerimaan = (float) ($row['penerimaan'] ?? 0);
                $pengeluaran = (float) ($row['pengeluaran'] ?? 0);
                $uraian = trim((string) ($row['uraian'] ?? ''));
                // A blank row (nothing typed, no amount, no photo) is not saved.
                if ($uraian === '' && $penerimaan == 0 && $pengeluaran == 0 && $photos === [] && empty($row['supplier_id'])) {
                    continue;
                }

                $supplierId = isset($row['supplier_id']) && isset($suppliers[(int) $row['supplier_id']]) ? (int) $row['supplier_id'] : null;
                $report->entries()->create([
                    'sort_order' => $index,
                    'tanggal' => $row['tanggal'] ?? null,
                    'period' => $row['period'] ?? null,
                    'no_entry' => $row['no_entry'] ?? null,
                    'uraian' => $uraian === '' ? null : $uraian,
                    'supplier_id' => $supplierId,
                    'penerimaan' => $penerimaan,
                    'pengeluaran' => $pengeluaran,
                    'kode_perkiraan' => $row['kode_perkiraan'] ?? null,
                    'kode_sap' => $row['kode_sap'] ?? null,
                    'cost_centre' => $row['cost_centre'] ?? null,
                    'photos' => $photos,
                ]);
                $kept = [...$kept, ...$photos];
            }
        });

        // Photos removed from every row are deleted from the disk.
        $removed = array_diff($before, $kept);
        if ($removed !== []) {
            Storage::disk('public')->delete(array_values($removed));
        }

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Laporan Realisasi Cash Card {$ul->name} {$month}/{$year}", $report);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan Realisasi Cash Card disimpan.']);

        return back();
    }

    /**
     * PDF of the Laporan Realisasi Cash Card.
     */
    public function pdf(Request $request): HttpResponse
    {
        [, $ul, $month, $year] = $this->keuanganTarget($request);
        $report = $this->report($ul, $month, $year) ?? new CashCardReport(['service_unit_id' => $ul->id, 'month' => $month, 'year' => $year, 'saldo_awal' => 0]);
        $report->setRelation('serviceUnit', $ul);

        $pdf = Pdf::loadView('keuangan.cash-card-pdf', $this->documents->laporan($report))->setPaper('a4', 'portrait');

        return $this->respond($request, $pdf->output(), Str::slug("laporan realisasi cash card {$ul->name} {$month} {$year}").'.pdf');
    }

    /**
     * Bukti Penerimaan Kas Kecil + Lampiran of one row (?entry=) or of every row.
     */
    public function bukti(Request $request): HttpResponse
    {
        [, $ul, $month, $year] = $this->keuanganTarget($request);
        $report = $this->report($ul, $month, $year);
        abort_if($report === null, 404, 'Laporan bulan ini belum disimpan.');
        $report->setRelation('serviceUnit', $ul);

        $rows = $this->documents->ledger($report)['rows'];
        if ($request->filled('entry')) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['entry']->id === $request->integer('entry')));
            abort_if($rows === [], 404);
        }

        $pdf = Pdf::loadView('keuangan.bukti-pdf', $this->documents->bukti($report, $rows))->setPaper('a4', 'portrait');
        $file = $request->filled('entry')
            ? Str::slug("bukti kas kecil {$ul->name} {$month} {$year} no {$rows[0]['no']}").'.pdf'
            : Str::slug("bukti kas kecil {$ul->name} {$month} {$year}").'.pdf';

        return $this->respond($request, $pdf->output(), $file);
    }

    private function report(ServiceUnit $ul, int $month, int $year): ?CashCardReport
    {
        return CashCardReport::query()->where('service_unit_id', $ul->id)->where('month', $month)->where('year', $year)->first();
    }

    /**
     * A new month starts from the previous month's saldo akhir.
     */
    private function previousSaldo(ServiceUnit $ul, int $month, int $year): float
    {
        $previous = $month === 1 ? [12, $year - 1] : [$month - 1, $year];
        $report = $this->report($ul, $previous[0], $previous[1]);

        return $report === null ? 0.0 : $this->documents->ledger($report)['saldo_akhir'];
    }

    private function respond(Request $request, string $pdf, string $file): HttpResponse
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$file.'"',
        ]);
    }
}
