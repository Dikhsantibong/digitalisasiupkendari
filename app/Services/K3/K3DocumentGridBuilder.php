<?php

namespace App\Services\K3;

/**
 * Turns the computed K3 monthly report into a spreadsheet grid (cells + merges +
 * column widths) for the Excel edit mode. The letterhead (logo + kop) is added
 * at export time. Mirrors {@see HarDocumentGridBuilder}.
 *
 * Grid shape: array{name, cols, col_widths, merges: list<array{0..3:int}>,
 *   rows: list<list<array{t:string, b?:bool, a?:string}>>}
 */
class K3DocumentGridBuilder
{
    private const COLS = 6;

    /**
     * @param  array<string, mixed>  $data  the K3DocumentBuilder payload
     * @return array<string, mixed>
     */
    public function build(array $data): array
    {
        /** @var array<string, mixed> $report */
        $report = $data['report'];
        /** @var array<string, string> $numbers */
        $numbers = $data['document']['numbers'] ?? [];

        $rows = [];
        $merges = [];
        $push = function (array $row) use (&$rows): int {
            $rows[] = $row;

            return count($rows) - 1;
        };
        $full = function (int $r) use (&$merges): void {
            $merges[] = [$r, 0, $r, self::COLS - 1];
        };
        $title = function (string $text, ?string $number) use ($push, $full): void {
            $full($push([$this->c($number ? "{$text}  ({$number})" : $text, true, 'l')]));
        };

        $full($push([$this->c((string) ($report['unit']['service_unit'] ?? 'UNIT LAYANAN'), true, 'c')]));
        $full($push([$this->c('LAPORAN KINERJA K3 & KEAMANAN — '.$report['unit']['name'].' · '.$report['period']['label'], true, 'c')]));
        $push([$this->c('')]);

        // 1. Time Frame.
        $title('1. Time Frame Kinerja K3', $numbers['time_frame'] ?? null);
        if ($report['time_frame'] !== []) {
            $push([$this->c('Kegiatan', true), $this->c('PIC', true), $this->c('Rencana', true, 'c'), $this->c('Realisasi', true, 'c')]);
            foreach ($report['time_frame'] as $tf) {
                $push([$this->c((string) $tf['activity']), $this->c((string) ($tf['pic'] ?? '')), $this->c((string) $tf['plan'], false, 'c'), $this->c((string) $tf['real'], false, 'c')]);
            }
        }
        $push([$this->c('')]);

        // 2. Kecelakaan.
        $title('2. Laporan Kecelakaan (PAK/PAHK)', $numbers['accidents'] ?? null);
        if ($report['accidents']['nihil']) {
            $push([$this->c('NIHIL — tidak ada kejadian pada periode ini.', true, 'l')]);
        } else {
            $push([$this->c('Kategori', true), $this->c('Lokasi', true), $this->c('Luka Ringan', true, 'c'), $this->c('Luka Berat', true, 'c'), $this->c('Meninggal', true, 'c')]);
            foreach ($report['accidents']['rows'] as $a) {
                $push([$this->c((string) $a['category']), $this->c((string) ($a['lokasi'] ?? '')), $this->c((string) $a['luka_ringan'], false, 'c'), $this->c((string) $a['luka_berat'], false, 'c'), $this->c((string) $a['meninggal'], false, 'c')]);
            }
        }
        $push([$this->c('')]);

        // 3. APAR.
        $title('3. Inspeksi APAR/APAB', $numbers['apar'] ?? null);
        if ($report['apar'] !== []) {
            $push([$this->c('RFID', true), $this->c('Lokasi', true), $this->c('Kondisi', true), $this->c('Exp Date', true), $this->c('Status', true)]);
            foreach ($report['apar'] as $ap) {
                $push([$this->c((string) ($ap['rfid'] ?? '')), $this->c((string) ($ap['location'] ?? '')), $this->c((string) ($ap['kondisi'] ?? '')), $this->c((string) ($ap['exp_date'] ?? '')), $this->c((string) $ap['status'])]);
            }
        }
        $push([$this->c('')]);

        // 4. Emergency.
        $title('4. Kesiapan Fasilitas Darurat', $numbers['emergency_tools'] ?? null);
        if ($report['emergency'] !== []) {
            $push([$this->c('Fasilitas', true), $this->c('Total', true, 'c'), $this->c('Ready', true, 'c'), $this->c('Not Ready', true, 'c'), $this->c('% Kesiapan', true, 'c')]);
            foreach ($report['emergency'] as $em) {
                $push([$this->c((string) $em['name']), $this->c((string) $em['total'], false, 'c'), $this->c((string) $em['ready'], false, 'c'), $this->c((string) $em['not_ready'], false, 'c'), $this->c((string) $em['percent'], false, 'c')]);
            }
        }
        $push([$this->c('')]);

        // 5. Patroli.
        $title('5. Rekap Patroli Keamanan (Kumulatif)', null);
        if ($report['patrol'] !== []) {
            $push([$this->c('Lokasi', true), $this->c('Total Scan', true, 'c')]);
            foreach ($report['patrol'] as $pt) {
                $push([$this->c((string) $pt['location']), $this->c((string) $pt['total'], false, 'c')]);
            }
        }
        $push([$this->c('')]);

        // 6. Sertifikat.
        $title('6. Sertifikasi Peralatan', $numbers['certificates'] ?? null);
        if ($report['certificates'] !== []) {
            $push([$this->c('Jenis', true), $this->c('Lokasi', true), $this->c('Uji Ulang', true), $this->c('Status', true)]);
            foreach ($report['certificates'] as $cert) {
                $push([$this->c((string) $cert['jenis']), $this->c((string) ($cert['lokasi'] ?? '')), $this->c((string) ($cert['uji_ulang_tanggal'] ?? '')), $this->c((string) $cert['status'])]);
            }
        }

        return [
            'name' => 'Laporan K3',
            'cols' => self::COLS,
            'col_widths' => [220, 120, 100, 100, 100, 100],
            'merges' => $merges,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{t: string, b?: bool, a?: string}
     */
    private function c(string $text, bool $bold = false, string $align = 'l'): array
    {
        $cell = ['t' => $text];
        if ($bold) {
            $cell['b'] = true;
        }
        if ($align !== 'l') {
            $cell['a'] = $align;
        }

        return $cell;
    }
}
