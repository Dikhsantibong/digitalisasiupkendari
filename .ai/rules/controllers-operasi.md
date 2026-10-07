---
paths:
  - 'app/Http/Controllers/Operasi/**'
---

# Controllers Operasi

## Logsheet & Absensi sekarang di modul OPERATOR
Layer input operator (logsheet harian + jadwal/absensi shift) sudah DIPINDAH ke modul tersendiri `operator` — lihat `.ai/rules/controllers-operator.md`. JANGAN tambahkan controller logsheet/absensi di namespace Operasi lagi. OPERASI hanya boleh menarik datanya nanti via `App\Services\Operasi\LogsheetAggregator` (belum aktif).

## Laporan: satu pintu "Buka Dokumen" + pratinjau PDF di editor (HAR/Operasi/K3 seragam)
Keputusan user: TIDAK ada lagi tombol "Lihat & Cetak" terpisah di menu Laporan. Semua modul (HAR, Operasi, K3) hanya punya satu tombol "Buka Dokumen (Lihat, Edit & Cetak)" yang membuka editor dokumen. Pratinjau ada DI DALAM editor: komponen shared `components/document/document-editor.tsx` punya 3 mode — Teks (PDF) · Excel · **Pratinjau PDF** (iframe ke route `*.laporan.document.pdf`, jadi pratinjau = hasil cetak persis). Print-preview React lama (`operasi/laporan/monthly-engine`, `har/laporan/monthly`) + route `laporan.show`/`spreadsheet`/`har.laporan.monthly` masih ADA tapi tak ditaut dari UI (jangan hapus, dipakai test). K3 tak punya print-preview terpisah (dulu sempat ada `k3.laporan.monthly`, sudah dihapus).
Logo PDF: SEMUA controller PDF laporan pakai trait `App\Http\Controllers\Concerns\EmbedsReportLogo` (regex ganti `src=…logo/sidebar-logo.png` → data URI base64). WAJIB pakai trait ini, JANGAN `str_replace('/logo/…')` — TinyMCE mengubah src jadi URL absolut saat disimpan sehingga str_replace path relatif meleset dan dompdf menampilkan alt "Logo".
Operasi: `LaporanDocumentController` edit/store/pdf (route `operasi.laporan.document.{edit,store,pdf}` param `{report}`). Persist ke `OperasiReportDocument` (tabel `operasi_report_documents`, unique unit+report_code+engine_id+month+year) — BUKAN `DocumentRecord` (di-cast enum BeritaAcaraType). content_html default = letterhead + `DocumentGridBuilder::gridToHtml(forMonthlyReport($data))`; PDF via blade `operasi.laporan.pdf-shell` + letterhead `operasi.laporan.partials.letterhead`. Gate `operasi.laporan.view`.

## Gotcha kolom bertipe date (SQLite)
Kolom `date` (cast 'date') tersimpan '00:00:00' di SQLite in-memory (test) → JANGAN query `where('some_date',$str)`; pakai `whereDate('some_date',$str)`. Untuk assertDatabaseHas tanggal, query model lalu bandingkan `->toDateString()`.

## Laporan Operasi: tabel jadwal/input disisipkan dari pdfView, bukan dibangun ulang
Laporan Operasi Pembangkit (BODY_VERSION 6): I Sampul, II Daftar Isi, III Lembar Pengesahan (TL Operasi / Koordinator Operasi / Manager dari `Employee` by position, tanda tangan dari `signature_path` bila ada), IV Resume Statistik (+ grafik batang & lingkaran 3D PNG via `App\Services\Reports\Chart3d`), V poin laporan (landscape), VI Lampiran. Setiap controller jadwal/input Operasi punya `pdfView(Unit, month, year)`; `App\Services\Operasi\OperasiReportTables::TABLES` menyisipkan view PDF itu (CSS scoped) ke laporan sehingga tabel selalu lengkap — tambah jadwal/input baru ke TABLES + section di document-body, jangan buat query tabel sendiri di `OperasiReportSections` (itu hanya untuk poin tanpa view: shift operator, data teknis bulan ini, patrol check, unsafe HAR, laporan 5S5R, input data aplikasi). Resume dihitung dari data pdfView yang sama.

## Operator input Operasi lewat operasi.lapangan.input; halaman pakai {key}/index.tsx
Role Operator & Project Leader TIDAK memegang operasi.input.*; mereka pegang `operasi.lapangan.input` yang hanya membuka Patrol Check Mesin, Unsafe Action & Condition, Program 5S5R, Monitoring FLM (gate via trait `Concerns\AuthorizesOperasiInput`). "Logbook mesin" operator = Logsheet Operator (modul Operator). Semua halaman `resources/js/pages/operasi/input` memakai pola `{key}/index.tsx` (Inertia::render('operasi/input/{key}/index')) seperti HAR/PdM/Logistik — JANGAN tambah file datar baru. Halaman yang diisi operator wajib punya cabang `useCompactLayout()` (kartu, tanpa tabel) + komponen `components/mobile/*`, dan kartunya didaftarkan di `layouts/mobile/modules.ts` dengan `coveredBy` agar sidebar desktop tidak dobel.

