# Spesifikasi Modul OPERASI — Aplikasi Pelaporan Pembangkit (Multi-Unit)

> Panduan implementasi untuk Claude Code, hasil reverse-engineering file Excel
> `8__AGUSTUS_2026.xlsx` (MASTER LAPORAN OPERASI, contoh data unit **PLTD
> Poasia**). Baca dokumen ini lebih dulu, lalu `prompt-implementasi-operasi.md`.
>
> **Langkah 0 sebelum coding:** baca `CLAUDE.md` di root project (project ini
> memakai **Laravel Boost** — ikuti konvensi, tooling, dan panduan di sana).
> Lalu periksa migration/model existing untuk master **Unit Layanan**, **Mesin**,
> dan **Pegawai** (lihat §0.1).

---

## 0. Gambaran Besar (WAJIB dipahami sebelum coding)

Aplikasi pelaporan pembangkit **multi-unit & multi-modul**.

- **Multi-unit:** mengelola banyak unit pembangkit; PLTD Poasia hanya satu
  contoh. Unit lain punya mesin, tangki, jenis pelumas, dan data bahan bakar
  berbeda. Semua data operasi di-scope per `unit_id`.
- **Multi-modul:** tiap fungsi kerja = satu modul berpola sama (input → rekap
  otomatis → cetak). Dokumen ini hanya modul **OPERASI**; modul lain menyusul
  (arsitektur generik dibahas di dokumen prompt).
- **Mental model user: "satu sheet Excel = satu menu"** — struktur workbook =
  blueprint navigasi (§2).

### 0.1 KRITIS — Master existing: JANGAN dibuat ulang

Aplikasi sudah punya master **Unit Layanan**, **Mesin**, dan **Pegawai**.
Modul ini **wajib FK ke tabel existing**, dilarang membuat ulang.

1. **Periksa dulu** migration/model untuk menemukan nama & PK tabel-tabel ini
   (kemungkinan `unit_layanans`/`units`, `mesins`/`engines`, `pegawais`/
   `employees` — pakai yang SEBENARNYA ada; bila ragu, tanyakan user).
2. Semua sebutan `unit`, `engine`/mesin, `pegawai` di dokumen ini adalah
   **placeholder** → petakan ke tabel existing. Kolom FK ditulis `unit_id`,
   `engine_id`, `employee_id` sebagai konvensi; sesuaikan bila beda.
3. **Identitas unit** (Wilayah, Sektor, Nama Unit, Lokasi, Kode Sentral)
   **sudah ada di master Unit Layanan** — ambil dari sana untuk kop laporan &
   Berita Acara. Jangan simpan ulang.
4. **Penandatangan** (Manajer, TL Operasi) **diambil dari master Pegawai** —
   jangan hardcode nama.
5. **Jenis bahan bakar & jenis pelumas per mesin BELUM ADA** di master mesin.
   → Modul ini menambahkan kolom `fuel_type` (`hsd_mfo` | `hsd_only`) dan
   relasi jenis pelumas ke master mesin existing (via migration `ALTER`,
   bukan tabel mesin baru). **Sebutkan di UI/instruksi bahwa user perlu
   melengkapi data ini per mesin** setelah kolom tersedia (form edit mesin:
   pilih jenis bahan bakar + jenis pelumas). Jangan asumsikan nilainya.

### 0.2 KRITIS — Semua data operasi di-scope per UNIT

Setiap tabel transaksi & master turunan modul ini **wajib `unit_id`** (FK ke
master unit existing). Data Poasia & unit lain di tabel sama, dipisah
`unit_id`. Feeder, tangki, pelumas, faktor kalibrasi, pembacaan harian — semua
per unit.

### 0.3 Temuan arsitektur Excel

File ini **konsolidasi bulanan satu unit**, bukan file input harian.

