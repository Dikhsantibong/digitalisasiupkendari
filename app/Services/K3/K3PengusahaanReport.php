<?php

namespace App\Services\K3;

use App\Models\K3PengusahaanAlatTanggapDarurat;
use App\Models\K3PengusahaanAparApab;
use App\Models\K3PengusahaanApat;
use App\Models\K3PengusahaanApelKeamanan;
use App\Models\K3PengusahaanBukuTamu;
use App\Models\K3PengusahaanCertificate;
use App\Models\K3PengusahaanEmergencyFacility;
use App\Models\K3PengusahaanEvaluasiPengujian;
use App\Models\K3PengusahaanFireAlarm;
use App\Models\K3PengusahaanHydrant;
use App\Models\K3PengusahaanInspeksiRambu;
use App\Models\K3PengusahaanInspeksiTempatKerja;
use App\Models\K3PengusahaanInventarisApd;
use App\Models\K3PengusahaanJamKerja;
use App\Models\K3PengusahaanJamKerjaBulanan;
use App\Models\K3PengusahaanKecelakaanInstalasi;
use App\Models\K3PengusahaanKecelakaanMasyarakat;
use App\Models\K3PengusahaanLaporanCctv;
use App\Models\K3PengusahaanMetodePengujian;
use App\Models\K3PengusahaanMetodePengujianMeta;
use App\Models\K3PengusahaanPatrolSecurity;
use App\Models\K3PengusahaanPemeriksaanP3k;
use App\Models\K3PengusahaanTimeFrame;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The content of the Laporan Pengusahaan Pembangkit (K3 & KAM): every Akses 2 —
 * Pengusahaan K3 input and formulir of a unit and month (pages/pengusahaan/k3),
 * each as a generic table section the report body (Blade) and its spreadsheet
 * grid both render. A section is portrait when narrow, landscape when wide.
 *
 * Section shape: key, title, number (document no.), orientation, meta
 * (label => value lines above the table), head & rows (rows of cells
 * {t: text, c: colspan, r: rowspan, a: l|c|r, b: bold, s: section row, red: day
 * off, fill: marked cell}), filled (whether the form was filled) and note.
 */
class K3PengusahaanReport
{
    private int $days = 31;

    /** @var list<int> */
    private array $redDays = [];

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(Unit $unit, int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1);
        $this->days = (int) $start->daysInMonth;
        $this->redDays = collect(range(1, $this->days))
            ->filter(fn (int $d): bool => Carbon::create($year, $month, $d)->isWeekend())
            ->values()
            ->all();

        $sections = [
            $this->timeFrame($unit, $month, $year),
            $this->kecelakaan('kecelakaan-instalasi', 'Laporan Bulanan Kecelakaan Instalasi', K3PengusahaanKecelakaanInstalasi::class, $unit, $month, $year),
            $this->kecelakaan('kecelakaan-masyarakat', 'Laporan Bulanan Kecelakaan Masyarakat Umum', K3PengusahaanKecelakaanMasyarakat::class, $unit, $month, $year),
            $this->jamKerja($unit, $month, $year),
            $this->jamKerjaBulanan($unit, $month, $year),
            $this->aparApab($unit, $month, $year),
            $this->apat($unit, $month, $year),
            $this->hydrant($unit, $month, $year),
            $this->fireAlarm($unit, $month, $year),
            $this->alatTanggapDarurat($unit, $month, $year),
            $this->emergencyFacility($unit, $month, $year),
            $this->pemeriksaanP3k($unit, $month, $year),
            $this->inventarisApd($unit, $month, $year),
            $this->inspeksiRambu($unit, $month, $year),
            $this->inspeksiTempatKerja($unit, $month, $year),
            $this->patrolSecurity($unit, $month, $year),
            $this->apelKeamanan($unit, $month, $year),
            $this->laporanCctv($unit, $month, $year),
            $this->bukuTamu($unit, $month, $year),
            $this->certificates($unit),
            $this->metodePengujian($unit, $month, $year),
            $this->evaluasiPengujian($unit, $year),
        ];

        // Numbered in reading order: the PDF prints the portrait pages before the landscape ones.
        $sections = [
            ...array_filter($sections, fn (array $s): bool => $s['orientation'] === 'portrait'),
            ...array_filter($sections, fn (array $s): bool => $s['orientation'] === 'landscape'),
        ];
        foreach ($sections as $index => &$section) {
            $section['no'] = $index + 2;
        }
        unset($section);

