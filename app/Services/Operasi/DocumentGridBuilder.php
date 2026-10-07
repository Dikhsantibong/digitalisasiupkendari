<?php

namespace App\Services\Operasi;

use App\Enums\BeritaAcaraType;

/**
 * Turns a generated document into a spreadsheet grid (cells + merges + column
 * widths) for the Excel edit mode, and renders a saved grid back to an HTML
 * table for PDF export. Cell text is pre-formatted so the spreadsheet, the
 * downloaded .xlsx, and the PDF all read identically.
 *
 * Grid shape: array{name, cols, col_widths, merges: list<array{0..3:int}>,
 *   rows: list<list<array{t:string, b?:bool, a?:string}>>}
 */
class DocumentGridBuilder
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function forBeritaAcara(BeritaAcaraType $type, array $data): array
    {
        return match ($type) {
            BeritaAcaraType::Hsd, BeritaAcaraType::Mfo => $this->fuelGrid($data),
            BeritaAcaraType::Pelumas => $this->lubricantGrid($data),
            BeritaAcaraType::Feeder => $this->feederGrid($data),
            BeritaAcaraType::Flowmeter => $this->flowmeterGrid($data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fuelGrid(array $data): array
    {
        $cols = 2;
        $rows = [];
        $merges = [];
        $mergeFull = function (int $r) use (&$merges, $cols): void {
            $merges[] = [$r, 0, $r, $cols - 1];
        };

        $push = function (array $row) use (&$rows): int {
            $rows[] = $row;

            return count($rows) - 1;
        };

        $mergeFull($push([$this->c($data['document']['title'], true, 'c')]));
        $mergeFull($push([$this->c('NO : '.$data['document']['number'], true, 'l')]));
        $mergeFull($push([$this->c($this->narrative($data, 'Bahan Bakar Minyak '.$data['fuel_label']))]));

        $fmt = fn ($v): string => number_format((float) $v, 2, ',', '.');

        $push([$this->c('1. Persediaan Awal'), $this->c($fmt($data['persediaan_awal']).' Liter', false, 'r')]);
        $push([$this->c('2. Penerimaan BBM ('.$data['fuel_label'].')'), $this->c('')]);
        $push([$this->c('   '.$data['penerimaan_range'].' pukul 10.00'), $this->c($fmt($data['penerimaan_total']).' Liter', false, 'r')]);
        $push([$this->c('A. Jumlah Stock BBM '.$data['fuel_label'], true), $this->c($fmt($data['jumlah_stock']).' Liter', true, 'r')]);
        $push([$this->c('3. Pemakaian Mesin PLN'), $this->c('')]);
        foreach ($data['pemakaian'] as $item) {
            $push([$this->c('   '.$item['mesin']), $this->c($fmt($item['liter']).' Liter', false, 'r')]);
        }
        $push([$this->c('B. Jumlah Pemakaian (3)', true), $this->c($fmt($data['pemakaian_total']).' Liter', true, 'r')]);
        $push([$this->c('C. Jumlah Pengiriman', true), $this->c($fmt($data['pengiriman']).' Liter', true, 'r')]);
        $push([$this->c('D. Persediaan menurut Administrasi (A-B-C)', true), $this->c($fmt($data['administrasi']).' Liter', true, 'r')]);
        $push([$this->c('Jumlah Persediaan menurut Fisik:'), $this->c('')]);
        foreach ($data['fisik'] as $item) {
            $push([$this->c('   '.$item['tangki']), $this->c($fmt($item['liter']).' Liter', false, 'r')]);
        }
        $push([$this->c('E. Jumlah Persediaan menurut Fisik', true), $this->c($fmt($data['fisik_total']).' Liter', true, 'r')]);
        $catatanText = 'Catatan: * Selisih disebabkan karena: '.(! empty($data['catatan']) ? $data['catatan'] : '......................................');
        $mergeFull($push([$this->c($catatanText)]));
        $push([$this->c('', false, 'c', true), $this->c($data['print_place_date'], false, 'c', true)]);
        $push([
            $this->c('Menyetujui, '.($data['signers']['manajer_title'] ?? 'Manajer'), false, 'c', true),
            $this->c('Membuat, '.($data['signers']['tl_title'] ?? 'TL. Operasi'), false, 'c', true),
        ]);
        $push([$this->c('', false, 'c', true, 22), $this->c('', false, 'c', true, 22)]);
        $push([$this->c('', false, 'c', true, 22), $this->c('', false, 'c', true, 22)]);
        $push([$this->c('', false, 'c', true, 22), $this->c('', false, 'c', true, 22)]);
        $push([
            $this->c($data['signers']['manajer'] ?? '(………………)', true, 'c', true),
            $this->c($data['signers']['tl_operasi'] ?? '(………………)', true, 'c', true),
        ]);

        return [
            'name' => $data['document']['title'],
            'cols' => $cols,
            'col_widths' => [430, 160],
            'merges' => $merges,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function lubricantGrid(array $data): array
    {
        $cols = 9;
        $rows = [];
        $merges = [];
        $mergeFull = function (int $r) use (&$merges, $cols): void {
            $merges[] = [$r, 0, $r, $cols - 1];
        };
        $push = function (array $row) use (&$rows): int {
            $rows[] = $row;

            return count($rows) - 1;
        };
        $fmt = fn ($v): string => number_format((float) $v, 2, ',', '.');

        $mergeFull($push([$this->c($data['document']['title'], true, 'c')]));
        $mergeFull($push([$this->c('NO : '.$data['document']['number'], true, 'l')]));
        $mergeFull($push([$this->c($this->narrative($data, 'fisik pelumas'))]));

        $headers = ['Jenis Pelumas', 'Persediaan Awal', 'Penerimaan', 'Stock', 'Pemakaian Sendiri', 'Pengiriman', 'Persediaan Administrasi', 'Stock Fisik', 'Selisih Fisik-Adm'];
        $push(array_map(fn (string $h): array => $this->c($h, true, 'c'), $headers));

        $totals = array_fill_keys(['awal', 'penerimaan', 'stock', 'pemakaian', 'pengiriman', 'administrasi', 'fisik_liter', 'selisih'], 0.0);
        foreach ($data['rows'] as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (float) $row[$key];
            }
            $push([
                $this->c($row['jenis']),
                $this->c($fmt($row['awal']), false, 'r'),
                $this->c($fmt($row['penerimaan']), false, 'r'),
                $this->c($fmt($row['stock']), false, 'r'),
                $this->c($fmt($row['pemakaian']), false, 'r'),
                $this->c($fmt($row['pengiriman']), false, 'r'),
                $this->c($fmt($row['administrasi']), false, 'r'),
                $this->c($fmt($row['fisik_liter']), false, 'r'),
                $this->c($fmt($row['selisih']), false, 'r'),
            ]);
        }
        $push([
            $this->c('JUMLAH TOTAL', true),
            $this->c($fmt($totals['awal']), true, 'r'),
            $this->c($fmt($totals['penerimaan']), true, 'r'),
            $this->c($fmt($totals['stock']), true, 'r'),
            $this->c($fmt($totals['pemakaian']), true, 'r'),
            $this->c($fmt($totals['pengiriman']), true, 'r'),
            $this->c($fmt($totals['administrasi']), true, 'r'),
            $this->c($fmt($totals['fisik_liter']), true, 'r'),
            $this->c($fmt($totals['selisih']), true, 'r'),
        ]);
        $mergeFull($push([$this->c('Catatan: * Selisih disebabkan karena: ......................................')]));
        $push([$this->c(''), $this->c(''), $this->c(''), $this->c(''), $this->c(''), $this->c(''), $this->c($data['print_place_date'], false, 'c', true), $this->c(''), $this->c('')]);
        $push([
            $this->c('Menyetujui, '.($data['signers']['manajer_title'] ?? 'Manajer'), false, 'c', true),
            $this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c('', false, 'c', true),
            $this->c('Membuat, '.($data['signers']['tl_title'] ?? 'TL. Operasi'), false, 'c', true),
            $this->c('', false, 'c', true), $this->c('', false, 'c', true),
        ]);
        for ($s = 0; $s < 3; $s++) {
            $push(array_fill(0, $cols, $this->c('', false, 'c', true, 22)));
        }
        $push([
            $this->c($data['signers']['manajer'] ?? '(………………)', true, 'c', true),
            $this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c('', false, 'c', true),
            $this->c($data['signers']['tl_operasi'] ?? '(………………)', true, 'c', true),
            $this->c('', false, 'c', true), $this->c('', false, 'c', true),
        ]);

        return [
            'name' => 'BA Opname Pelumas',
            'cols' => $cols,
            'col_widths' => [150, 80, 70, 70, 90, 70, 100, 80, 90],
            'merges' => $merges,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function feederGrid(array $data): array
    {
        $cols = 8;
        $rows = [];
        $merges = [];
        $mergeFull = function (int $r) use (&$merges, $cols): void {
            $merges[] = [$r, 0, $r, $cols - 1];
        };
        $push = function (array $row) use (&$rows): int {
            $rows[] = $row;

            return count($rows) - 1;
        };
        $fmt = fn ($v): string => ((float) $v) == 0.0 ? '-' : number_format((float) $v, 2, ',', '.');

        $mergeFull($push([$this->c($data['document']['title'], true, 'c')]));
        $mergeFull($push([$this->c('NO : '.$data['document']['number'], true, 'l')]));
        $mergeFull($push([$this->c($this->narrative($data, 'kWh meter tersalur feeder'))]));

        $h1Index = $push([
            $this->c('NO', true, 'c'),
            $this->c('KWH FEEDER', true, 'c'),
            $this->c('TERSALUR KWH', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('KETERANGAN', true, 'c'),
        ]);
        $merges[] = [$h1Index, 2, $h1Index, 6];

        $h2Index = $push([
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('AWAL', true, 'c'),
            $this->c('AKHIR', true, 'c'),
            $this->c('F. KALI', true, 'c'),
            $this->c('HASIL', true, 'c'),
            $this->c('', true, 'c'),
        ]);
        $merges[] = [$h1Index, 0, $h2Index, 0];
        $merges[] = [$h1Index, 1, $h2Index, 1];
        $merges[] = [$h1Index, 7, $h2Index, 7];

        $feederRows = $data['feeder_rows'] ?? [];
        $totalExport = 0.0;
        $totalImport = 0.0;

        foreach ($feederRows as $idx => $item) {
            $no = (string) ($idx + 1);
            $feederName = (string) ($item['feeder_name'] ?? ('Feeder '.($idx + 1)));
            $exp = $item['export'] ?? ['awal' => 0, 'akhir' => 0, 'f_kali' => 1, 'hasil' => 0];
            $imp = $item['import'] ?? ['awal' => 0, 'akhir' => 0, 'f_kali' => 1, 'hasil' => 0];
            $ket = (string) ($item['keterangan'] ?? '');

            $totalExport += (float) ($exp['hasil'] ?? 0);
            $totalImport += (float) ($imp['hasil'] ?? 0);

            $r1 = $push([
                $this->c($no, false, 'c'),
                $this->c($feederName, true, 'l'),
                $this->c('Export', false, 'c'),
                $this->c(number_format((float) ($exp['awal'] ?? 0), 2, ',', '.'), false, 'r'),
                $this->c(number_format((float) ($exp['akhir'] ?? 0), 2, ',', '.'), false, 'r'),
                $this->c(number_format((float) ($exp['f_kali'] ?? 1), 2, ',', '.'), false, 'r'),
                $this->c($fmt($exp['hasil'] ?? 0), true, 'r'),
                $this->c($ket, false, 'l'),
            ]);

            $r2 = $push([
                $this->c('', false, 'c'),
                $this->c('', false, 'l'),
                $this->c('Import', false, 'c'),
                $this->c(number_format((float) ($imp['awal'] ?? 0), 2, ',', '.'), false, 'r'),
                $this->c(number_format((float) ($imp['akhir'] ?? 0), 2, ',', '.'), false, 'r'),
                $this->c(number_format((float) ($imp['f_kali'] ?? 1), 2, ',', '.'), false, 'r'),
                $this->c($fmt($imp['hasil'] ?? 0), true, 'r'),
                $this->c('', false, 'l'),
            ]);

            $merges[] = [$r1, 0, $r2, 0];
            $merges[] = [$r1, 1, $r2, 1];
            $merges[] = [$r1, 7, $r2, 7];
        }

        $sumExp = $push([
            $this->c('JUMLAH EXPORT', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c($fmt($totalExport), true, 'r'),
            $this->c('', false, 'l'),
        ]);
        $merges[] = [$sumExp, 0, $sumExp, 5];

        $sumImp = $push([
            $this->c('JUMLAH IMPORT', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c($fmt($totalImport), true, 'r'),
            $this->c('', false, 'l'),
        ]);
        $merges[] = [$sumImp, 0, $sumImp, 5];

        $totUnit = $push([
            $this->c('TOTAL UNIT PLTD', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c('', true, 'c'),
            $this->c(number_format($totalExport - $totalImport, 2, ',', '.'), true, 'r'),
            $this->c('', false, 'l'),
        ]);
        $merges[] = [$totUnit, 0, $totUnit, 5];

        $catatanText = 'Catatan: * '.(! empty($data['catatan']) ? $data['catatan'] : '......................................');
        $mergeFull($push([$this->c($catatanText)]));

        $push([$this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c('', false, 'c', true), $this->c($data['print_place_date'], false, 'c', true), $this->c('', false, 'c', true)]);
        $push([
            $this->c('Menyetujui, '.($data['signers']['manajer_title'] ?? 'Manajer'), false, 'c', true),
            $this->c('', false, 'c', true),
            $this->c('', false, 'c', true),
            $this->c('', false, 'c', true),
            $this->c('', false, 'c', true),
            $this->c('Membuat, '.($data['signers']['tl_title'] ?? 'TL. Operasi'), false, 'c', true),
            $this->c('', false, 'c', true),
            $this->c('', false, 'c', true),
        ]);
        for ($s = 0; $s < 3; $s++) {
            $push(array_fill(0, $cols, $this->c('', false, 'c', true, 22)));
        }
        $push([
            $this->c($data['signers']['manajer'] ?? '(………………)', true, 'c', true),
            $this->c('', false, 'c', true),
            $this->c('', false, 'c', true),
            $this->c('', false, 'c', true),
            $this->c('', false, 'c', true),
            $this->c($data['signers']['tl_operasi'] ?? '(………………)', true, 'c', true),
            $this->c('', false, 'c', true),
            $this->c('', false, 'c', true),
        ]);

        return [
            'name' => 'BA kWh Feeder',
            'cols' => $cols,
            'col_widths' => [40, 150, 60, 80, 80, 80, 90, 160],
            'merges' => $merges,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function flowmeterGrid(array $data): array
    {
        $machines = $data['flowmeter_machines'] ?? [];
        $rowsData = $data['flowmeter_rows'] ?? [];
        $totalsData = $data['flowmeter_totals'] ?? [];
        $numMachines = count($machines);
        $fuelName = $data['fuel_name'] ?? 'HSD';

        // Columns: TGL (1) + Machines (3 each) + TOTAL UNIT (1) + ADM (1) + Real (1) + SELISIH (1)
        $cols = 1 + ($numMachines * 3) + 4;
        $colWidths = [45];
        foreach ($machines as $m) {
            $colWidths[] = 85; // Awal
            $colWidths[] = 85; // Akhir
            $colWidths[] = 75; // Pemakaian
        }
        $colWidths[] = 85; // TOTAL UNIT
        $colWidths[] = 85; // ADM
        $colWidths[] = 85; // Real
        $colWidths[] = 85; // SELISIH

        $rows = [];
        $merges = [];
        $mergeFull = function (int $r) use (&$merges, $cols): void {
            $merges[] = [$r, 0, $r, $cols - 1];
        };
        $push = function (array $row) use (&$rows): int {
            $rows[] = $row;

            return count($rows) - 1;
        };
        $fmt = fn ($v): string => ((float) $v) == 0.0 ? '-' : number_format((float) $v, 2, ',', '.');

        // Row 0: Title
        $mergeFull($push([$this->c('STAND FLOW METER '.strtoupper($fuelName), true, 'c')]));
        // Row 1: Subtitle
        $mergeFull($push([$this->c('BULAN '.strtoupper($data['period']['label'] ?? ''), true, 'c')]));
        // Row 2: Empty
        $mergeFull($push([$this->c('')]));

        // Machine Header Rows
        // Row 3: Machine Names
        $r3 = [$this->c('TGL', true, 'c')];
        foreach ($machines as $m) {
            $r3[] = $this->c($m['name'], true, 'c');
            $r3[] = $this->c('', true, 'c');
            $r3[] = $this->c('', true, 'c');
        }
        $unitName = $data['unit']['name'] ?? 'UNIT';
        $r3[] = $this->c('TOTAL '.$unitName, true, 'c');
        $r3[] = $this->c('ADM', true, 'c');
        $r3[] = $this->c('Real', true, 'c');
        $r3[] = $this->c('SELISIH', true, 'c');
        $r3Idx = $push($r3);

        // Row 4: Stand Awal Bulan Lalu
        $r4 = [$this->c('', true, 'c')];
        foreach ($machines as $m) {
            $r4[] = $this->c('STAND AWAL BLN LALU', false, 'l');
            $r4[] = $this->c('', false, 'l');
            $r4[] = $this->c($fmt($m['stand_awal_bln_lalu'] ?? 0), true, 'r');
        }
        $r4[] = $this->c('', true, 'c');
        $r4[] = $this->c('', true, 'c');
        $r4[] = $this->c('', true, 'c');
        $r4[] = $this->c('', true, 'c');
        $r4Idx = $push($r4);

        // Row 5: Faktor Koreksi
        $r5 = [$this->c('', true, 'c')];
        foreach ($machines as $m) {
            $r5[] = $this->c('FAKTOR KOREKSI', false, 'l');
            $r5[] = $this->c('', false, 'l');
            $r5[] = $this->c(number_format((float) ($m['faktor_koreksi'] ?? 1), 7, ',', '.'), true, 'r');
        }
        $r5[] = $this->c('', true, 'c');
        $r5[] = $this->c('', true, 'c');
        $r5[] = $this->c('', true, 'c');
        $r5[] = $this->c('', true, 'c');
        $r5Idx = $push($r5);

        // Row 6: Faktor Kali
        $r6 = [$this->c('', true, 'c')];
        foreach ($machines as $m) {
            $r6[] = $this->c('FAKTOR KALI', false, 'l');
            $r6[] = $this->c('', false, 'l');
            $r6[] = $this->c(number_format((float) ($m['faktor_kali'] ?? 1), 1, ',', '.'), true, 'r');
        }
        $r6[] = $this->c('', true, 'c');
        $r6[] = $this->c('', true, 'c');
        $r6[] = $this->c('', true, 'c');
        $r6Idx = $push($r6);

        // Row 7: STAND FM / PEMAKAIAN
        $r7 = [$this->c('', true, 'c')];
        foreach ($machines as $m) {
            $r7[] = $this->c('STAND FM', true, 'c');
            $r7[] = $this->c('', true, 'c');
            $r7[] = $this->c('PEMAKAIAN', true, 'c');
        }
        $r7[] = $this->c('', true, 'c');
        $r7[] = $this->c('', true, 'c');
        $r7[] = $this->c('', true, 'c');
        $r7Idx = $push($r7);

        // Row 8: AWAL / AKHIR / PEMAKAIAN
        $r8 = [$this->c('', true, 'c')];
        foreach ($machines as $m) {
            $r8[] = $this->c('AWAL', true, 'c');
            $r8[] = $this->c('AKHIR', true, 'c');
            $r8[] = $this->c('', true, 'c');
        }
        $r8[] = $this->c('', true, 'c');
        $r8[] = $this->c('', true, 'c');
        $r8[] = $this->c('', true, 'c');
        $r8Idx = $push($r8);

        // Merges for header:
        $merges[] = [$r3Idx, 0, $r8Idx, 0];
        $merges[] = [$r3Idx, $cols - 4, $r8Idx, $cols - 4];
        $merges[] = [$r3Idx, $cols - 3, $r8Idx, $cols - 3];
        $merges[] = [$r3Idx, $cols - 2, $r8Idx, $cols - 2];
        $merges[] = [$r3Idx, $cols - 1, $r8Idx, $cols - 1];

        for ($i = 0; $i < $numMachines; $i++) {
            $baseCol = 1 + ($i * 3);
            $merges[] = [$r3Idx, $baseCol, $r3Idx, $baseCol + 2];
            $merges[] = [$r4Idx, $baseCol, $r4Idx, $baseCol + 1];
            $merges[] = [$r5Idx, $baseCol, $r5Idx, $baseCol + 1];
            $merges[] = [$r6Idx, $baseCol, $r6Idx, $baseCol + 1];
            $merges[] = [$r7Idx, $baseCol, $r7Idx, $baseCol + 1];
            $merges[] = [$r7Idx, $baseCol + 2, $r8Idx, $baseCol + 2];
        }

        // Daily Data Rows
        foreach ($rowsData as $row) {
            $tgl = (string) ($row['tgl'] ?? '');
            $r = [$this->c($tgl, false, 'c')];
            $dayMachs = $row['machines'] ?? [];
            $totalUnitPemakaian = 0.0;

            foreach ($machines as $m) {
                $mId = $m['id'];
                $mDay = $dayMachs[$mId] ?? ['awal' => 0, 'akhir' => 0, 'pemakaian' => 0];
                $r[] = $this->c($fmt($mDay['awal'] ?? 0), false, 'r');
                $r[] = $this->c($fmt($mDay['akhir'] ?? 0), false, 'r');
                $pemakaian = (float) ($mDay['pemakaian'] ?? 0);
                $r[] = $this->c($fmt($pemakaian), true, 'r');
                $totalUnitPemakaian += $pemakaian;
            }

            $adm = (float) ($row['adm'] ?? $totalUnitPemakaian);
            $real = (float) ($row['real'] ?? 0);
            $selisih = (float) ($row['selisih'] ?? ($real - $adm));

            $r[] = $this->c($fmt($totalUnitPemakaian), false, 'r');
            $r[] = $this->c($fmt($adm), false, 'r');
            $r[] = $this->c($fmt($real), false, 'r');
            $r[] = $this->c($selisih < 0 ? '('.number_format(abs($selisih), 2, ',', '.').')' : $fmt($selisih), true, 'r');

            $push($r);
        }

        // Row TOT
        $totMachs = $totalsData['machines'] ?? [];
        $rTot = [$this->c('TOT', true, 'c')];
        $totalAllPemakaian = 0.0;

        foreach ($machines as $m) {
            $mId = $m['id'];
            $mTot = $totMachs[$mId] ?? ['awal' => 0, 'akhir' => 0, 'pemakaian' => 0];
            $rTot[] = $this->c($fmt($mTot['awal'] ?? 0), true, 'r');
            $rTot[] = $this->c($fmt($mTot['akhir'] ?? 0), true, 'r');
            $pTot = (float) ($mTot['pemakaian'] ?? 0);
            $rTot[] = $this->c($fmt($pTot), true, 'r');
            $totalAllPemakaian += $pTot;
        }

        $totAdm = (float) ($totalsData['adm'] ?? $totalAllPemakaian);
        $totReal = (float) ($totalsData['real'] ?? 0);
        $totSelisih = (float) ($totalsData['selisih'] ?? ($totReal - $totAdm));

        $rTot[] = $this->c($fmt($totalAllPemakaian), true, 'r');
        $rTot[] = $this->c($fmt($totAdm), true, 'r');
        $rTot[] = $this->c($fmt($totReal), true, 'r');
        $rTot[] = $this->c($totSelisih < 0 ? '('.number_format(abs($totSelisih), 2, ',', '.').')' : $fmt($totSelisih), true, 'r');
        $push($rTot);

        return [
            'name' => 'Stand Flowmeter '.$fuelName,
            'cols' => $cols,
            'col_widths' => $colWidths,
            'merges' => $merges,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data  the MonthlyEngineReport payload
     * @return array<string, mixed>
     */
    public function forMonthlyReport(array $data): array
    {
        $usesMfo = collect($data['rows'])->contains(fn (array $r): bool => $r['pemakaian_mfo'] !== null);

        $headers = ['Tgl', 'kWh Produksi', 'kWh PS', 'kWh Netto', 'Pakai HSD (L)'];
        if ($usesMfo) {
            $headers[] = 'Pakai MFO (L)';
        }
        $headers = array_merge($headers, ['Pelumas (L)', 'BP Pagi', 'BP Malam']);
        $cols = count($headers);

        $rows = [];
        $merges = [];
        $push = function (array $row) use (&$rows): int {
            $rows[] = $row;

            return count($rows) - 1;
        };
        $merges[] = [0, 0, 0, $cols - 1];
        $push([$this->c(($data['unit']['service_unit'] ?? 'UNIT LAYANAN'), true, 'c')]);
        $merges[] = [1, 0, 1, $cols - 1];
        $push([$this->c('LAPORAN OPERASI PEMBANGKIT — '.($data['engine']['name'] ?? '').' · '.$data['period']['label'], true, 'c')]);
        $push(array_map(fn (string $h): array => $this->c($h, true, 'c'), $headers));

        $fmt = fn ($v): string => $v === null ? '' : number_format((float) $v, 2, ',', '.');

        foreach ($data['rows'] as $row) {
            $cells = [
                $this->c((string) $row['day'], false, 'c'),
                $this->c($fmt($row['kwh_produksi']), false, 'r'),
                $this->c($fmt($row['kwh_pakai_sendiri']), false, 'r'),
                $this->c($fmt($row['kwh_netto']), false, 'r'),
                $this->c($fmt($row['pemakaian_hsd']), false, 'r'),
            ];
            if ($usesMfo) {
                $cells[] = $this->c($fmt($row['pemakaian_mfo']), false, 'r');
            }
            $cells[] = $this->c($fmt($row['pemakaian_pelumas_liter']), false, 'r');
            $cells[] = $this->c($fmt($row['beban_puncak_pagi_kw']), false, 'r');
            $cells[] = $this->c($fmt($row['beban_puncak_malam_kw']), false, 'r');
            $push($cells);
        }

        foreach (['periode_1' => 'PERIODE I', 'periode_2' => 'PERIODE II', 'periode_3' => 'PERIODE III', 'total' => 'TOTAL'] as $key => $label) {
            $s = $data['summary'][$key] ?? null;
            if ($s === null) {
                continue;
            }
            $cells = [
                $this->c($label, true, 'l'),
                $this->c($fmt($s['kwh_produksi']), true, 'r'),
                $this->c($fmt($s['kwh_pakai_sendiri']), true, 'r'),
                $this->c($fmt($s['kwh_netto']), true, 'r'),
                $this->c($fmt($s['pemakaian_hsd']), true, 'r'),
            ];
            if ($usesMfo) {
                $cells[] = $this->c($fmt($s['pemakaian_mfo']), true, 'r');
            }
            $cells[] = $this->c($fmt($s['pemakaian_pelumas_liter']), true, 'r');
            $cells[] = $this->c('', true);
            $cells[] = $this->c('', true);
            $push($cells);
        }

        return [
            'name' => 'Laporan Operasi',
            'cols' => $cols,
            'col_widths' => array_fill(0, $cols, 90),
            'merges' => $merges,
            'rows' => $rows,
        ];
    }

    /**
     * Render a grid (as saved from the spreadsheet editor) to an HTML table for
     * PDF export. Honours merges, bold and alignment.
     *
     * @param  array<string, mixed>  $grid
     */
    public function gridToHtml(array $grid): string
    {
        $rows = $grid['rows'] ?? [];
        $merges = $grid['merges'] ?? [];

        // Map every covered (non-origin) cell so it is skipped, and record the
        // span for each merge origin.
        $covered = [];
        $span = [];
        foreach ($merges as [$r1, $c1, $r2, $c2]) {
            $span["$r1:$c1"] = [$r2 - $r1 + 1, $c2 - $c1 + 1];
            for ($r = $r1; $r <= $r2; $r++) {
                for ($c = $c1; $c <= $c2; $c++) {
                    if ($r !== $r1 || $c !== $c1) {
                        $covered["$r:$c"] = true;
                    }
                }
            }
        }

        $html = '<table style="width:100%; border-collapse:collapse; font-size:11px;">';
        foreach ($rows as $r => $cells) {
            $html .= '<tr>';
            foreach ($cells as $c => $cell) {
                if (isset($covered["$r:$c"])) {
                    continue;
                }
                $attrs = '';
                if (isset($span["$r:$c"])) {
                    [$rowspan, $colspan] = $span["$r:$c"];
                    if ($rowspan > 1) {
                        $attrs .= ' rowspan="'.$rowspan.'"';
                    }
                    if ($colspan > 1) {
                        $attrs .= ' colspan="'.$colspan.'"';
                    }
                }
                $align = match ($cell['a'] ?? 'l') {
                    'c' => 'center',
                    'r' => 'right',
                    default => 'left',
                };
                $weight = ($cell['b'] ?? false) ? 'font-weight:bold;' : '';
                $border = ($cell['noborder'] ?? false) ? 'border:none;' : 'border:1px solid #000;';
                $height = isset($cell['height']) ? 'height:'.$cell['height'].'px;' : '';
                $style = "{$border} padding:2px 4px; text-align:{$align}; {$weight} {$height}";
                $text = (string) ($cell['t'] ?? '');
                $content = $text !== '' ? e($text) : '&nbsp;';
                $html .= '<td'.$attrs.' style="'.$style.'">'.$content.'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</table>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function narrative(array $data, string $subject): string
    {
        $n = $data['narrative'];

        return "Pada Hari ini {$n['hari']} Tanggal {$n['tanggal_terbilang']} Bulan {$n['bulan']} "
            ."Tahun {$n['tahun_terbilang']} ({$n['tanggal_penuh']}) kami yang bertanda tangan di bawah ini "
            ."menyatakan bahwa telah diadakan pemeriksaan {$subject} pada {$data['unit']['name']} "
            .'dengan hasil sebagai berikut:';
    }

    /**
     * @return array{t: string, b?: bool, a?: string, noborder?: bool, height?: int}
     */
    private function c(string $text, bool $bold = false, string $align = 'l', bool $noborder = false, ?int $height = null): array
    {
        $cell = ['t' => $text];
        if ($bold) {
            $cell['b'] = true;
        }
        if ($align !== 'l') {
            $cell['a'] = $align;
        }
        if ($noborder) {
            $cell['noborder'] = true;
        }
        if ($height !== null) {
            $cell['height'] = $height;
        }

        return $cell;
    }
}