```
File harian per tanggal        File bulan lalu       Sumber input manual asli
"PLTD DDMM'YY.xlsx"           (stand akhir bln lalu)  Star-Stop, Penerimaan BB,
  sheet ISIAN DATA, START-STOP                        pembacaan meter
        └──────────────┬──────────────────────────────────────┘
                       ▼
    Sheet "DT INP" — 157 kolom, ~95% FORMULA = REKAP, bukan input
                       ▼
    Sheet turunan (STANDKWH, SFC, KWH, JGG, JHAR, MOH…) = hasil hitung
                       ▼
    Berita Acara & laporan resmi PLN (sheet bernomor 1–14)
```

Yang benar-benar diketik manusia jauh lebih sedikit dari 157 kolom. Sumber
input asli: **Star-Stop** (log start/stop — sheet menandai sendiri kolom
"diisi oleh unit" vs "otomatis"), **Penerimaan BB**, **pembacaan meter
harian** + opname fisik. Sisanya kalkulasi → backend.

---

## 1. Data Umum (patokan) — sheet `DT UM`

Data per periode pelaporan per unit → tabel `report_periods` (ber-`unit_id`).
Identitas unit diambil dari master Unit Layanan (§0.1), tidak diduplikasi.

- Periode: Bulan, Tahun, Jumlah Hari, Jumlah Jam (hari×24) — men-drive jumlah
  baris tanggal & judul.
- Penanggung jawab laporan: relasi ke Pegawai (jabatan Manajer).
- Legenda warna tab (kunci klasifikasi §2): kuning=DIISI, cyan=DIPRINT,
  hijau=DIISI+DIPRINT, merah=hak akses Sektor.

---

## 2. Peta Sheet → Menu → Sifat

Dari warna tab Excel + verifikasi rasio formula. **Draft — tampilkan ke user
untuk dikoreksi sebelum dikunci.**

### 2.1 HARUS DIISI (input manual)
| Sheet | Peran |
|---|---|
| `DT UM` | Setting periode & data umum (1×/bulan/unit) |
| `Star-Stop PLTD Poasia` | **Log start/stop mesin** — sumber jam operasi/HAR/gangguan |
| `CTRL OLI` | Kontrol/pemakaian pelumas harian |
| `CTRL BBM` | Kontrol BBM harian |
| `8. PENERIMAAN BB` | **Register penerimaan BBM** dari pemasok |
| `kWh EDMI`, `JM OPS` | Pembacaan meter EDMI & jam (sebagian input) |

> `DT INP` bertab kuning tapi ~95% formula → di aplikasi jadi **rekap/cetak**,
> bukan input. Bukti klasifikasi warna perlu koreksi manusia.

### 2.2 HARUS DIPRINT (laporan, tanpa input)
`DT INP`, `STANDKWH BRUTO/EDMI/NETTO PLNT/OUTGOING PLNT/BRUTO PLNT/MTR FEEDER`,
`EVIDEN EDMI`/`PLNT`, `SFC`, `KWH`, `KWHPS`, `HSD`, `PELUMAS MESIN`,
`PELUMAS TRAFO`, `JOPS`, `JOPS PELUMAS`, `JGG`, `JHAR`, `MOH`, `SLC`, `FJK
ENGINE`/`25`. → Dihitung otomatis dari §2.1 (formula 87–95%). Menu Laporan
sekali klik.

### 2.3 HARUS DIISI DAN DIPRINT (dokumen resmi)
`BERITA ACARA HSD`, `BERITA ACARA MFO`, `BA OPNAME FISIK PELUMAS`. → Angka
otomatis; stock fisik opname + catatan selisih manual, lalu cetak.

### 2.4 Laporan resmi PLN (FASE BERIKUTNYA — jangan dulu)
Sheet `1. EXSUM` … `14. KWH` + `13. REALPROD`. Siapkan report registry agar
mudah ditambah; implementasi menyusul. `Lembar1/2` kosong → abaikan.

---

## 3. Mesin unit PLTD Poasia (data contoh — cocokkan ke master existing)

| Nama Mesin | fuel_type | Pelumas | Catatan |
|---|---|---|---|
| MIRRLEES #1–#5 ESL 16 MK 2 | hsd_mfo | Argina T30 | 5 unit |
| CUMMINS EX BAUBAU 1–2 | hsd_only | Meditran/Trafolube | flow meter IN/OUT |
| CUMMINS EX PASARWAJO 4 | hsd_only | Meditran/Trafolube | idem |

