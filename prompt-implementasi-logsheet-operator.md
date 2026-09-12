# PROMPT UNTUK CLAUDE CODE — Logsheet Operator (Layer Input dalam Modul OPERASI)

> **Langkah 0:** baca `CLAUDE.md` (project pakai **Laravel Boost**). Lalu baca
> `modul-operasi.md` DAN `modul-operasi-addendum-logsheet-operator.md` (skema &
> aturan). Ini **BUKAN modul baru** — Logsheet adalah layer input operator DI
> DALAM modul OPERASI yang sudah ada. **Pakai ulang** fondasi generik (RBAC,
> `work_modules`, komponen grid, unit scoping) — jangan duplikasi. FK ke master
> existing (Unit Layanan, Mesin, Pegawai), jangan buat ulang.

Stack: **Laravel + Inertia + React + TypeScript + Tailwind + MySQL** (Laravel
Boost).

---

## Konteks & keputusan final (jangan ditanyakan ulang)

Bangun **fitur Logsheet Operator** di modul OPERASI. Operator mengisi
pembacaan parameter mesin **tiap jam** di lapangan; hasilnya kelak menyuapi
data harian TL Operasi.

- **Bagian dari modul OPERASI**, bukan modul terpisah.
- **Role baru `operator`** — hanya akses menu Logsheet. Tidak melihat menu TL
  Operasi (input harian, laporan, Berita Acara).
- **Satu logsheet per mesin per hari**; **form sama semua mesin** sekarang.
- **Variasi PLTM/PLTG disiapkan tapi belum diisi** — parameter lewat master
  `logsheet_parameters` ber-`plant_type`; sekarang semua pakai set `all`.
- **Slot waktu campuran**: 1:00–24:00 + slot 30-menit sore (17:30, 18:30,
  19:30, 20:30, 21:30). Jangan kunci 24 baris; simpan `time_slot` sebagai nilai.
- **Auto-agregasi ke `daily_engine_reports`: SIAPKAN integrasinya, JANGAN
  implementasi.** Service kosong + kolom `source`. TL Operasi tetap input
  manual untuk sekarang.
- **Multi-unit**: semua data ber-`unit_id`; operator hanya mesin di unitnya.

---

## Hak Akses

- Tambah role **`operator`** ke RBAC generik yang sudah ada (jangan hardcode).
- Permission: `operasi.logsheet.write` (operator isi), `operasi.logsheet.view`
  (TL Operasi & Manajer verifikasi).
- Operator TIDAK punya permission menu TL Operasi. Uji: operator akses route
  input harian/laporan/BA → 403.
- Scoping unit via `user_units` seperti role lain.

---

## Skema Database (ber-`unit_id` + `engine_id`)

Ikuti `modul-operasi-addendum-logsheet-operator.md` §3:

- `logsheet_parameters` — master kolom logsheet (code, name, unit_of_measure,
  sub_channel spt L1/L2/L3/R/S/T/IN/OUT/1/2, plant_type, sort_order). Seed set
  `all` sesuai parameter di bawah.
- `operator_logsheets` — 1 lembar = 1 mesin + 1 tanggal (unit_id, engine_id,
  log_date, operator_employee_id, shift, status draft|submitted, submitted_at).
  unique(engine_id, log_date).
- `operator_logsheet_readings` — nilai per (logsheet, time_slot, parameter).
  Model panjang (baris per nilai), fleksibel untuk parameter dinamis & slot
  30-menit.
- `daily_engine_reports` + kolom `source` (manual|logsheet), default `manual`.

**Parameter seed (plant_type='all'):** Load (kW); Coolant Temp (titik 1,2);
Lube Oil Temp; Lube Oil Press; Winding Temp (L1,L2,L3); TEG Battery; Ampere
(R,S,T); Cos Phi; Hour meter; Freq; TEG; KVAR; kWh Meter; Flow meter (IN,OUT);
Daily Tank Level; Bearing Generator Temperatur.

