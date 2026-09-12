# PROMPT UNTUK CLAUDE CODE — Modul PEMELIHARAAN (HAR): Input, Laporan, Executive Summary

> **Langkah 0:** baca `CLAUDE.md` (project pakai **Laravel Boost**). Lalu baca
> `modul-har.md` (skema data & aturan bisnis). Prompt ini melengkapinya dengan
> fitur, hak akses, dan format output. Modul HAR SEJAJAR dengan modul OPERASI —
> **pakai ulang** semua fondasi generik yang sudah dibangun modul Operasi (RBAC,
> `work_modules`, document template engine, report registry, komponen grid).

Stack: **Laravel + Inertia + React + TypeScript + Tailwind + MySQL** (Laravel
Boost).

---

## Konteks & keputusan final (jangan ditanyakan ulang)

Bangun **modul PEMELIHARAAN (HAR)** — laporan bulanan TL Pemeliharaan.

- **Multi-unit.** Format laporan sama semua unit; data mesin & nama unit beda.
  Tiap menu punya pemilih unit; semua data per `unit_id`.
- **Master Unit Layanan, Mesin, Pegawai SUDAH ADA** → FK, jangan buat ulang.
- **Master pemeliharaan BELUM ADA** → modul ini buat CRUD:
  `maintenance_types` (PM/PdM/CM/FLM/ENJI), `maintenance_cycles` (P1/P2/P4),
  `wo_statuses`, `work_groups`, `sr_categories`.
- **Sumber WO/SR = INPUT MANUAL sekarang**, tapi WAJIB dibungkus interface
  `WorkOrderSource` agar bisa disambung ke **database WPC** (`192.168.3.85/
  wpc-ditgas`) nanti tanpa mengubah controller/laporan. Kolom `source`
  (manual|wpc) di tabel WO/SR. Jangan hardcode koneksi WPC sekarang.
- **Akses:** role **TL HAR** (isi + lihat) dan **Manajer** (lihat saja).
- **Upload foto** per pekerjaan penting → perlu (lampiran).
- **No. Dokumen ISO** (FMKD-314-…) tetap per jenis sheet, editable di setting
  (bukan auto-generate) — pola sama dengan Berita Acara.
- **Biaya:** default rinci per WO lalu dijumlah; sediakan juga override total
  manual per bulan.

Aplikasi ini multi-modul — ikuti prinsip arsitektur generik yang sudah ada.

---

## Hak Akses

- **TL HAR:** akses penuh modul HAR (isi + lihat).
- **Manajer:** hanya lihat/cetak (read-only), tidak bisa mengubah data.
- RBAC generik (pakai yang sudah ada dari modul Operasi): permission
  `har.input.write`, `har.laporan.view`, `har.executive.view`. Manajer dapat
  `*.view` saja. Cek via kode permission, bukan nama role.
- Scoping unit via `user_units` (TL HAR memegang unit tertentu). Manajer bisa
  lihat lintas unit bila memang berwenang — konfirmasi cakupan Manajer ke user.

---

## Fitur 1 — Menu Input (per unit)

Karena data HAR berbasis daftar (bukan angka harian), inputnya campuran
**grid/tabel** (untuk daftar WO/SR) dan **form** (untuk log kegiatan & foto).

### 1.1 Work Order & Service Request
- Grid daftar WO (kolom: WONUM, deskripsi, jenis (PM/PdM/CM/ENJI), mesin, work
  group, status, report date, sched start/finish, biaya jasa/material). Bisa
  tambah baris manual + (nanti) tarik dari WPC. Paste dari Excel didukung
  (pola grid sama modul Operasi).
- Grid daftar SR (kolom: no SR, deskripsi, kategori, status open/close, mesin).
- Semua lewat interface `WorkOrderSource` (sekarang implementasi manual).

### 1.2 Log Kegiatan HARMES (inti)
- Form per aktivitas: tanggal, mesin, jenis HAR, hasil pekerjaan, no WO/SR/
  LH-05/TUG-9, keterangan.
- Sub-daftar **uraian kegiatan** (multi-baris) dan **material terpakai** (nama,
  no part, jumlah, satuan).

### 1.3 Biaya
- Input rinci per WO (jasa + material) — terhubung ke grid WO.
- Opsi override: input total Jasa & Material per bulan (fallback).

### 1.4 Rencana vs Realisasi
- Matriks per mesin (scope: HAR / Pelumas / Air), rencana & realisasi.
- Simpan sebagai struktur fleksibel (JSON) — konfirmasi granularitas ke user.

