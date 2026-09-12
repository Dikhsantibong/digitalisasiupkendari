# ADDENDUM Modul OPERASI — Logsheet Operator (Input Lapangan Per Jam)

> Tambahan untuk `modul-operasi.md` dan `prompt-implementasi-operasi.md`.
> Sumber: `POASIA_CONTAINER_.xlsx` (sheet Logsheet, Cummins Containerized
> Poasia). **Bukan modul baru** — ini layer INPUT OPERATOR di dalam modul
> OPERASI, yang menyuapi data harian TL Operasi.
>
> **Langkah 0:** baca `CLAUDE.md` (Laravel Boost) + `modul-operasi.md` dulu.
> FK ke master existing (Unit Layanan, Mesin, Pegawai). Pakai ulang fondasi
> generik (RBAC, grid, dll.) — jangan duplikasi.

---

## 0. Posisi dalam arsitektur

```
OPERATOR (sudah ada)                    TL OPERASI (sudah ada)
  isi Logsheet per JAM       →(nanti)→    data harian ter-agregat
  (1 lembar / mesin / hari)  auto        (stand akhir, beban puncak
                                          pagi & malam, jam operasi)
                                             → laporan & Berita Acara
```

- **Operator** mengisi logsheet parameter mesin **tiap jam** di lapangan.
- **TL Operasi** (modul Operasi existing) memakai hasilnya untuk laporan
  bulanan. Untuk SEKARANG, TL Operasi tetap input harian manual seperti di
  `modul-operasi.md`; auto-agregasi dari logsheet **disiapkan tapi belum
  diimplementasi** (§4).

---

## 1. Keputusan final (jangan ditanyakan ulang)

- **Bagian dari modul OPERASI**, bukan modul terpisah.
- **Satu logsheet per mesin per hari.** Setiap mesin punya 1 lembar; **form
  sama untuk semua mesin** sekarang.
- **Siapkan variasi untuk PLTM & PLTG** (jenis pembangkit berbeda punya
  parameter berbeda) — **struktur disiapkan fleksibel, tapi untuk sekarang
  semua unit pakai form yang sama** (jenis PLTD/containerized). Jangan bikin
  parameter khusus PLTM/PLTG dulu; cukup pastikan skema mendukungnya nanti
  (§3.3).
- **Role baru: `operator`** — hanya mengisi logsheet, tidak mengakses menu TL
  Operasi.
- **Auto-agregasi ke data TL Operasi: SIAPKAN INTEGRASINYA, JANGAN
  IMPLEMENTASI** sekarang (§4).

---

## 2. Struktur Logsheet (dari Excel)

- **Header:** Mesin (FK master mesin), Hari/Tanggal, (opsional) nama operator/
  shift.
- **Baris = jam.** Jam 1:00–24:00, DITAMBAH pembacaan tiap 30 menit pada jam
  beban puncak sore: 17:30, 18:30, 19:30, 20:30, 21:30. Artinya slot waktu
  **tidak selalu tiap jam bulat** → simpan slot waktu sebagai nilai, jangan
  asумsikan 24 baris tetap.
- **Kolom = parameter dibaca per slot:**

| Parameter | Satuan | Sub-kolom |
|---|---|---|
| Load | kW | — |
| Coolant Temp. | °C | titik 1, 2 |
| Lube Oil Temp. | °C | — |
| Lube Oil Press. | bar | — |
| Winding Temp | °C | L1, L2, L3 |
| TEG Battery | V | — |
| Ampere | A | R, S, T |
| Cos Phi | — | — |
| Hour meter | jam | — |
| Freq | Hz | — |
| TEG | V | — |
| KVAR | kVAR | — |
| kWh Meter | kWh | — |
| Flow meter | L | IN, OUT |
| Daily Tank Level | — | — |
| Bearing Generator Temperatur | °C | blok terpisah, per jam |

> **Catatan penting — Flow meter IN/OUT:** operator mencatat IN & OUT tiap
> slot. Ini menjawab pertanyaan yang tadi ditandai "asumsi menunggu verifikasi"
> di `modul-operasi.md` §5.8 — pemakaian BBM Cummins memang berbasis IN/OUT.
> Setelah addendum ini, rumus IN/OUT bisa dikaitkan ke data logsheet nyata.

---

## 3. Skema Database (ber-`unit_id` + `engine_id`)

### 3.1 Definisi parameter (generik, agar PLTD/PLTM/PLTG bisa beda kelak)
```
logsheet_parameters   -- master kolom logsheet (apa yang dibaca)
  id, plant_type (pltd|pltm|pltg|containerized|all), code, name, unit_of_measure,
  sub_channel (nullable: 'L1','L2','L3','R','S','T','IN','OUT','1','2'),
  sort_order, is_active
  -- SEKARANG: seed satu set parameter (plant_type='all') sesuai §2.
  -- NANTI: tambah baris ber-plant_type utk PLTM/PLTG tanpa ubah skema.
```

