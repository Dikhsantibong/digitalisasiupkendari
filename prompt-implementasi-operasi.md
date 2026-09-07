# PROMPT UNTUK CLAUDE CODE — Modul OPERASI: Input, Laporan, & Berita Acara

> **Langkah 0:** baca `CLAUDE.md` di root (project ini pakai **Laravel Boost** —
> ikuti konvensi & tooling-nya). Lalu baca `modul-operasi.md` (skema data &
> aturan bisnis). Prompt ini melengkapinya dengan fitur, hak akses, dukungan
> multi-unit, dan format output resmi yang harus persis dokumen aslinya.

Stack: **Laravel + Inertia + React + TypeScript + Tailwind + MySQL** (Laravel
Boost). Konfirmasi versi & konvensi dari `CLAUDE.md`.

---

## Konteks & keputusan yang sudah final

Bangun **modul OPERASI**. Tiga menu: **Input**, **Laporan**, **Berita Acara**.

Keputusan yang sudah dikunci user (jangan ditanyakan ulang):
- **Multi-unit.** Tiap menu punya pemilih unit; semua data per `unit_id`.
- **Master Unit Layanan, Mesin, Pegawai SUDAH ADA** → FK, jangan buat ulang.
  Periksa migration/model existing dulu (nama & PK). Identitas unit untuk kop
  laporan/BA diambil dari master Unit Layanan; penandatangan dari master
  Pegawai.
- **fuel_type & pelumas per mesin belum ada di master** → tambah kolom via
  ALTER ke master mesin, dan **beri UI/instruksi agar user melengkapi jenis
  bahan bakar (hsd_mfo/hsd_only) & pelumas tiap mesin**.
- **Satu TL Operasi = satu unit** (pivot `user_units`, tapi 1 baris per user).
- **Unit containerized (Cummins 1–10) & "plnt" = unit terpisah**, bukan Poasia.
- **Jam operasi/HAR/gangguan = dari log Star-Stop** (`engine_status_logs`),
  dihitung otomatis. Bukan input angka jam.
- **Beban puncak = 2 angka** (pagi & malam).
- **Nomor surat BA tetap/tidak berubah** per jenis dokumen (lihat §3.4).
- **Cummins IN/OUT** = pakai default aman di modul §5.8, tandai untuk verifikasi.

Aplikasi juga **multi-modul** — arsitektur generik (di bawah), tanpa menyebut
modul lain di UI.

---

## Hak Akses

- Semua menu hanya untuk role **"TL Operasi"**; role lain → 403 (bukan sekadar
  disembunyikan).
- RBAC generik, **jangan hardcode nama role**: `roles`, `permissions`
  (terhubung ke modul), pivot `role_permissions`, `user_roles` (multi-role).
  Cek akses via kode permission: `operasi.input.write`, `operasi.laporan.view`,
  `operasi.berita-acara.create`.
- **Scoping unit:** pivot `user_units` (satu TL Operasi = satu unit). Pemilih
  unit di tiap menu hanya menampilkan unit yang boleh diakses user.

---

## Prinsip Arsitektur (ikuti walau sekarang 1 modul)

1. **`work_modules`** (kode, nama), isi awal `operasi`. Setiap permission,
   report, document template terhubung ke modul. Folder `app/Modules/Operasi/…`,
   `resources/js/modules/operasi/…`; sediakan `app/Modules/_template/` contoh
   minimal untuk diduplikasi modul berikutnya.
2. **Grid input reusable** — komponen grid menerima definisi kolom/baris
   sebagai konfigurasi (field, tipe, read-only/auto-carry, validasi), bukan
   hardcode field operasi.
3. **Report registry** — laporan didaftarkan lewat definisi (kode, judul,
   query/kalkulasi, modul, filter), bukan controller unik per laporan.
4. **Document/BA template engine** — satu engine (`document_templates`), bukan
   3 file copy-paste. Tabel dinamis generik (BA HSD=baris per mesin, BA
   Pelumas=baris per jenis pelumas via satu struktur). Nomor surat via satu
   service. Helper tanggal Indonesia + terbilang reusable.
5. Tabel transaksi selalu ber-`unit_id`; RBAC/report/document layer generik.

---

## Fitur 1 — Menu Input ("terasa seperti Excel", per unit)