## Input harian Operasi & Berita Acara = Pengusahaan Operasi (Akses 2)
Keputusan user (2026-10-01): Input Harian, Star-Stop, Feeder, Pasokan Cadangan, Penerimaan BBM, Resource Pembangkit dan Berita Acara PINDAH ke Pengusahaan Operasi. Controller `Operasi\Pengusahaan{DailyReport,StarStop,FeederReading,AuxiliaryReading,FuelReceipt,ResourcePembangkit,BeritaAcara}Controller`, route `operasi.pengusahaan.{daily-report,star-stop,feeder,auxiliary,fuel-receipt,resource-pembangkit,berita-acara}.*` (didaftarkan SEBELUM hub `pengusahaan/{section}`), halaman `pengusahaan/operasi/{folder}/index`, kartu di `lib/pengusahaan-menus.ts` (pakai `component` override). Permission `operasi.pengusahaan.view/write` = TL & Staf Operasi tulis, Manager UL baca; Koordinator Operasi TIDAK (kecuali Resource Pembangkit read-only via operasi.laporan.view karena dipakai Laporan). Permission OperasiBeritaAcara* dihapus. Blade PDF tetap di views/operasi/** (dipakai OperasiReportTables).

## Pemakaian Pelumas & Stand Meter read their columns from the unit's master
Pemakaian Pelumas: lubricant columns = the unit's active `LubricantType` (master Jenis Pelumas). Machines per lubricant = the `machine_lubricant_type` pivot, or all active machines if none are linked. Totals are recomputed server-side in `Services\Operasi\PemakaianPelumasSheet` (readings keyed "{lubId}_{machineId}" → day). Stand Meter: jenis BBM = `UnitFuelTypes` (see below); store rejects other codes. Both save only with `operasi.pengusahaan.write` (Manager UL/UP read only). The phone `menus/operasi.ts` must list these pages in PENGUSAHAAN_PAGES.

## Pengusahaan Operasi sheets: BBM, TUG 9 and Jam mesin
- **Pemakaian Bahan Bakar**: readings keyed "{fuelCode}_{machineId}". Jenis BBM per unit = `Services\Operasi\UnitFuelTypes` = the `BbmType` (master Jenis BBM) linked to each active `FuelTank` (`fuel_tanks.bbm_type_id`; an unlinked tank falls back to the BbmType whose code matches its group); every active machine is listed under each jenis. NEVER hardcode HSD/MFO: Stand Meter, Pemakaian BBM, TUG BBM, Kinerja Termal and Ikhtisar Sentral build their BBM columns from this list (Ikhtisar paths `mesins.{id}.bbm.{CODE}` / `inventory.{row}.bbm.{CODE}`; the `pemakaian_hsd/mfo` columns are legacy, filled for the dashboard only).
- **TUG 9**: one table `operasi_tugs` with `jenis` (`TugJenis` pelumas|bbm) stores only the header per machine and month. Amounts always come from the sheets via `TugDocument` (`TugPelumasDocument` / `TugBbmDocument`); controllers extend the abstract `TugController`. BBM material codes live in `bbm_types.material_code`.
- **Jam Operasi/Pemeliharaan/Gangguan**: one `PengusahaanJamMesinController`, with the type passed as a route `->defaults('jenis', ...)`, and table `operasi_jam_mesin`. Jam Siap Ops is never stored: it is 24 − the three (`JamMesinSheet::siapOperasi`), and a negative value is flagged.
- **PDF letterhead**: every Pengusahaan Operasi PDF must show the PLN logo inlined as a data URI. Use `@include('operasi.partials.kop-pengusahaan', ['unit' => $unit])`; never use `public_path` or a relative `/logo` src.

## Pengusahaan Operasi sidebar: only Input and Berita Acara
Decided 2026-10-07: Pengusahaan sections are only `input` and `formulir` (`PengusahaanController::SECTIONS`). For Operasi, `formulir` is shown as "Berita Acara" (`MODULE_SECTIONS` server-side, `PENGUSAHAAN_SECTIONS[].modules` client-side) and holds Berita Acara + TUG 9. The kWh and Rekap pages sit in `input` with `group: 'kWh' | 'Rekap'`, shown under sub-headings on the hub (phone groups op-kwh/op-rekap). Never add a new section for them again. Ikhtisar Sentral is in group Rekap.
Removed from the menus 2026-10-07 (user will rebuild them): Star-Stop, Feeder, Pasokan Cadangan, Penerimaan BBM, Resource Pembangkit. Their routes/controllers/data stay because Jam sheets, Kali Gangguan, Ikhtisar penerimaan and the Laporan still read them; do not re-add them to PENGUSAHAAN_MENUS or mobile menus/operasi.ts.

## Persediaan Bahan Bakar / Pelumas
One `PengusahaanPersediaanController` (route default `jenis` = `PersediaanJenis` bbm|pelumas), table `operasi_persediaan`, service `Services\Operasi\PersediaanSheet`. Only penerimaan + pengiriman (BBM: kirim_1 TUG 8/Kembali Sewa SMP, kirim_2 Pinjam THAS; pelumas: kirim_1 TUG 8, kirim_2 TUG 10, kirim_3 Over Flow) and opening CORRECTIONS are stored; PEMAKAIAN is always read from Pemakaian BBM / Pelumas raw_readings, and the opening is carried live from last month's saldo akhir. Periods: BBM 1-7, 8-14, 15-22, 23-end; pelumas = `PemakaianPelumasSheet::periods` (1-10, 11-20, 21-end). Input hub sub-headings: Bahan Bakar, Pelumas, kWh, Rekap (`PENGUSAHAAN_MENU_GROUPS`).

## Tara Kalor (kWh / kCal)
Third `MesinHarianJenis` (`tara-kalor`) on `PengusahaanMesinHarianController` / `operasi_mesin_harian`; menu in Input group kWh. Typed in manually, NOT linked to other data (user, 2026-10-07). `MesinHarianJenis::aggregation()` = average: TOTAL per day, JMH per machine and the unit figure are the average of the filled (>0) cells. PDF filenames must strip "/" from labels.

## kWh Rekap
Read-only `PengusahaanKwhController::rekap` (route `operasi.pengusahaan.kwh-rekap.*`) = `KwhSheet::rekap`: three blocks (produksi, ps, nett) built with `KwhSheet::energi` from one `harian()` call, so it always matches Stand kWh Harian / Energi Dibangkit / Energi PS. Never store kWh rekap values.

## SFC and SFC Netto
Read-only `PengusahaanKwhController::sfc` with route default `jenis` (`sfc` = liter / kWh produksi, 4 decimals; `sfc-netto` = liter / kWh netto, 3 decimals; config in `PengusahaanKwhController::SFC`). `KwhSheet::sfc(basis)`: liters = sum of EVERY jenis BBM of the unit (`UnitFuelTypes`, Pemakaian BBM raw_readings) - never hardcode HSD/MFO; 0 when the kWh is not positive; TOTAL/JMH/unit = summed liters / summed kWh. Shared page `components/operasi/sfc-page.tsx`; menus in Input group Rekap.

## Pelumas: tambah / ganti, Perincian, Rekap, BA Fisik
- Pemakaian Pelumas has two inputs per cell: `readings` = pelumas tambah, `readings_ganti` = pelumas ganti. `raw_readings` stays the TOTAL (tambah + ganti) so every other sheet keeps reading it; tambah is derived (`PemakaianPelumasSheet::tambahOf`). Old sheets without `readings_ganti` are all tambah. Read-only page: Pelumas Tambah & Ganti.
- Perincian Minyak Pelumas / Rekap Pelumas (`PengusahaanRincianPelumasController`, route default `jenis`) read Persediaan & Pemakaian Pelumas; only what no sheet holds is stored as OperasiRekap (`perincian-pelumas` / `rekap-pelumas`). Grease = jenis pelumas with satuan Kg (`LubricantUnit::Kg`).
- BA Pemeriksaan Fisik Pelumas (`ba-fisik-pelumas` OperasiRekap) takes its figures from `PerincianPelumasSheet::totals`; its counted liters are the default sisa fisik of Perincian & Rekap Pelumas (typed fisik there still wins).

## Bahan Bakar: Perincian & Rekap share one record
Perincian Bahan Bakar and Rekap Bahan Bakar (`PengusahaanRincianBbmController`, `RincianBbmSheet`) store ONE OperasiRekap `rincian-bbm` keyed by jenis BBM code; Perincian saves pengembalian / koreksi / fisik, Rekap saves the non-mesin pemakaian (test / cuci / bocor) per mesin — each page replaces only its own parts so both always show the same sisa. Penerimaan per periode and TUG 8 come from Persediaan Bahan Bakar.

## Laporan Pengusahaan = the menus' own pages (BODY_VERSION 3)
`OperasiPengusahaanBook` lists the chapters in the unit's filing order (Ikhtisar Sentral … TUG 9). Each chapter runs the menu controller's pdf action under `Services\Reports\PdfViewRecorder` (binds `dompdf.wrapper` to capture view + data + orientation) and embeds that view as a scoped fragment; PDF = `OrientationPdfMerger::renderSections` with `op-p-section` / `op-p-landscape`, so portrait and landscape pages are merged in order and the Daftar Isi gets page numbers. Per-jenis-BBM chapters loop over `UnitFuelTypes` (never HSD/MFO by name). To add a chapter: give the menu a pdf action and add it to the spec list. Chapters without a menu (Beban Tinggi, Indikator, FJK Mesin, Gangguan Feeder) print a placeholder page.