---

## Fitur — Menu Logsheet (untuk operator)

- Pilih **mesin + tanggal** (mesin dari master, difilter unit operator).
- **Grid mirip Excel:** baris = slot waktu (1:00–24:00 + slot 30-menit sore),
  kolom = parameter (di-generate dari `logsheet_parameters` set aktif). Pakai
  komponen grid reusable modul Operasi; navigasi keyboard Tab/Enter/panah +
  paste dari Excel.
- Slot waktu default dari template, tapi izinkan tambah slot bila perlu.
- **Draft → Submit**: simpan bertahap sebagai draft; submit saat lengkap
  (status berubah). Penguncian setelah submit — konfirmasi ke user (§konfirmasi).
- Validasi ringan (angka non-negatif; suhu/tekanan wajar) — jangan terlalu
  ketat (kondisi lapangan bervariasi).
- TL Operasi & Manajer punya tampilan **lihat** logsheet (read-only) untuk
  verifikasi.
- Endpoint: `operasi.logsheet.write` + cek hak unit.

---

## Integrasi auto-agregasi (SIAPKAN, JANGAN IMPLEMENTASI)

- Buat service `LogsheetAggregator` dengan method `aggregateToDailyReport
  (engineId, date)` bertanda `// TODO: implement` — TIDAK dipanggil di alur
  sekarang.
- Rencana (untuk komentar/dokumentasi di service, bukan kode aktif): beban
  puncak pagi/malam = MAX(Load) per rentang slot; stand kWh akhir = kWh Meter
  slot 24:00; jam operasi dari Hour meter; pemakaian BBM dari Flow meter IN/OUT.
- Kolom `daily_engine_reports.source` disiapkan (default `manual`); jalur
  `logsheet` belum diaktifkan.
- Jangan ubah alur input manual TL Operasi yang sudah ada.

---

## Dukungan PLTM/PLTG (siapkan, jangan isi)

- `plant_type` di `logsheet_parameters` memungkinkan set parameter berbeda per
  jenis pembangkit. Sekarang hanya seed `all`.
- Jika master unit/mesin existing belum punya penanda jenis pembangkit, **beri
  catatan** bahwa nanti perlu kolom `plant_type` di master — jangan buat
  sekarang; cukup siapkan pemetaan set parameter berdasarkan jenis.

---

## Definition of Done

- [ ] Baca `CLAUDE.md` + kedua dokumen modul; ikuti Laravel Boost.
- [ ] Logsheet berada di dalam modul OPERASI (bukan modul baru); pakai ulang
  fondasi (RBAC, grid, unit scoping).
- [ ] Role `operator` sudah ada; hanya akses menu Logsheet; route TL Operasi
  untuk operator → 403.
- [ ] TIDAK ada tabel unit/mesin/pegawai baru — FK existing.
- [ ] Parameter logsheet dari master `logsheet_parameters` (bukan hardcode
  kolom); seed set `all` sesuai daftar parameter.
- [ ] Grid slot waktu mendukung jam bulat + slot 30-menit sore; tidak mengunci
  24 baris.
- [ ] Draft/Submit berfungsi; TL Operasi & Manajer bisa melihat (read-only).
- [ ] `LogsheetAggregator` ada sebagai service kosong (TODO), tidak dipanggil;
  kolom `source` ada di `daily_engine_reports` (default manual).
- [ ] Alur input manual TL Operasi tidak berubah.
- [ ] Grid responsif seperti Excel (uji paste dari Excel asli).

---

## Konfirmasi ke user sebelum coding

1. Setelah submit, logsheet dikunci dari edit operator (perlu TL untuk buka)?
2. Nama operator/shift dicatat per lembar?
3. Master mesin existing sudah punya penanda jenis pembangkit (PLTD/PLTM/PLTG/
   containerized)? (menentukan variasi form nanti)
4. Slot 30-menit sore (17:30–21:30) sama untuk semua unit, atau bisa beda?