### 3.2 Data logsheet
```
operator_logsheets    -- 1 lembar = 1 mesin + 1 tanggal
  id, unit_id, engine_id, log_date, operator_employee_id (FK pegawai, nullable),
  shift (nullable), status (draft|submitted), submitted_at,
  input_by, timestamps
  unique(engine_id, log_date)

operator_logsheet_readings   -- nilai per slot waktu per parameter
  id, logsheet_id (FK), time_slot (time, mis. 17:30),
  parameter_id (FK logsheet_parameters), value (decimal, nullable),
  note (nullable)
  index(logsheet_id, time_slot)
  -- model panjang (baris per nilai) lebih fleksibel utk parameter dinamis
  --   & slot 30-menit daripada 1 kolom per parameter.
```

### 3.3 Dukungan PLTM/PLTG (siapkan, jangan isi sekarang)
- `plant_type` di `logsheet_parameters` + (idealnya) jenis pembangkit di master
  Unit/Mesin existing menentukan set parameter yang dirender. Jika master belum
  punya jenis pembangkit, **beri catatan** bahwa nanti perlu kolom
  `plant_type` di master unit — jangan buat sekarang, cukup siapkan
  pemetaannya. Untuk sekarang semua unit → set parameter `all`.

---

## 4. Integrasi ke data TL Operasi (SIAPKAN, JANGAN IMPLEMENTASI)

Tujuan akhir: nilai di `daily_engine_reports` (modul Operasi) bisa **ditarik
otomatis** dari logsheet, misalnya:
- `beban_puncak_pagi_kw` = MAX(Load) pada slot pagi; `beban_puncak_malam_kw`
  = MAX(Load) pada slot malam.
- `kwh_produksi_stand_akhir` = kWh Meter pada slot 24:00.
- jam operasi = dari Hour meter / durasi mesin jalan.
- flow meter HSD IN/OUT → pemakaian BBM.

**Yang dibangun sekarang:**
- Bungkus rencana agregasi di **satu service kosong** `LogsheetAggregator`
  dengan method bertanda `// TODO: implement` (mis. `aggregateToDailyReport
  (engineId, date)`), dipanggil dari NANTI — tidak dipakai di alur sekarang.
- Beri kolom penanda di `daily_engine_reports` (mis. `source` =
  `manual` | `logsheet`) agar kelak bisa dibedakan. Default `manual`.
- **Jangan** aktifkan jalur otomatis; TL Operasi tetap input manual. Dokumen
  ini hanya memastikan pintu integrasinya ada.

---

## 5. Hak Akses

- **Role baru `operator`:** hanya menu Logsheet (isi lembar mesin yang jadi
  tanggung jawabnya). Permission `operasi.logsheet.write`.
- Operator **tidak** melihat menu TL Operasi (input harian, laporan, BA).
- TL Operasi & Manajer bisa **melihat** logsheet (`operasi.logsheet.view`)
  untuk verifikasi. Scoping unit via `user_units` seperti role lain.
- RBAC generik yang sudah ada — cukup tambah role `operator` + permission,
  jangan hardcode.

---

## 6. Fitur — Menu Logsheet (untuk operator)

- Pilih **mesin + tanggal** (mesin dari master, difilter unit operator).
- **Grid mirip Excel:** baris = slot waktu (1:00–24:00 + slot 30-menit sore),
  kolom = parameter (§2). Pakai komponen grid reusable (dari modul Operasi),
  navigasi keyboard + paste. Slot waktu bisa tetap (template) tapi izinkan
  tambah slot bila perlu.
- Simpan sebagai draft, lalu **submit** saat lembar lengkap (status berubah;
  bisa dikunci dari edit setelah submit — konfirmasi ke user).
- Validasi ringan (angka non-negatif, tekanan/suhu wajar) — jangan terlalu
  ketat karena kondisi lapangan bervariasi.
- Endpoint: `operasi.logsheet.write` + cek hak unit.

---

## 7. Anti-pattern

- ❌ Membuat modul baru terpisah — ini bagian modul OPERASI.
- ❌ Hardcode daftar parameter jadi kolom tabel — pakai `logsheet_parameters`
  (agar PLTM/PLTG bisa beda kelak).
- ❌ Mengunci 24 baris jam — ada slot 30-menit; simpan time_slot sebagai nilai.
- ❌ Mengimplementasi auto-agregasi ke daily report sekarang — hanya siapkan
  service kosong + kolom `source`.
- ❌ Membuat ulang master mesin/pegawai — FK existing.

---

## 8. Konfirmasi ke user

1. Setelah submit, logsheet dikunci dari edit operator (perlu approve TL untuk
   buka)?
2. Nama operator/shift perlu dicatat per lembar?
3. Master mesin existing sudah punya penanda jenis pembangkit (PLTD/PLTM/PLTG/
   containerized)? Bila belum, ini yang nanti dibutuhkan untuk variasi form.
4. Slot waktu 30-menit sore (17:30–21:30) apakah sama untuk semua unit, atau
   bisa beda?