        return [$this->recap($sections), ...$sections];
    }

    /**
     * Section 1: which pengusahaan forms were filled this month.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function recap(array $sections): array
    {
        $rows = array_map(fn (array $s): array => [
            $this->cell((string) $s['no'], 'c'),
            $this->cell($s['title']),
            $this->cell($s['number'], 'c'),
            $this->cell($s['filled'] ? 'Terisi' : 'Belum diisi', 'c', true),
            $this->cell($s['filled'] ? (string) count(array_filter($s['rows'], fn (array $r): bool => ! ($r[0]['s'] ?? false))).' baris' : '-', 'c'),
        ], $sections);

        return [
            'key' => 'rekap',
            'no' => 1,
            'title' => 'Rekapitulasi Pengisian Input & Formulir Pengusahaan K3 & KAM',
            'number' => 'FMKD-314-10.3.3',
            'orientation' => 'portrait',
            'meta' => [],
            'head' => [[$this->h('No', w: '6%'), $this->h('Input / Formulir Pengusahaan'), $this->h('No. Dokumen', w: '20%'), $this->h('Status', w: '14%'), $this->h('Isi', w: '12%')]],
            'rows' => $rows,
            'filled' => true,
            'note' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function timeFrame(Unit $unit, int $month, int $year): array
    {
        $items = K3PengusahaanTimeFrame::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('no_urut')->get();

        $rows = [];
        foreach ($items as $i => $item) {
            foreach (['rencana' => 'RENC', 'realisasi' => 'REAL'] as $field => $label) {
                $marked = array_map('intval', (array) ($item->{$field} ?? []));
                $row = $field === 'rencana'
                    ? [$this->cell((string) ($i + 1), 'c', r: 2), $this->cell((string) $item->uraian_pelaporan, r: 2), $this->cell((string) $item->pic, 'c', r: 2)]
                    : [];
                $row[] = $this->cell($label, 'c', true);
                foreach (range(1, $this->days) as $d) {
                    $row[] = ['t' => in_array($d, $marked, true) ? '✓' : '', 'a' => 'c', 'fill' => in_array($d, $marked, true) ? $field : null, 'red' => in_array($d, $this->redDays, true)];
                }
                if ($field === 'rencana') {
                    $row[] = $this->cell((string) ($item->keterangan ?? ''), r: 2);
                }
                $rows[] = $row;
            }
        }

        return $this->section('time-frame', 'Time Frame Kinerja K3 dan Keamanan', 'FMZ-08.2.3.29', 'landscape', [
            [$this->h('No', r: 2, w: '3%'), $this->h('Uraian Pelaporan', r: 2, w: '18%'), $this->h('PIC', r: 2, w: '7%'), $this->h('Status', r: 2, w: '4%'), $this->h('Tanggal', c: $this->days), $this->h('Keterangan', r: 2, w: '10%')],
            $this->dayHeader(),
        ], $rows, $items->isNotEmpty());
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, mixed>
     */
    private function kecelakaan(string $key, string $title, string $model, Unit $unit, int $month, int $year): array
    {
        $record = $this->record($model, $unit, $month, $year);
        $rows = [];

        if ($record?->getAttribute('is_nihil')) {
            $rows[] = [['t' => 'NIHIL — tidak ada kejadian kecelakaan pada bulan ini.', 'c' => 9, 'a' => 'c', 'b' => true]];
        } else {
            foreach ($record?->items ?? [] as $i => $item) {
                $rows[] = [
                    $this->cell((string) ($i + 1), 'c'),
                    $this->cell($this->date($item->tanggal_kejadian), 'c'),
                    $this->cell((string) $item->fungsi),
                    $this->cell((string) $item->lokasi_kejadian),
                    $this->cell((string) $item->luka_ringan, 'c'),
                    $this->cell((string) $item->luka_berat, 'c'),
                    $this->cell((string) $item->meninggal, 'c'),
                    $this->cell($item->kerugian_material ? 'Rp '.number_format((float) $item->kerugian_material, 0, ',', '.') : '-', 'r'),
                    $this->cell((string) $item->keterangan),
                ];
            }
        }

        return $this->section($key, $title, (string) ($record?->getAttribute('nomor_keputusan') ?: 'Kepdir PLN'), 'landscape', [
            [$this->h('No', r: 2, w: '4%'), $this->h('Tanggal Kejadian', r: 2, w: '10%'), $this->h('Fungsi', r: 2), $this->h('Lokasi Kejadian', r: 2), $this->h('Korban', c: 3), $this->h('Kerugian Material', r: 2, w: '12%'), $this->h('Keterangan', r: 2)],
            [$this->h('Luka Ringan', w: '7%'), $this->h('Luka Berat', w: '7%'), $this->h('Meninggal', w: '7%')],
        ], $rows, $record !== null, $record?->getAttribute('catatan'));
    }

    /** @return array<string, mixed> */
    private function jamKerja(Unit $unit, int $month, int $year): array
    {
        $r = K3PengusahaanJamKerja::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->first();
        $v = fn (string $f): string => $r ? $this->num($r->getAttribute($f)) : '-';

        $rows = [
            $this->sectionRow('A. Jumlah Karyawan', 5),
            $this->line('Karyawan Tetap', $v('karyawan_tetap').' orang'),
            $this->line('Karyawan Tetap (Shift)', $v('karyawan_tetap_shift').' orang'),
            $this->line('Karyawan Tidak Tetap', $v('karyawan_tidak_tetap').' orang'),
            $this->line('Karyawan Tidak Tetap (Shift)', $v('karyawan_tidak_tetap_shift').' orang'),
            $this->sectionRow('B. Hari Kerja, Jam Kerja & Lembur per Orang per Bulan', 5),
        ];
        foreach (['tetap' => 'Tetap', 'tetap_shift' => 'Tetap (Shift)', 'tidak_tetap' => 'Tidak Tetap', 'tidak_tetap_shift' => 'Tidak Tetap (Shift)'] as $suffix => $label) {
            $rows[] = [$this->cell($label), $this->cell($v("hari_{$suffix}").' hari', 'c'), $this->cell($v("jam_{$suffix}").' jam', 'c'), $this->cell($v("lembur_{$suffix}").' jam', 'c'), $this->cell('')];
        }
        $rows[] = $this->sectionRow('C. Absensi', 5);
        foreach (['cuti' => 'Cuti', 'ijin' => 'Ijin', 'sakit' => 'Sakit'] as $prefix => $label) {
            $rows[] = [$this->cell($label), $this->cell($v("{$prefix}_orang").' orang', 'c'), $this->cell($v("{$prefix}_hari").' hari', 'c'), $this->cell($v("{$prefix}_jam").' jam', 'c'), $this->cell('')];
        }
        $rows[] = $this->sectionRow('D. Jumlah', 5);
        $rows[] = $this->line('Total jam kerja per orang', $v('total_jam_kerja_orang').' jam');
        $rows[] = $this->line('Total lembur', $v('total_lembur').' jam');
        $rows[] = $this->line('Total absensi', $v('total_absensi_jam').' jam');
        $rows[] = $this->line('Total jam kerja seluruh karyawan', $v('total_jam_kerja_seluruh').' jam', true);

        return $this->section('jam-kerja', 'Laporan Hari & Jam Kerja Karyawan', 'SMT-FM-AK3-02.02', 'portrait', [
            [$this->h('Uraian', w: '40%'), $this->h('Hari / Orang'), $this->h('Jam Kerja'), $this->h('Lembur / Jam'), $this->h('Ket.', w: '10%')],
        ], $rows, $r !== null);
    }

    /** @return array<string, mixed> */
    private function jamKerjaBulanan(Unit $unit, int $month, int $year): array
    {
        $r = K3PengusahaanJamKerjaBulanan::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->first();
        $v = fn (string $f): string => $r ? $this->num($r->getAttribute($f)) : '-';
        $lines = [
            'Jam kerja kumulatif s/d bulan lalu' => 'jam_kerja_komulatif_bulan_lalu',
            'Karyawan tetap' => 'karyawan_tetap',
            'Karyawan tetap (shift)' => 'karyawan_tetap_shift',
            'Karyawan tidak tetap' => 'karyawan_tidak_tetap',
            'Karyawan tidak tetap (shift)' => 'karyawan_tidak_tetap_shift',
            'Jumlah karyawan' => 'jumlah_karyawan',
            'Hari kerja' => 'hari_kerja',
            'Jam kerja standar' => 'jam_kerja_standart',
            'Jam kerja standar karyawan' => 'jam_kerja_standart_karyawan',
            'Jam kerja lembur karyawan' => 'jam_kerja_lembur_karyawan',
            'Jam kerja seluruh karyawan' => 'jam_kerja_seluruh_karyawan',
            'Jam absensi karyawan' => 'jam_absensi_karyawan',
            'Jam kerja realisasi karyawan' => 'jam_kerja_realisasi_karyawan',
            'Jam kerja kumulatif s/d bulan ini' => 'jam_kerja_komulatif_bulan_ini',
        ];
        $rows = [];
        $no = 1;
        foreach ($lines as $label => $field) {
            $rows[] = [$this->cell((string) $no++, 'c'), $this->cell($label), $this->cell($v($field), 'r', $field === 'jam_kerja_komulatif_bulan_ini')];
        }

        return $this->section('jam-kerja-bulanan', 'Laporan Bulanan Jam Kerja Karyawan', (string) ($r?->no_dokumen ?: 'FMZ-08.4.4.11'), 'portrait', [
            [$this->h('No', w: '8%'), $this->h('Uraian'), $this->h('Jumlah', w: '25%')],
        ], $rows, $r !== null, $r?->catatan);
    }

    /** @return array<string, mixed> */
    private function aparApab(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanAparApab::class, $unit, $month, $year);
        $rows = collect($record?->items ?? [])->values()->map(fn ($it, int $i): array => [
            $this->cell((string) ($it->no_urut ?: $i + 1), 'c'),
            $this->cell((string) $it->no_rfid, 'c'),
            $this->cell((string) $it->lokasi),
            $this->cell($this->date($it->tgl_periksa), 'c'),
            $this->cell((string) $it->merk_apar, 'c'),
            $this->cell((string) $it->jenis_apar, 'c'),
            $this->cell($this->num($it->berat_kg), 'c'),
            $this->cell((string) $it->kondisi_tabung, 'c'),
            $this->cell((string) $it->kondisi_nozzle_selang, 'c'),
            $this->cell((string) $it->indikator_tekanan, 'c'),
            $this->cell((string) $it->kondisi_pin_segel, 'c'),
            $this->cell((string) $it->keterangan),
        ])->all();

        return $this->section('apar-apab', 'Inspeksi APAR / APAB', (string) ($record?->getAttribute('no_dokumen') ?: 'SMT-FM-AK3-12.03'), 'landscape', [
            [$this->h('No', r: 2, w: '4%'), $this->h('No. RFID', r: 2), $this->h('Lokasi', r: 2, w: '14%'), $this->h('Tgl Periksa', r: 2), $this->h('Merk', r: 2), $this->h('Jenis', r: 2), $this->h('Berat (kg)', r: 2), $this->h('Kondisi', c: 4), $this->h('Keterangan', r: 2, w: '12%')],
            [$this->h('Tabung'), $this->h('Nozzle / Selang'), $this->h('Indikator Tekanan'), $this->h('Pin / Segel')],
        ], $rows, $record !== null, $record?->getAttribute('catatan'));
    }

    /** @return array<string, mixed> */
    private function apat(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanApat::class, $unit, $month, $year);

        return $this->simple('apat', 'Inspeksi Alat Pemadam Api Tradisional (APAT)', $record, 'SMT-FM-AK3-12.04', 'portrait',
            ['Nama Alat' => null, 'Tgl Inspeksi' => '14%', 'Kondisi' => '14%', 'Jumlah' => '10%', 'Keterangan' => null],
            fn ($it): array => [(string) $it->nama_alat, $this->date($it->tanggal_inspeksi), (string) $it->kondisi, (string) $it->jumlah, (string) $it->keterangan],
            ['c' => [1, 2, 3]],
        );
    }

    /** @return array<string, mixed> */
    private function hydrant(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanHydrant::class, $unit, $month, $year);

        return $this->simple('hydrant', 'Inspeksi Hydrant', $record, 'SMT-FM-AK3-12.05', 'portrait',
            ['Lokasi' => null, 'Tgl Periksa' => '12%', 'Hose' => '10%', 'Nozzle' => '10%', 'Box Hydrant' => '10%', 'Tekanan Air' => '11%', 'Keterangan' => null],
            fn ($it): array => [(string) $it->lokasi, $this->date($it->tanggal_periksa), (string) $it->hose, (string) $it->nozzle, (string) $it->box_hydrant, (string) $it->kondisi_tekanan_air, (string) $it->keterangan],
            ['c' => [1, 2, 3, 4, 5]],
        );
    }

    /** @return array<string, mixed> */
    private function fireAlarm(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanFireAlarm::class, $unit, $month, $year);

        return $this->simple('fire-alarm', 'Inspeksi Fire Alarm', $record, 'SMT-FM-AK3-12.06', 'portrait',
            ['Lokasi' => null, 'Tgl Periksa' => '14%', 'Kondisi' => '14%', 'Panel Indikator' => '16%', 'Keterangan' => null],
            fn ($it): array => [(string) $it->lokasi, $this->date($it->tanggal_periksa), (string) $it->kondisi, (string) $it->panel_indikator, (string) $it->keterangan],
            ['c' => [1, 2, 3]],
        );
    }

    /** @return array<string, mixed> */
    private function alatTanggapDarurat(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanAlatTanggapDarurat::class, $unit, $month, $year);

        return $this->simple('alat-tanggap-darurat', 'Daftar Alat Tanggap Darurat', $record, 'SMT-FM-AK3-03.03', 'portrait',
            ['Jenis Alat' => null, 'Siap Pakai' => '10%', 'Kadaluarsa' => '10%', 'Kosong' => '10%', 'Tgl Diisi Kembali' => '14%', 'Keterangan' => null],
            fn ($it): array => [(string) $it->jenis, (string) $it->siap_pakai, (string) $it->kadaluarsa, (string) $it->kosong, $this->date($it->tgl_diisi_kembali), (string) $it->keterangan],
            ['c' => [1, 2, 3, 4]],
        );
    }

    /** @return array<string, mixed> */
    private function emergencyFacility(Unit $unit, int $month, int $year): array
    {
        $items = K3PengusahaanEmergencyFacility::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderByRaw("CASE periode WHEN 'M1' THEN 1 WHEN 'M2' THEN 2 WHEN 'M3' THEN 3 WHEN 'M4' THEN 4 ELSE 5 END")
            ->orderBy('sort_order')->orderBy('no_urut')->get();

        $rows = [];
        foreach ($items->groupBy('periode') as $periode => $group) {
            $rows[] = $this->sectionRow('Periode '.($periode === 'BULANAN' ? 'Bulanan' : $periode), 10);
            foreach ($group->groupBy('grup') as $grup => $list) {
                $rows[] = $this->sectionRow((string) $grup, 10, italic: true);
                foreach ($list->values() as $i => $it) {
                    $rows[] = [
                        $this->cell((string) ($it->no_urut ?: $i + 1), 'c'),
                        $this->cell((string) $it->nama_peralatan),
                        $this->cell((string) $it->jml_total, 'c'),
                        $this->cell((string) $it->jml_ready, 'c'),
                        $this->cell((string) $it->jml_not_ready, 'c'),
                        $this->cell((string) $it->persen_kesiapan, 'c'),
                        $this->cell((string) $it->lokasi),
                        $this->cell((string) $it->kendala),
                        $this->cell((string) $it->tindak_lanjut),
                        $this->cell(''),
                    ];
                }
            }
        }

        return $this->section('emergency-facility', 'Pemeriksaan Emergency Facility', (string) ($items->first()?->no_dokumen ?: 'SMT-FM-AK3-03.04'), 'landscape', [
            [$this->h('No', w: '4%'), $this->h('Nama Peralatan', w: '18%'), $this->h('Jml Total'), $this->h('Ready'), $this->h('Not Ready'), $this->h('% Kesiapan'), $this->h('Lokasi'), $this->h('Kendala'), $this->h('Tindak Lanjut'), $this->h('Ket.', w: '5%')],
        ], $rows, $items->isNotEmpty());
    }

    /** @return array<string, mixed> */
    private function pemeriksaanP3k(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanPemeriksaanP3k::class, $unit, $month, $year);
        $locations = array_values((array) ($record?->getAttribute('locations') ?: K3PengusahaanPemeriksaanP3k::DEFAULT_LOCATIONS));
        $rows = [];
        foreach ($record?->items ?? [] as $i => $it) {
            $kondisi = is_array($it->kondisi_lokasi) ? $it->kondisi_lokasi : (array) json_decode((string) $it->kondisi_lokasi, true);
            $rows[] = [
                $this->cell((string) ($it->no_urut ?: $i + 1), 'c'),
                $this->cell((string) $it->nama_isi),
                $this->cell((string) $it->standar_jumlah, 'c'),
                $this->cell((string) $it->satuan, 'c'),
                ...array_map(fn (string $loc): array => $this->cell((string) ($kondisi[$loc] ?? ''), 'c'), $locations),
                $this->cell((string) $it->keterangan),
            ];
        }

        return $this->section('pemeriksaan-p3k', 'Pemeriksaan Kotak P3K', (string) ($record?->getAttribute('no_dokumen') ?: 'SMT-FM-AK3-10.01'), 'landscape', [
            [$this->h('No', r: 2, w: '4%'), $this->h('Nama Isi Kotak P3K', r: 2, w: '20%'), $this->h('Standar', r: 2), $this->h('Satuan', r: 2), $this->h('Jumlah per Lokasi', c: count($locations)), $this->h('Keterangan', r: 2)],
            array_map(fn (string $loc): array => $this->h($loc), $locations),
        ], $rows, $record !== null, $record?->getAttribute('catatan'));
    }

    /** @return array<string, mixed> */
    private function inventarisApd(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanInventarisApd::class, $unit, $month, $year);
        $rows = [];
        foreach (collect($record?->items ?? [])->sortBy('sort_order')->groupBy(fn ($it): string => trim(($it->kategori ? $it->kategori.'. ' : '').($it->nama_grup ?? ''))) as $group => $list) {
            if ($group !== '') {
                $rows[] = $this->sectionRow($group, 5);
            }
            foreach ($list->values() as $i => $it) {
                $rows[] = [$this->cell((string) ($i + 1), 'c'), $this->cell((string) $it->nama_alat), $this->cell((string) $it->jumlah, 'c'), $this->cell((string) $it->lokasi), $this->cell((string) $it->keterangan)];
            }
        }

        return $this->section('inventaris-apd', 'Daftar Inventaris Alat Pelindung Diri (APD)', (string) ($record?->getAttribute('no_dokumen') ?: 'SMT-FM-AK3-01.01'), 'portrait', [
            [$this->h('No', w: '6%'), $this->h('Nama Alat'), $this->h('Jumlah', w: '10%'), $this->h('Lokasi', w: '22%'), $this->h('Keterangan', w: '22%')],
        ], $rows, $record !== null, $record?->getAttribute('catatan'));
    }

    /** @return array<string, mixed> */
    private function inspeksiRambu(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanInspeksiRambu::class, $unit, $month, $year);

        return $this->simple('inspeksi-rambu', 'Inspeksi Rambu-Rambu K3', $record, 'SMT-FM-AK3-12.07', 'portrait',
            ['Rambu K3' => null, 'Lokasi' => '24%', 'Tingkat Pelanggaran' => '16%', 'Keterangan' => '16%'],
            fn ($it): array => [(string) $it->rambu_k3, (string) $it->lokasi, (string) $it->tingkat_pelanggaran, (string) $it->keterangan],
            ['c' => [2, 3]],
            meta: $record ? ['Tanggal Inspeksi' => $this->date($record->getAttribute('tanggal_inspeksi'))] : [],
        );
    }

    /** @return array<string, mixed> */
    private function inspeksiTempatKerja(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanInspeksiTempatKerja::class, $unit, $month, $year);
        $rows = [];
        foreach (collect($record?->items ?? [])->groupBy('category') as $category => $list) {
            $rows[] = $this->sectionRow((string) $category, 4);
            foreach ($list->values() as $i => $it) {
                $rows[] = [$this->cell((string) ($it->no_urut ?: $i + 1), 'c'), $this->cell((string) $it->item), $this->cell((string) ($it->status ?? '-'), 'c'), $this->cell((string) $it->comment)];
            }
        }
        $meta = $record ? array_filter([
            'Tanggal' => $this->date($record->getAttribute('tanggal_inspeksi')),
            'Departemen' => (string) $record->getAttribute('departemen'),
            'Lokasi' => (string) $record->getAttribute('lokasi'),
            'Tim Inspektur' => (string) $record->getAttribute('tim_inspektur'),
            'Ketua Tim' => (string) $record->getAttribute('ketua_tim'),
        ], fn (string $v): bool => $v !== '' && $v !== '-') : [];

        return $this->section('inspeksi-tempat-kerja', 'Inspeksi Tempat Kerja dan Fasilitas', (string) ($record?->getAttribute('no_dokumen') ?: 'SMT-FM-AK3-12-01'), 'portrait', [
            [$this->h('No', w: '6%'), $this->h('Item Pemeriksaan'), $this->h('Status', w: '12%'), $this->h('Komentar', w: '30%')],
        ], $rows, $record !== null, $record?->getAttribute('catatan'), $meta);
    }

    /** @return array<string, mixed> */
    private function patrolSecurity(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanPatrolSecurity::class, $unit, $month, $year);
        $items = collect($record?->items ?? []);
        $grand = max(1, (int) $items->sum('total'));
        $rows = [];
        foreach ($items as $it) {
            $scans = (array) $it->scans;
            $total = (int) ($it->total ?: array_sum(array_map('intval', $scans)));
            $rows[] = [
                $this->cell((string) ($it->lokasi_kode ?: $it->lokasi_nama), 'c', true),
                ...array_map(fn (int $d): array => ['t' => (string) ((int) ($scans[(string) $d] ?? 0) ?: ''), 'a' => 'c', 'red' => in_array($d, $this->redDays, true)], range(1, $this->days)),
                $this->cell((string) $total, 'c', true),
                $this->cell(number_format($total / $grand * 100, 1, ',', '.').'%', 'c'),
            ];
        }
        if ($items->isNotEmpty()) {
            $rows[] = [
                $this->cell('TOTAL', 'c', true),
                ...array_map(fn (int $d): array => $this->cell((string) ($items->sum(fn ($it): int => (int) (((array) $it->scans)[(string) $d] ?? 0)) ?: ''), 'c', true), range(1, $this->days)),
                $this->cell((string) $items->sum('total'), 'c', true),
                $this->cell('100%', 'c', true),
            ];
        }

        return $this->section('patrol-security', (string) ($record?->getAttribute('judul') ?: 'Patrol Check Security'), 'SMT-FM-KAM-01', 'landscape', [
            [$this->h('Lokasi', r: 2, w: '6%'), $this->h('Tanggal (jumlah scan)', c: $this->days), $this->h('Total', r: 2, w: '5%'), $this->h('%', r: 2, w: '5%')],
            $this->dayHeader(),
        ], $rows, $record !== null, $record?->getAttribute('catatan'));
    }

    /** @return array<string, mixed> */
    private function apelKeamanan(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanApelKeamanan::class, $unit, $month, $year);
        $rows = collect($record?->items ?? [])->sortBy([['tanggal', 'asc'], ['sort_order', 'asc']])->values()->map(fn ($it, int $i): array => [
            $this->cell((string) ($i + 1), 'c'),
            $this->cell($this->date($it->tanggal), 'c'),
            $this->cell((string) $it->tim_regu, 'c'),
            $this->cell((string) $it->shift, 'c'),
            $this->cell((string) $it->waktu_apel, 'c'),
            $this->cell((string) $it->jumlah_personil, 'c'),
            $this->cell((string) $it->kelengkapan_atribut, 'c'),
            $this->cell((string) $it->keterangan),
        ])->all();

        return $this->section('apel-keamanan', 'Laporan Apel Keamanan', (string) ($record?->getAttribute('no_dokumen') ?: 'SMT-FM-KAM-02'), 'portrait', [
            [$this->h('No', w: '5%'), $this->h('Tanggal', w: '12%'), $this->h('Tim / Regu'), $this->h('Shift'), $this->h('Waktu Apel'), $this->h('Jml Personil'), $this->h('Kelengkapan Atribut'), $this->h('Keterangan', w: '20%')],
        ], $rows, $record !== null, $record?->getAttribute('catatan'));
    }

    /** @return array<string, mixed> */
    private function laporanCctv(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanLaporanCctv::class, $unit, $month, $year);

        return $this->simple('laporan-cctv', 'Laporan Pemantauan CCTV', $record, 'SMT-FM-KAM-03', 'portrait',
            ['Tanggal' => '12%', 'Lokasi CCTV' => null, 'Waktu Pantau' => '14%', 'Kondisi Pantau' => '16%', 'Keterangan' => null],
            fn ($it): array => [$this->date($it->tanggal), (string) $it->lokasi_cctv, (string) $it->waktu_pantau, (string) $it->kondisi_pantau, (string) $it->keterangan],
            ['c' => [0, 2, 3]],
        );
    }

    /** @return array<string, mixed> */
    private function bukuTamu(Unit $unit, int $month, int $year): array
    {
        $record = $this->record(K3PengusahaanBukuTamu::class, $unit, $month, $year);
        $items = collect($record?->items ?? []);
        $rows = $items->values()->map(fn ($it, int $i): array => [
            $this->cell((string) ($it->no_urut ?: $i + 1), 'c'),
            $this->cell($this->date($it->tanggal), 'c'),
            $this->cell((string) $it->jumlah_kehadiran_tamu, 'c'),
            $this->cell((string) $it->tamu_pln, 'c'),
            $this->cell((string) $it->instansi, 'c'),
            $this->cell((string) $it->kontraktor, 'c'),
            $this->cell((string) $it->lainnya, 'c'),
            $this->cell((string) $it->keterangan),
        ])->all();
        if ($items->isNotEmpty()) {
            $rows[] = [
                ['t' => 'JUMLAH', 'c' => 2, 'a' => 'c', 'b' => true],
                ...array_map(fn (string $f): array => $this->cell((string) $items->sum($f), 'c', true), ['jumlah_kehadiran_tamu', 'tamu_pln', 'instansi', 'kontraktor', 'lainnya']),
                $this->cell(''),
            ];
        }

        return $this->section('buku-tamu', 'Laporan Mutasi Buku Tamu', (string) ($record?->getAttribute('no_dokumen') ?: 'SMT-FM-AK3-06.04'), 'portrait', [
            [$this->h('No', r: 2, w: '5%'), $this->h('Tanggal', r: 2, w: '12%'), $this->h('Jumlah Kehadiran', r: 2), $this->h('Jenis Tamu', c: 4), $this->h('Keterangan', r: 2, w: '20%')],
            [$this->h('PLN'), $this->h('Instansi'), $this->h('Kontraktor'), $this->h('Lainnya')],
        ], $rows, $record !== null, $record?->getAttribute('catatan'));
    }

    /** @return array<string, mixed> */
    private function certificates(Unit $unit): array
    {
        $items = K3PengusahaanCertificate::query()->with('category')->where('unit_id', $unit->id)->orderBy('id')->get();
        $rows = $items->values()->map(fn ($it, int $i): array => [
            $this->cell((string) ($i + 1), 'c'),
            $this->cell((string) ($it->category?->name ?? '-')),
            $this->cell((string) $it->jenis),
            $this->cell((string) $it->kapasitas, 'c'),
            $this->cell((string) $it->lokasi),
            $this->cell((string) $it->merk_manufacture),
            $this->cell((string) $it->no_seri, 'c'),
            $this->cell((string) $it->regulasi),
            $this->cell((string) $it->ijin_awal_nomor),
            $this->cell($this->date($it->ijin_awal_tanggal), 'c'),
            $this->cell((string) $it->uji_terakhir_nomor),
            $this->cell($this->date($it->uji_terakhir_tanggal), 'c'),
            $this->cell($this->date($it->uji_ulang_tanggal), 'c'),
            $this->cell((string) $it->batasan_uji, 'c'),
            $this->cell((string) $it->keterangan),
        ])->all();

        return $this->section('certificate', 'Daftar Sertifikasi Peralatan', 'SMT-FM-AK3-05.01', 'landscape', [
            [$this->h('No', r: 2, w: '3%'), $this->h('Kategori Alat', r: 2), $this->h('Jenis', r: 2), $this->h('Kapasitas', r: 2), $this->h('Lokasi', r: 2), $this->h('Merk', r: 2), $this->h('No. Seri', r: 2), $this->h('Regulasi', r: 2), $this->h('Ijin Pemakaian Awal', c: 2), $this->h('Uji Terakhir', c: 2), $this->h('Uji Ulang', r: 2), $this->h('Batasan Uji', r: 2), $this->h('Keterangan', r: 2)],
            [$this->h('Nomor'), $this->h('Tanggal'), $this->h('Nomor'), $this->h('Tanggal')],
        ], $rows, $items->isNotEmpty());
    }

    /** @return array<string, mixed> */
    private function metodePengujian(Unit $unit, int $month, int $year): array
    {
        $items = K3PengusahaanMetodePengujian::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->get();
        $meta = K3PengusahaanMetodePengujianMeta::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->first();
        $tests = ['uji_visual', 'uji_fungsi', 'uji_beban', 'uji_hydro', 'ndt', 'uji_ultrasonic_thickness', 'uji_ketahanan'];
        $rows = $items->values()->map(fn ($it, int $i): array => [
            $this->cell((string) ($it->no_urut ?: $i + 1), 'c'),
            $this->cell((string) $it->nama_peralatan),
            $this->cell((string) $it->no_pengesahan),
            $this->cell((string) $it->nama_kategori_alat),
            ...array_map(fn (string $t): array => $this->cell((string) ($it->{$t} ?? '-'), 'c'), $tests),
            $this->cell((string) $it->sertifikasi_terakhir, 'c'),
            $this->cell((string) $it->sertifikasi_ulang, 'c'),
            $this->cell((string) $it->keterangan),
        ])->all();

        return $this->section('metode-pengujian', 'Formulir Metode Pengujian Peralatan', (string) ($meta?->nomor_dokumen ?: 'SMT-FM-AK3-05.02'), 'landscape', [
            [$this->h('No', r: 2, w: '3%'), $this->h('Nama Peralatan', r: 2), $this->h('No. Pengesahan', r: 2), $this->h('Kategori Alat', r: 2), $this->h('Metode Pemeriksaan', c: 7), $this->h('Sertifikasi', c: 2), $this->h('Keterangan', r: 2)],
            [$this->h('Visual'), $this->h('Fungsi'), $this->h('Beban'), $this->h('Hydro'), $this->h('NDT'), $this->h('Ultrasonic'), $this->h('Ketahanan'), $this->h('Terakhir'), $this->h('Ulang')],
        ], $rows, $items->isNotEmpty(), $meta?->catatan);
    }

    /** @return array<string, mixed> */
    private function evaluasiPengujian(Unit $unit, int $year): array
    {
        $items = K3PengusahaanEvaluasiPengujian::query()->where('unit_id', $unit->id)->where('year', $year)->orderBy('sort_order')->get();
        $rows = $items->values()->map(function ($it, int $i): array {
            $done = array_map('intval', (array) ($it->progres_bulan ?? []));

            return [
                $this->cell((string) ($it->no_urut ?: $i + 1), 'c'),
                $this->cell((string) $it->nama_kategori_alat),
                $this->cell((string) $it->jenis),
                $this->cell((string) $it->kapasitas, 'c'),
                $this->cell((string) $it->temuan_sertifikat),
                ...array_map(fn (int $m): array => ['t' => in_array($m, $done, true) ? '✓' : '', 'a' => 'c', 'fill' => in_array($m, $done, true) ? 'realisasi' : null], range(1, 12)),
                $this->cell((string) $it->keterangan),
            ];
        })->all();
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return $this->section('evaluasi-pengujian', "Evaluasi Hasil Pengujian Peralatan Tahun {$year}", 'SMT-FM-AK3-05.03', 'landscape', [
            [$this->h('No', r: 2, w: '3%'), $this->h('Kategori Alat', r: 2), $this->h('Jenis', r: 2), $this->h('Kapasitas', r: 2), $this->h('Temuan dalam Sertifikat', r: 2, w: '22%'), $this->h('Progres Tindak Lanjut', c: 12), $this->h('Keterangan', r: 2)],
            array_map(fn (string $m): array => $this->h($m), $months),
        ], $rows, $items->isNotEmpty());
    }

    /**
     * A header + items form rendered as a numbered table.
     *
     * @param  array<string, string|null>  $columns  label => width
     * @param  callable(mixed): list<string>  $map
     * @param  array{c?: list<int>}  $align  centred column indexes (after No)
     * @param  array<string, string>  $meta
     * @return array<string, mixed>
     */
    private function simple(string $key, string $title, ?Model $record, string $defaultNumber, string $orientation, array $columns, callable $map, array $align = [], array $meta = []): array
    {
        $centred = $align['c'] ?? [];
        $rows = collect($record?->items ?? [])->values()->map(function ($it, int $i) use ($map, $centred): array {
            $values = $map($it);

            return [
                $this->cell((string) ((int) ($it->no_urut ?? $it->no_id ?? 0) ?: $i + 1), 'c'),
                ...array_map(fn (string $v, int $index): array => $this->cell($v, in_array($index, $centred, true) ? 'c' : 'l'), $values, array_keys($values)),
            ];
        })->all();

        $head = [[$this->h('No', w: '5%'), ...array_map(fn (string $label, ?string $w): array => $this->h($label, w: $w), array_keys($columns), $columns)]];

        return $this->section($key, $title, (string) ($record?->getAttribute('no_dokumen') ?: $defaultNumber), $orientation, $head, $rows, $record !== null, $record?->getAttribute('catatan'), $meta);
    }

    /**
     * @param  list<list<array<string, mixed>>>  $head
     * @param  list<list<array<string, mixed>>>  $rows
     * @param  array<string, string>  $meta
     * @return array<string, mixed>
     */
    private function section(string $key, string $title, string $number, string $orientation, array $head, array $rows, bool $filled, ?string $note = null, array $meta = []): array
    {
        return [
            'key' => $key,
            'no' => 0,
            'title' => $title,
            'number' => $number,
            'orientation' => $orientation,
            'meta' => $meta,
            'head' => $head,
            'rows' => $rows,
            'filled' => $filled,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function record(string $model, Unit $unit, int $month, int $year): ?Model
    {
        return $model::query()->with('items')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->latest('id')->first();
    }

    /** @return list<array<string, mixed>> */
    private function dayHeader(): array
    {
        return array_map(fn (int $d): array => [...$this->h((string) $d), 'red' => in_array($d, $this->redDays, true)], range(1, $this->days));
    }

    /** @return array<string, mixed> */
    private function h(string $text, int $c = 1, int $r = 1, ?string $w = null): array
    {
        return ['t' => $text, 'c' => $c, 'r' => $r, 'w' => $w];
    }

    /** @return array<string, mixed> */
    private function cell(string $text, string $align = 'l', bool $bold = false, int $r = 1): array
    {
        return ['t' => $text, 'a' => $align, 'b' => $bold, 'r' => $r];
    }

    /** @return list<array<string, mixed>> */
    private function sectionRow(string $text, int $span, bool $italic = false): array
    {
        return [['t' => $text, 'c' => $span, 'b' => true, 's' => true, 'i' => $italic]];
    }

    /** @return list<array<string, mixed>> */
    private function line(string $label, string $value, bool $bold = false): array
    {
        return [$this->cell($label, 'l', $bold), ['t' => $value, 'c' => 3, 'a' => 'c', 'b' => $bold], $this->cell('')];
    }

    private function date(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function num(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $number = (float) $value;

        return floor($number) === $number ? number_format($number, 0, ',', '.') : number_format($number, 2, ',', '.');
    }
}