> Unit containerized (Cummins 1–10) & lokasi "plnt" = **unit terpisah**, bukan
> bagian Poasia. Jangan campur ke unit Poasia; mereka jadi `unit_id` sendiri.
>
> `fuel_type` & pelumas di atas adalah nilai yang BENAR untuk Poasia, tapi
> karena master mesin belum punya kolomnya, user akan mengisinya lewat form
> edit mesin setelah kolom ditambahkan (§0.1 poin 5).

**Kode status mesin (Star-Stop):** `RSH`, `FO`, `MOH`, dll → menentukan jam
masuk kategori operasi/HAR/gangguan/standby. Master `unit_status_codes`
**global** (dipakai semua unit), `unit_id` nullable untuk override. **Daftar
lengkap kode + arti + kategori belum final → sediakan CRUD master ini agar
user melengkapi sendiri**; seed awal RSH/FO/MOH dengan kategori sebagai
placeholder yang bisa diedit.

---

## 4. Skema Database (Laravel + MySQL) — semua ber-`unit_id`

### 4.1 Perubahan ke master existing (ALTER, bukan tabel baru)
```
mesin (existing)  + fuel_type ENUM('hsd_mfo','hsd_only') NULL
                  + relasi ke lubricant_types (nullable) atau kolom penanda
                    pelumas — sesuaikan gaya relasi yang sudah dipakai project
```

### 4.2 Master turunan modul (per unit)
```
report_periods       id, unit_id, month, year, total_days, total_hours,
                     pic_employee_id (FK pegawai), locked_at
                     unique(unit_id, month, year)
feeders              id, unit_id, name, feeder_type, is_active
auxiliary_sources    id, unit_id, name, description, is_active
fuel_tanks           id, unit_id, code, name, fuel_type (hsd|mfo),
                     capacity_liter, is_daily_tank
lubricant_types      id, unit_id, code, name, unit_of_measure (drum|liter), sort_order
calibration_factors  id, unit_id, engine_id (nullable), factor_type (kwh|hsd|mfo),
                     value (decimal presisi tinggi), effective_date, notes
unit_status_codes    id, unit_id (nullable=global), code, label,
                     category (operasi|har|gangguan|standby)
```

### 4.3 Input harian (ber-`unit_id`)
```
engine_status_logs    -- Star-Stop; SUMBER JAM (jam dihitung dari sini)
  id, unit_id, engine_id, report_date, status_code_id,
  operator_name, dispatcher_name, start_datetime, stop_datetime,
  duration_minutes (auto), keterangan, input_by, timestamps

daily_engine_reports  -- 1 baris = 1 mesin + 1 tanggal (pembacaan meter)
  id, unit_id, engine_id, report_date,
  kwh_produksi_stand_akhir, kwh_pakai_sendiri_stand_akhir,
  beban_puncak_pagi_kw, beban_puncak_malam_kw,     -- 2 angka saja
  pemakaian_pelumas_liter,
  flowmeter_hsd_stand_akhir, flowmeter_hsd_tambah_liter,
  flowmeter_mfo_stand_akhir (nullable), flowmeter_mfo_tambah_liter (nullable),
  air_pps_stand_akhir (nullable), air_softener_stand_akhir (nullable),
  catatan, input_by, verified_by, verified_at, timestamps
  unique(engine_id, report_date)
  -- stand_awal & jam TIDAK disimpan (auto; jam dari engine_status_logs)

daily_feeder_readings id, unit_id, feeder_id, report_date, stand_akhir,
                      is_active_today, input_by, timestamps; unique(feeder_id, report_date)
daily_auxiliary_readings id, unit_id, auxiliary_source_id, report_date,
                      stand_kwh_akhir, stand_bbm_akhir, input_by, timestamps
fuel_receipts         id, unit_id, report_date, fuel_type, supplier, do_number,
                      unloading_date, volume_liter, calorie_value, price_per_liter,
                      transport_cost, surveyor_cost, keterangan, input_by, timestamps
lubricant_receipts    id, unit_id, report_date, lubricant_type_id, volume,
                      do_number, input_by, timestamps
physical_stock_takes  id, unit_id, report_period_id, item_type (fuel|lubricant),
                      tank_id (nullable), lubricant_type_id (nullable),
                      physical_qty_liter, physical_drum, physical_cm, input_by, timestamps
```