### 1.5 Lampiran foto
- Upload foto per pekerjaan (kaitkan ke WO/aktivitas/mesin), dengan judul,
  caption, tanggal. Pakai storage Laravel; simpan path.

- Semua endpoint input: permission `har.input.write` + cek hak unit.

---

## Fitur 2 — Menu Laporan (satu klik, per unit)

1. Pemilih **unit + periode (bulan/tahun)**.
2. Laporan mengikuti struktur baku Excel (modul §1): SR Summary, WO Summary,
   Akumulasi Biaya, Rekap WO Task, WO PM/PdM/CM/ENJI, WO Waiting, Rencana vs
   Realisasi, Log Kegiatan HARMES, Lampiran foto. Dihitung otomatis dari data
   Fitur 1 (rumus di modul §4), lewat **report registry**.
3. Tiap laporan tampil dengan **kop + No. Dokumen ISO** yang benar.
4. Tombol **"Lihat & Cetak"** → satu klik preview → Unduh PDF/Cetak. Tanpa
   halaman perantara. Laporan bisa dicetak per bagian atau **satu dokumen
   penuh** (gabungan semua bagian + lampiran foto).
5. Akses `har.laporan.view` (TL HAR & Manajer).

---

## Fitur 3 — Executive Summary (satu klik, otomatis)

1. Pilih unit + periode → sistem **rangkum otomatis**: jumlah SR per kategori,
   WO per jenis, % complete, total biaya (jasa+material) & akumulatif, daftar
   WO open/tertunda, highlight pekerjaan penting.
2. Preview + cetak PDF sekali klik.
3. Akses `har.executive.view` (TL HAR & Manajer).

---

## Prioritas implementasi (urutan disarankan)

1. Master pemeliharaan (CRUD) + skema DB + RBAC/unit scoping.
2. Input Work Order & Service Request (lewat interface `WorkOrderSource`).
3. Input Log Kegiatan HARMES + material + foto.
4. Menu Laporan (rekap WO/SR + biaya) sekali klik.
5. Rencana vs Realisasi.
6. Executive Summary otomatis.
7. (Fase lanjut) Integrasi database WPC — implementasi `WpcWorkOrderSource`.

---

## Definition of Done

- [ ] Baca `CLAUDE.md`, ikuti Laravel Boost.
- [ ] Pakai ulang fondasi modul Operasi (RBAC, work_modules, document engine,
      report registry, grid) — tidak menduplikasi.
- [ ] Tiap menu punya pemilih unit; data per `unit_id`; format laporan sama
      antar unit, data mesin/unit ikut unit terpilih.
- [ ] TIDAK ada tabel unit/mesin/pegawai baru — FK existing.
- [ ] Master pemeliharaan (types/cycles/statuses/work groups/SR categories)
      dibuat + CRUD.
- [ ] Semua akses WO/SR lewat interface `WorkOrderSource`; ada
      `ManualWorkOrderSource` (aktif) + placeholder `WpcWorkOrderSource`;
      kolom `source` ada. Tidak ada koneksi WPC hardcoded.
- [ ] TL HAR bisa isi + lihat; Manajer hanya lihat/cetak (uji: Manajer tak
      bisa POST/PUT data → ditolak).
- [ ] Upload foto lampiran berfungsi (storage, bukan blob).
- [ ] No. Dokumen ISO tampil benar & editable di setting (tidak auto-generate).
- [ ] Laporan sekali klik → preview → PDF; bisa cetak per bagian & dokumen penuh.
- [ ] Executive Summary terangkum otomatis dari data.
- [ ] Biaya: default per-WO dijumlah; override total manual tersedia & ditandai.

---

## Konfirmasi ke user sebelum coding

1. Nama & PK tabel master Unit Layanan, Mesin, Pegawai existing (sama seperti
   modul Operasi — pastikan konsisten).
2. Cakupan akses Manajer: lihat semua unit, atau unit tertentu saja?
3. Daftar lengkap status WO dari WPC (baru terlihat APPR, CLOSE) + kategori SR
   + siklus (P1/P2/P4 + lainnya).
4. Matriks Rencana vs Realisasi: perlu granular per-tanggal (31 hari) atau
   cukup ringkasan per mesin/bulan?
5. Detail teknis WPC untuk fase integrasi nanti (jenis DB, skema, akses) —
   tidak sekarang, tapi tandai sebagai kebutuhan fase 2.
