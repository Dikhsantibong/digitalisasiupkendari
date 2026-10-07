---
paths:
  - 'app/Services/Operasi/**'
---

# Operasi

## Modul OPERASI — registry generik (laporan, master) & satu service kalkulasi
Semua rumus operasi HANYA di `App\Services\Operasi\OperasiCalculator` (carry-over stand awal, faktor kalibrasi, produksi/pemakaian, subtotal periode, rekap jam dari Star-Stop, SFC) — dipakai grid, laporan, BA. Jangan duplikasi rumus di controller/frontend.
Laporan: daftar di `Reports\ReportRegistry` (kelas implement `OperasiReport`), satu `LaporanController` render semua. Master data operasi (feeder/tangki/pelumas/faktor/kode status): daftar di `Master\OperasiMasterRegistry` (skema field), satu `MasterController` generik (validasi + form otomatis dari skema). Berita Acara: `BeritaAcaraBuilder` + `DocumentTemplateService` (nomor surat tetap, override per unit) + Blade dompdf; nomor surat JANGAN auto-increment. Helper tanggal/terbilang: `App\Support\Indonesian`. Tambah laporan/master/dokumen = tambah 1 kelas/entri registry, tanpa controller/route baru.

## Pengusahaan Operasi recap sheets are computed and correctable
Recap sheets (Kinerja Unit Mesin, Daftar Inventarisasi Mesin, Data Kinerja Pembangkit Termal, Ikhtisar Sentral) are never typed in. A `*Sheet` service builds the automatic values from the input sheets (Stand kWh Harian → kWh via `OperasiCalculator`; the Jam sheets; Beban Tertinggi; Pemakaian BBM/Pelumas; Penerimaan BBM). Only the user's corrections are stored, in `operasi_rekaps` (`jenis` + `overrides`, keyed by row/field or by path). The UI marks a corrected cell and offers a reset. Do not copy computed values into new tables. Kinerja Termal formulas and constants (860 / 10289 / 0.949) live in `KinerjaTermalSheet`.
