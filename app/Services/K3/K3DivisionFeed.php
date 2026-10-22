<?php

namespace App\Services\K3;

use App\Enums\PermissionName;
use App\Models\HarProgram5s5rEvidence;
use App\Models\HarProgram5s5rItem;
use App\Models\HarUnsafeCondition;
use App\Models\LogistikFormRow;
use App\Models\LogistikJadwalRow;
use App\Models\OperasiProgram5s5rEvidence;
use App\Models\OperasiProgram5s5rItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pdm\PdmFormDocuments;
use App\Support\LogistikJadwal;
use App\Support\OperasiProgram5s5r;
use App\Support\PdmForms\PdmForms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * What the other divisions report that belongs to K3: every Unsafe Action &
 * Unsafe Condition finding (Operasi, Pemeliharaan, Logistik & Gudang) and
 * every 5S5R input (Operasi, Pemeliharaan, PdM, Logistik & Gudang). Read
 * live from each division's own tables — nothing is copied — so the K3
 * module (Kondisi K3, Rekap 5S5R, Laporan K3) always shows the latest save
 * and the divisions' pages keep working as before.
 *
 * @phpstan-type Finding array{sumber: string, sumber_key: string, periode: string|null, kategori: string, temuan: string, kondisi: string|null, lokasi: string|null, tindak_lanjut: string|null, rekomendasi: string|null, status: string|null, keterangan: string|null, foto_sebelum: string|null, foto_sesudah: string|null, url: string|null}
 * @phpstan-type ProgramRow array{program: string, rencana: int, realisasi: int, kinerja: string, catatan: string|null}
 * @phpstan-type Program array{key: string, sumber: string, judul: string, saved: bool, rows: list<ProgramRow>, total: array{rencana: int, realisasi: int, kinerja: string}, temuan: list<string>, eviden: int, url: string|null}
 */
class K3DivisionFeed
{
    /** The 5S5R programs in order, key => label. */
    public const PROGRAMS = [
        'ringkas' => 'Ringkas (Seiri)',
        'rapi' => 'Rapi (Seiton)',
        'resik' => 'Resik (Seiso)',
        'rawat' => 'Rawat (Seiketsu)',
        'rajin' => 'Rajin (Shitsuke)',
    ];

    /** har_unsafe_conditions.source => division label. */
    private const UNSAFE_SOURCES = [
        'operasi' => 'Operasi',
        'har' => 'Pemeliharaan',
    ];

    public function __construct(private readonly PdmFormDocuments $pdmDocuments) {}

    /**
     * The findings of the other divisions for the unit and month, Operasi &
     * Pemeliharaan first, then Logistik & Gudang. `url` opens the source page
     * when the viewer may open it.
     *
     * @return list<Finding>
     */
    public function unsafeFindings(Unit $unit, int $month, int $year, ?User $viewer = null): array
    {
        $period = ['unit_id' => $unit->id, 'month' => $month, 'year' => $year];

        $shared = HarUnsafeCondition::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (HarUnsafeCondition $finding) use ($period, $viewer): array {
                $source = $finding->source !== null && isset(self::UNSAFE_SOURCES[$finding->source]) ? $finding->source : null;
                // Saved before the source column: the shared Operasi & HAR page.
                $route = $source ?? 'operasi';

                return [
                    'sumber' => $source === null ? 'Operasi / Pemeliharaan' : self::UNSAFE_SOURCES[$source],
                    'sumber_key' => $source ?? 'operasi-har',
                    'periode' => $finding->periode,
                    'kategori' => $this->kategori($finding->kategori),
                    'temuan' => $finding->temuan,
                    'kondisi' => $finding->kondisi,
                    'lokasi' => $finding->lokasi,
                    'tindak_lanjut' => $finding->tindak_lanjut,
                    'rekomendasi' => $finding->rekomendasi,
                    'status' => $this->status($finding->keterangan),
                    'keterangan' => null,
                    'foto_sebelum' => $this->photo($finding->foto_sebelum),
                    'foto_sesudah' => $this->photo($finding->foto_sesudah),
                    'url' => $this->canOpenUnsafe($viewer, $route) ? route("{$route}.input.unsafe-condition.index", $period, false) : null,
                ];
            });