---

## 5. Aturan Bisnis & Kalkulasi (backend, difilter per unit)

1. **Carry-over stand awal:** `stand_awal(N)=stand_akhir(N-1)`; tgl 1 = stand
   akhir hari terakhir bulan lalu (mesin sama). Berlaku kWh produksi, kWh
   pemakaian sendiri, flow meter HSD/MFO, air, feeder.
2. **kWh Produksi:** `(stand_akhir-stand_awal)×faktor_kali_kwh`.
3. **Pemakaian BBM:** `(fm_akhir-fm_awal+tambah)×faktor_kali` (faktor per
   unit/mesin dari `calibration_factors`).
4. **SFC:** netto=`total_bbm/(kwh_prod-kwh_ps)`; bruto=`total_bbm/kwh_prod`.
5. **Jam dari Star-Stop:** durasi start→stop dikelompokkan per
   `unit_status_codes.category` → operasi/HAR/gangguan;
   `standby = jam_hari - (operasi+har+gangguan)`.
6. **Subtotal periode:** I=1–10, II=11–20, III=21–akhir; Total=jumlah.
7. **Persediaan BBM (BA):** `awal+terima-pakai-kirim=administrasi`;
   `selisih=fisik-administrasi`. Stok awal=fisik BA periode lalu.
8. **Cummins flow meter IN/OUT (default aman — TANDAI untuk diverifikasi
   user):** pemakaian = `(IN_akhir - IN_awal) - (OUT_akhir - OUT_awal)`,
   yaitu BBM masuk engine dikurangi yang kembali. Buat perhitungan ini
   **terisolasi & mudah diganti** (satu method khusus), beri komentar jelas
   bahwa rumus ini asumsi sementara dan menunggu konfirmasi tim operasi.
9. **Validasi:** total jam/hari sesuai `report_periods`; stand akhir ≥ awal
   (opsi "reset meter" eksplisit); field MFO hanya untuk mesin `hsd_mfo`.

---

## 6. Anti-pattern

- ❌ Membuat ulang master unit/mesin/pegawai — WAJIB FK existing.
- ❌ Tabel/kalkulasi tanpa `unit_id`.
- ❌ Form 157 kolom meniru `DT INP` (itu rekap).
- ❌ Simpan hasil kalkulasi (SFC, standby, total) sebagai input.
- ❌ Hardcode faktor kalibrasi, feeder, tangki, pelumas, kode status, nama
  penandatangan.
- ❌ Ketik jam operasi/HAR/gangguan langsung (turunkan dari Star-Stop).

---

## 7. Seed data unit Poasia (verifikasi ke user)

- **Feeder Poasia:** Andonohu, Lapuko, Teluk, Express, Express 5, PPS, Tie
  Line (F Express), Boulevard, Nii Tanasa, DS Coupling, Kubra, Gubernur,
  Andonohu Baru, Coupling Andonohu, Arena Mitsubishi 5/6, Arena Cummins.
  (Bersihkan duplikat penulisan.)
- **Pelumas Poasia:** Shell Diala B, Thermo XT 32, Meditran SX CH-4, Shell
  Argina S3, Trafolube A, Total Aurelia TI3030.
- **Kode status:** RSH, FO, MOH (placeholder; user lengkapi via CRUD).
- **Tangki Poasia:** HSD (1500 Ton×1, 90 Ton×1, 3.5 KL×2, daily tank), MFO
  (1500 Ton×3, 10 KL×1, 5 KL×2) — konfirmasi persis.

> Seeder per unit — unit lain punya daftar sendiri.