- **Pemilih unit** di atas (hanya unit user). Semua simpan/baca per `unit_id`.
- **Grid edit per sel** (bukan form vertikal). Library: pilih `handsontable` /
  `ag-grid-react` / `react-data-grid` (lisensi bebas komersial); komponen
  generik (prinsip #2). Keyboard: Tab/Enter/panah + **copy-paste dari Excel**.
- Layout: **baris=tanggal (1–31)**, **kolom=field** (`daily_engine_reports`),
  mesin dipilih per tab/dropdown (dari master existing, difilter unit).
- **Stand awal read-only auto-carry** di sebelah stand akhir.
- Tanda visual baris lengkap (hijau) vs belum (kuning).
- Baris TOTAL & PERIODE I/II/III otomatis (read-only).
- Validasi inline per sel.
- Field MFO hanya untuk mesin `hsd_mfo`.
- Tab terpisah dengan pola grid sama: **Star-Stop** (`engine_status_logs` —
  input jam mesin di sini, bukan angka jam agregat), **feeder**, **pasokan
  cadangan**, **penerimaan BBM** (`fuel_receipts`).
- Endpoint: permission `operasi.input.write` + cek hak unit.

---

## Fitur 2 — Menu Laporan (satu klik, per unit)

1. Pemilih **unit + periode/tanggal + mesin** (atau "semua mesin").
2. Laporan HANYA CETAK (rekap kWh, SFC, jam operasi/HAR/gangguan, pemakaian
   BBM & pelumas, distribusi feeder) — dihitung otomatis via **service
   kalkulasi yang sama** dengan grid (jangan duplikasi rumus; rumus di modul
   §5).
3. Lewat **report registry** (prinsip #3).
4. Tombol **"Lihat & Cetak"** → satu klik langsung preview (modal HTML/PDF) +
   "Unduh PDF"/"Cetak". Tanpa langkah perantara.
5. Laporan resmi PLN (sheet 1–14) tidak dibangun sekarang; registry mudah
   ditambah.
6. Akses `operasi.laporan.view` + hak unit.

---

## Fitur 3 — Menu Berita Acara (satu klik, per unit)

Format **harus persis** dokumen asli. Via document template engine (prinsip
#4). Data otomatis per unit; opname & catatan manual.

### 3.1 Jenis
| Jenis | Judul | No Dokumen |
|---|---|---|
| BA BBM HSD | "BERITA ACARA PEMERIKSAAN BAHAN BAKAR MINYAK : HSD (B35)" | SMT-FM-EPI-01.04 |
| BA BBM MFO | "BERITA ACARA PEMERIKSAAN BAHAN BAKAR MINYAK : MFO" | SMT-FM-EPI-01.04 |
| BA Opname Pelumas | "BERITA ACARA INVENTARISASI PEMERIKSAAN FISIK PELUMAS" | SMT-FM-EPI-01.04 |

### 3.2 Struktur BA BBM (HSD & MFO)
```
[Kop surat]
UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI
UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI
SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)

[Judul]                                     No. Dokumen : SMT-FM-EPI-01.04
BERITA ACARA                                Revisi      : 00
PEMERIKSAAN BAHAN BAKAR MINYAK : <HSD/MFO>  Tanggal     : <tgl revisi dok>
                                             Halaman     :

NO : <nomor surat — lihat 3.4>

Pada Hari ini <hari> Tanggal <terbilang> Bulan <bulan> Tahun <terbilang>
(<dd-mm-yyyy>) kami yang bertanda tangan di bawah ini menyatakan bahwa telah
diadakan pemeriksaan Bahan Bakar Minyak <HSD/MFO> pada <nama unit dari master>
dengan hasil sebagai berikut:

1. Persediaan Awal (per <tgl awal>)                        : ..... Liter
2. Penerimaan BBM (<HSD/MFO>)
   - Tanggal <awal> s/d <akhir> pukul 10.00                : ..... Liter
   Jumlah                                                   : ..... Liter
A. Jumlah Stock BBM <HSD/MFO>                               : ..... Liter
3. Pemakaian Mesin PLN (daftar mesin dinamis per unit)      : ..... Liter (tiap baris)
B. Jumlah Pemakaian (3)                                     : ..... Liter
C. Jumlah Pengiriman                                        : ..... Liter
D. Persediaan menurut Administrasi (A-B-C)                  : ..... Liter
Jumlah Persediaan menurut Fisik:
  <daftar tangki dinamis per unit>                          : ..... Liter (tiap tangki)
E. Jumlah Persediaan menurut Fisik                          : ..... Liter
F. Selisih Administrasi vs Fisik (E-D)                      : ..... Liter

Catatan: * Selisih disebabkan karena: <manual, kosong bila 0>

                                        Kendari, <tgl cetak>
Menyetujui,                            Membuat,
Manajer                                 TL. Operasi
<nama dari master pegawai>              <nama dari master pegawai>
```

### 3.3 Struktur BA Opname Fisik Pelumas (tabel per jenis pelumas)
```
[Kop surat sama]
BERITA ACARA INVENTARISASI PEMERIKSAAN FISIK PELUMAS
NO : <nomor surat — 3.4>
Pada hari ini ... (kalimat baku sama pola BA BBM) ...

| Jenis Pelumas | Persediaan Awal | Penerimaan | Stock | Pemakaian Sendiri |
  Pengiriman | Persediaan Administrasi | Stock Fisik (drum/cm/liter) |
  Selisih Fisik-Administrasi |
| A. Shell Diala B | ... |
| ... (jenis pelumas dinamis per unit) |
| JUMLAH TOTAL | ... |

Demikian Berita Acara ini dibuat untuk digunakan sebagaimana mestinya.
Catatan: * Selisih disebabkan karena: <manual>
                    Kendari, <tgl>
Menyetujui, Manajer <nama>     Membuat, TL. Operasi <nama>
```

### 3.4 Sumber data (otomatis vs manual)
| Bagian | Sumber |
|---|---|
| Persediaan awal | Auto = fisik BA periode lalu (unit sama, carry-over) |
| Penerimaan BBM/pelumas | Manual → `fuel_receipts`/`lubricant_receipts` (per unit) |
| Pemakaian mesin per unit | Auto, SUM `daily_engine_reports` unit+rentang |
| Persediaan Administrasi (A-B-C) | Auto |
| Stock fisik per tangki/drum | Manual (opname akhir periode) → `physical_stock_takes` |
| Selisih & catatan | Selisih auto; teks penyebab manual |
| **Nomor surat** | **Tetap/tidak berubah** per jenis dokumen. Simpan nomor baku tiap template di setting/`document_templates` (mis. BA HSD selalu "021/OPS/…", MFO "022/…", Pelumas "023/…"). JANGAN auto-increment. **Multi-unit belum diputuskan** apakah nomor sama untuk semua unit atau beda kode per unit → buat nomor ini sebagai **field editable per template + per unit** (default: pakai nilai contoh), sehingga user bisa set sendiri; beri catatan ini menunggu keputusan user. |
| Tanggal & terbilang | Auto (helper Indonesia) |
| Nama & jabatan TTD | Master Pegawai (jangan hardcode) |

### 3.5 Alur satu klik
1. TL Operasi pilih **unit** → jenis (HSD/MFO/Pelumas) → periode.
2. Sistem isi otomatis semua angka (kecuali stock fisik & catatan selisih).
3. **Preview** persis format §3.2/3.3 (PDF: cek library yang sudah dipakai di
   project via `CLAUDE.md`; bila belum ada, `dompdf` atau HTML→print-to-PDF).
4. Tombol **"Cetak/Unduh PDF"** langsung dari preview.
5. Simpan record dokumen (template, unit, periode, no surat, snapshot JSON,
   file PDF, dibuat/disetujui oleh, tanggal).
6. Akses `operasi.berita-acara.create` + hak unit.

---

## Definition of Done

- [ ] Baca `CLAUDE.md` & ikuti konvensi Laravel Boost.
- [ ] Tiap menu punya pemilih unit; data per `unit_id`; user hanya unit-nya.
- [ ] TIDAK ada tabel unit/mesin/pegawai baru — FK existing (tunjukkan tabel
      yang dipakai ke user).
- [ ] Kolom `fuel_type` & pelumas ditambahkan ke master mesin + ada UI untuk
      user melengkapinya per mesin.
- [ ] Menu hanya role TL Operasi; route ditolak 403 untuk role lain; RBAC
      berbasis kode permission.
- [ ] Arsitektur generik dibuktikan dengan 1 modul dummy kedua yang memakai
      ulang komponen tanpa mengubah kode modul Operasi.
- [ ] Grid input responsif seperti Excel (uji paste dari Excel asli).
- [ ] Jam operasi/HAR/gangguan dihitung dari Star-Stop, bukan input angka.
- [ ] Stand awal auto-carry, tak bisa diketik.
- [ ] Rumus kalkulasi hanya di satu service, dipakai grid+laporan+BA.
- [ ] Rumus Cummins IN/OUT terisolasi + berkomentar "asumsi, menunggu
      verifikasi".
- [ ] Menu Laporan: klik → preview tanpa halaman perantara.
- [ ] BA: 3 dokumen menghasilkan PDF yang tata letaknya cocok dgn sample asli
      (bandingkan ke user sebelum dianggap selesai).
- [ ] Nomor surat BA tetap sesuai setting per template (tidak auto-increment).
- [ ] Field manual (penerimaan BBM, stock fisik, catatan) ditandai beda dari
      field otomatis di UI.