        $logistikUrl = $viewer !== null && $this->canOpenLogistik($viewer)
            ? route('logistik.input.form.index', ['form' => 'unsafe', ...$period], false)
            : null;

        $logistik = LogistikFormRow::query()->where('unit_id', $unit->id)->where('form', 'unsafe')
            ->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (LogistikFormRow $row): bool => trim((string) ($row->data['temuan'] ?? '')) !== '')
            ->map(function (LogistikFormRow $row) use ($logistikUrl): array {
                $data = (array) $row->data;
                $keterangan = $this->text($data['keterangan'] ?? null);

                return [
                    'sumber' => 'Logistik & Gudang',
                    'sumber_key' => 'logistik',
                    'periode' => $this->text($data['periode'] ?? null),
                    'kategori' => $this->kategori((string) ($data['kategori'] ?? '')),
                    'temuan' => trim((string) $data['temuan']),
                    'kondisi' => $this->text($data['kondisi'] ?? null),
                    'lokasi' => $this->text($data['lokasi'] ?? null),
                    'tindak_lanjut' => $this->text($data['tindak_lanjut'] ?? null),
                    'rekomendasi' => $this->text($data['rekomendasi'] ?? null),
                    // Logistik keeps a free-text keterangan; Open / Close only when it says so.
                    'status' => $this->status($keterangan),
                    'keterangan' => $keterangan,
                    'foto_sebelum' => $this->photo($data['eviden_sebelum'] ?? null),
                    'foto_sesudah' => $this->photo($data['eviden_sesudah'] ?? null),
                    'url' => $logistikUrl,
                ];
            });

        return $shared->concat($logistik)->values()->all();
    }

    /**
     * Counts of findings by kategori and status.
     *
     * @param  list<array{kategori: string, status: string|null}>  $findings
     * @return array{total: int, unsafe_action: int, unsafe_condition: int, open: int, closed: int}
     */
    public static function summary(array $findings): array
    {
        $rows = collect($findings);

        return [
            'total' => $rows->count(),
            'unsafe_action' => $rows->where('kategori', 'Unsafe Action')->count(),
            'unsafe_condition' => $rows->where('kategori', 'Unsafe Condition')->count(),
            'open' => $rows->where('status', 'Open')->count(),
            'closed' => $rows->where('status', 'Close')->count(),
        ];
    }

    /**
     * The 5S5R inputs of every division for the unit and month: per program
     * (Ringkas … Rajin) the rencana, realisasi and kinerja, with the temuan
     * and the number of eviden photos.
     *
     * - Operasi & Pemeliharaan (Program 5S 5R): rencana = weeks of the month,
     *   realisasi = weeks the program was filled in.
     * - PdM (Laporan Inspeksi Checklist 5S5R): the rencana & realisasi columns.
     * - Logistik & Gudang (Laporan Inspeksi Checklist 5S5R): per item the
     *   target (15 pelaksanaan) against the slots marked D.
     *
     * @return list<Program>
     */
    public function program5s5r(Unit $unit, int $month, int $year, ?User $viewer = null): array
    {
        $period = ['unit_id' => $unit->id, 'month' => $month, 'year' => $year];

        return [
            $this->weeklyProgram('operasi', 'Operasi', 'Program 5S 5R Pengoperasian KIT', OperasiProgram5s5rItem::class, OperasiProgram5s5rEvidence::class, $unit, $month, $year,
                $this->can($viewer, [PermissionName::OperasiInputView, PermissionName::OperasiLapanganProgram5s5r, PermissionName::OperasiLaporanView]) ? route('operasi.input.program-5s5r.index', $period, false) : null),
            $this->weeklyProgram('har', 'Pemeliharaan', 'Program 5S 5R Pemeliharaan KIT', HarProgram5s5rItem::class, HarProgram5s5rEvidence::class, $unit, $month, $year,
                $this->can($viewer, [PermissionName::HarInputView, PermissionName::HarLapanganProgram5s5r, PermissionName::HarLaporanView]) ? route('har.input.program-5s5r.index', $period, false) : null),
            $this->pdmChecklist($unit, $month, $year,
                $this->can($viewer, [PermissionName::PdmInputView, PermissionName::PdmLaporanView]) ? route('pdm.input.forms.index', ['form' => 'checklist-5s5r', ...$period], false) : null),
            $this->logistikChecklist($unit, $month, $year,
                $viewer !== null && $this->canOpenLogistik($viewer) ? route('logistik.input.sheet.index', ['jadwal' => 'inspeksi-5s5r', ...$period], false) : null),
        ];
    }

    /**
     * @param  class-string<OperasiProgram5s5rItem|HarProgram5s5rItem>  $itemModel
     * @param  class-string<OperasiProgram5s5rEvidence|HarProgram5s5rEvidence>  $evidenceModel
     * @return Program
     */
    private function weeklyProgram(string $key, string $sumber, string $judul, string $itemModel, string $evidenceModel, Unit $unit, int $month, int $year, ?string $url): array
    {
        $weeks = OperasiProgram5s5r::weeks($month, $year);
        /** @var Collection<int, OperasiProgram5s5rItem|HarProgram5s5rItem> $items */
        $items = $itemModel::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('minggu')->get();

        $rows = [];
        foreach (array_keys(self::PROGRAMS) as $program) {
            $done = $items->where('program', $program);
            $last = $done->sortByDesc('minggu')->first();
            $rows[] = $this->programRow($program, $weeks, $done->count(), $last === null ? null : collect([
                "Minggu {$last->minggu}",
                $last->kondisi_akhir !== null ? "kondisi akhir {$last->kondisi_akhir}" : null,
                $last->progres !== null ? "progres {$last->progres}%" : null,
            ])->filter()->implode(' · '));
        }

        $temuan = $items->filter(fn (Model $item): bool => trim((string) $item->keterangan) !== '')
            ->map(fn (Model $item): string => 'Minggu '.$item->minggu.' — '.(self::PROGRAMS[$item->program] ?? $item->program).': '.trim((string) $item->keterangan))
            ->values()->all();

        return $this->program($key, $sumber, $judul, $items->isNotEmpty(), $rows, $temuan,
            $evidenceModel::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->count(), $url);
    }

    /**
     * @return Program
     */
    private function pdmChecklist(Unit $unit, int $month, int $year, ?string $url): array
    {
        $form = PdmForms::find('checklist-5s5r');
        $document = $form === null ? null : $this->pdmDocuments->load($form, $unit, $month, $year);
        $saved = (bool) ($document['saved'] ?? false);

        $rows = [];
        $temuan = [];
        foreach (array_keys(self::PROGRAMS) as $program) {
            $list = $saved ? ($document['rows'][$program] ?? []) : [];
            $rencana = (int) array_sum(array_map(fn (array $row): float => is_numeric($row['rencana'] ?? null) ? (float) $row['rencana'] : 0.0, $list));
            $realisasi = (int) array_sum(array_map(fn (array $row): float => is_numeric($row['realisasi'] ?? null) ? (float) $row['realisasi'] : 0.0, $list));
            $rows[] = $this->programRow($program, $rencana, $realisasi, null);

            foreach ($list as $row) {
                $text = trim((string) ($row['temuan'] ?? ''));
                if ($text !== '') {
                    $followUp = trim((string) ($row['tindak_lanjut'] ?? ''));
                    $temuan[] = trim((string) ($row['item'] ?? '')).': '.$text.($followUp !== '' ? " (tindak lanjut: {$followUp})" : '');
                }
            }
        }

        $eviden = $saved
            ? collect($document['images'] ?? [])->flatten(1)->count()
                + collect($document['rows'] ?? [])->flatten(1)->filter(fn ($row): bool => is_array($row) && trim((string) ($row['eviden'] ?? '')) !== '')->count()
            : 0;

        return $this->program('pdm', 'PdM', 'Laporan Inspeksi Checklist 5S5R PdM', $saved, $rows, $temuan, $eviden, $url);
    }

    /**
     * @return Program
     */
    private function logistikChecklist(Unit $unit, int $month, int $year, ?string $url): array
    {
        $slots = count(LogistikJadwal::columns('inspeksi-5s5r', $month, $year));
        $saved = LogistikJadwalRow::query()->where('unit_id', $unit->id)->where('jadwal', 'inspeksi-5s5r')
            ->where('year', $year)->where('month', $month)->orderBy('sort_order')->orderBy('id')->get();

        $rows = [];
        foreach (array_keys(self::PROGRAMS) as $program) {
            $items = $saved->where('section', $program);
            $rencana = (int) $items->sum(fn (LogistikJadwalRow $row): int => $row->target ?? $slots);
            $realisasi = (int) $items->sum(fn (LogistikJadwalRow $row): int => count(array_filter((array) ($row->days ?? []), fn ($code): bool => $code === 'D')));
            $rows[] = $this->programRow($program, $rencana, $realisasi, null);
        }

        $temuan = $saved->filter(fn (LogistikJadwalRow $row): bool => trim((string) $row->keterangan) !== '')
            ->map(fn (LogistikJadwalRow $row): string => trim((string) $row->nama).': '.trim((string) $row->keterangan))
            ->values()->all();

        return $this->program('logistik', 'Logistik & Gudang', 'Laporan Inspeksi Checklist 5S5R Logistik & Gudang', $saved->isNotEmpty(), $rows, $temuan,
            (int) $saved->sum(fn (LogistikJadwalRow $row): int => count((array) ($row->evidence ?? []))), $url);
    }

    /**
     * @return ProgramRow
     */
    private function programRow(string $program, int $rencana, int $realisasi, ?string $catatan): array
    {
        return [
            'program' => self::PROGRAMS[$program],
            'rencana' => $rencana,
            'realisasi' => $realisasi,
            'kinerja' => self::percent($realisasi, $rencana),
            'catatan' => $catatan === '' ? null : $catatan,
        ];
    }

    /**
     * @param  list<ProgramRow>  $rows
     * @param  list<string>  $temuan
     * @return Program
     */
    private function program(string $key, string $sumber, string $judul, bool $saved, array $rows, array $temuan, int $eviden, ?string $url): array
    {
        $rencana = (int) array_sum(array_column($rows, 'rencana'));
        $realisasi = (int) array_sum(array_column($rows, 'realisasi'));

        return [
            'key' => $key,
            'sumber' => $sumber,
            'judul' => $judul,
            'saved' => $saved,
            'rows' => $rows,
            'total' => ['rencana' => $rencana, 'realisasi' => $realisasi, 'kinerja' => self::percent($realisasi, $rencana)],
            'temuan' => $temuan,
            'eviden' => $eviden,
            'url' => $url,
        ];
    }

    public static function percent(int $done, int $of): string
    {
        return $of > 0 ? round($done / $of * 100).'%' : '-';
    }

    /** UNSAFE ACTION / Unsafe Action → Unsafe Action. */
    private function kategori(string $value): string
    {
        $value = strtoupper(trim($value));

        return match (true) {
            str_contains($value, 'ACTION') => 'Unsafe Action',
            str_contains($value, 'CONDITION') => 'Unsafe Condition',
            default => $value === '' ? 'Unsafe Condition' : ucwords(strtolower($value)),
        };
    }

    /** open / close / "Close" / "Selesai" → Open | Close; anything else is unknown. */
    private function status(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return match (true) {
            $value === '' => null,
            str_contains($value, 'close') || str_contains($value, 'selesai') => 'Close',
            str_contains($value, 'open') => 'Open',
            default => null,
        };
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function photo(mixed $path): ?string
    {
        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function canOpenUnsafe(?User $viewer, string $module): bool
    {
        return $module === 'har'
            ? $this->can($viewer, [PermissionName::HarInputView, PermissionName::HarLapanganUnsafeCondition, PermissionName::HarLaporanView])
            : $this->can($viewer, [PermissionName::OperasiInputView, PermissionName::OperasiLapanganUnsafeCondition, PermissionName::OperasiLaporanView]);
    }

    private function canOpenLogistik(User $viewer): bool
    {
        return $this->can($viewer, [PermissionName::LogistikInputView, PermissionName::LogistikLaporanView]);
    }

    /**
     * @param  list<PermissionName>  $permissions
     */
    private function can(?User $viewer, array $permissions): bool
    {
        return $viewer !== null && collect($permissions)->contains(fn (PermissionName $permission): bool => $viewer->hasPermissionTo($permission));
    }
}
