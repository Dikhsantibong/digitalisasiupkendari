# PROMPT UNTUK CLAUDE CODE — Modul K3 & KEAMANAN: Input, Laporan, Monitoring

> **Langkah 0:** baca `CLAUDE.md` (project pakai **Laravel Boost**). Lalu baca
> `modul-k3.md` (skema data & aturan bisnis). Prompt ini melengkapinya dengan
> fitur, hak akses, dan format output. Modul K3 SEJAJAR dengan modul OPERASI &
> HAR — **pakai ulang** fondasi generik yang sudah ada (RBAC, `work_modules`,
> document template engine, report registry, komponen grid). Jangan duplikasi.

Stack: **Laravel + Inertia + React + TypeScript + Tailwind + MySQL** (Laravel
Boost).

---

## Konteks & keputusan final (jangan ditanyakan ulang)

Bangun **modul K3 & KEAMANAN** — laporan kinerja bulanan TL K3L & Keamanan,
dari 5 file sumber (LAPKIN induk + PATROL + EMERGENCY + LAMPIRAN + SERTIFIKAT;
detail di `modul-k3.md`).

- **Multi-unit.** Format form sama antar unit; data & nama unit beda. Tiap menu
  punya pemilih unit; semua data per `unit_id`. File contoh = unit Poasia.
- **Master Unit Layanan, Mesin, Pegawai SUDAH ADA** → FK, jangan buat ulang.
- **Master K3 BELUM ADA** → modul ini buat CRUD (jenis kegiatan, emergency
  equipment, APD, lokasi patroli POA1–14, kotak P3K, APAR/APAB per RFID,
  kategori alat sertifikasi, item checklist inspeksi).
- **Akses:** role **TL K3L & Keamanan** (isi + lihat) dan **Manajer** (lihat
  saja).
- **Log patroli satpam = INPUT MANUAL** (cap waktu scan POA1–14 diketik).
- **Output:** bisa cetak per form DAN **satu dokumen penuh sekali klik**
  (semua form + lampiran, urut sesuai daftar isi LAPKIN).
- **Kadaluarsa sertifikat/APAR = LABEL/INDIKATOR di sistem** (badge status
  aktif / mendekati-expired / expired), **bukan notifikasi push**.
- **No. Dokumen ISO** (SMT-FM-AK3-*, FMZ-*) tetap per form, editable di
  setting (bukan auto-generate).

Aplikasi multi-modul — ikuti prinsip arsitektur generik yang sudah ada.

---

## Hak Akses

- **TL K3L & Keamanan:** akses penuh (isi + lihat).
- **Manajer:** hanya lihat/cetak (read-only).
- RBAC generik (pakai yang sudah ada): permission `k3.input.write`,
  `k3.laporan.view`, `k3.monitoring.view`. Manajer dapat `*.view` saja. Cek via
  kode permission, bukan nama role.
- Scoping unit via `user_units`. Cakupan Manajer (semua unit / tertentu) —
  konfirmasi ke user (konsisten dengan modul HAR).

---

## Fitur 1 — Menu Input (per unit)

Data K3 = banyak form inspeksi/inventaris + log keamanan, periode campuran
(harian/mingguan/bulanan). Gunakan **komponen grid reusable** (dari modul
Operasi) untuk form tabular, dan form biasa untuk yang berstruktur bebas.

Kelompokkan menu input sesuai `modul-k3.md`:

1. **Time Frame** — matriks rencana vs realisasi kegiatan K3 (grid 31 hari,
   RENC/REAL per kegiatan).
2. **Laporan Kecelakaan (PAK/PAHK)** — form; tombol cepat "NIHIL" bila tak ada
   kejadian (mayoritas bulan NIHIL).
3. **Inspeksi & Inventaris berkala:**
   - APAR/APAB per tabung (pilih dari master RFID, isi kondisi + exp date).
   - Emergency Facility (mingguan M1–M4 + bulanan): ready/not ready/% kesiapan.
   - Inventaris APD, Kotak/Isi P3K, Fire Alarm, Rambu K3, Alat Tanggap Darurat,
     Hydrant, HIRARC, Inspeksi Tempat Kerja (checklist generik lewat
     `inspections` + `inspection_results`).
4. **Keamanan (harian):**
   - **Patroli satpam** — grid POA1–POA14, input cap waktu scan manual + total.
   - Apel Keamanan, Mutasi/Monitoring Tamu, Kondisi CCTV.
5. **Sertifikasi peralatan** — data sertifikat + metode/hasil pengujian.
6. **Lampiran** — upload dokumen/foto per bulan (storage, simpan path).

- % kesiapan emergency dihitung otomatis bila sumber angka; bila teks (level
  air, "Ready") tampilkan apa adanya, jangan paksa hitung.
- Endpoint input: permission `k3.input.write` + cek hak unit.

---

## Fitur 2 — Menu Laporan (satu klik, per unit)

1. Pemilih **unit + periode (bulan/tahun)**.
2. Tiap form tampil dengan **kop + No. Dokumen ISO** yang benar, lewat
   **report registry** & document template engine.
3. Tombol **"Lihat & Cetak"** → satu klik preview → Unduh PDF/Cetak, tanpa
   halaman perantara.
4. **Dokumen penuh sekali klik:** gabungkan seluruh form (urut sesuai daftar
   isi LAPKIN) + sampul + daftar isi + lampiran jadi satu PDF. Sediakan juga
   cetak per form.
5. Akses `k3.laporan.view` (TL K3L & Manajer).

---

## Fitur 3 — Menu Monitoring (dashboard status, per unit)

1. Ringkasan status bulan berjalan: jumlah APAR siap/kadaluarsa, % kesiapan
   emergency, kecelakaan (NIHIL/ada), rekap patroli kumulatif, jumlah kegiatan
   Time Frame terealisasi.
2. **Monitoring sertifikat & APAR:** tabel dengan **badge status** —
   `aktif` (hijau), `mendekati expired` (kuning, mis. ≤60 hari), `expired`
   (merah). Hitung sisa hari/bulan dari tanggal uji ulang / exp date. Ini
   pengganti "pengingat" — label di sistem, bukan push notification.
3. Akses `k3.monitoring.view` (TL K3L & Manajer).

---

## Prioritas implementasi

1. Master K3 (CRUD) + skema DB + RBAC/unit scoping (pakai ulang fondasi).
2. Input form bulanan inti (Time Frame, Kecelakaan, APAR, Emergency, APD, P3K,
   Inspeksi Tempat Kerja).
3. Input keamanan harian (patroli, apel, tamu, CCTV).
4. Sertifikasi peralatan + Monitoring (badge status).
5. Menu Laporan per form + dokumen penuh sekali klik.
6. Lampiran upload.

---

## Definition of Done

- [ ] Baca `CLAUDE.md`, ikuti Laravel Boost.
- [ ] Pakai ulang fondasi modul Operasi/HAR (RBAC, work_modules, document
      engine, report registry, grid) — tidak menduplikasi.
- [ ] Tiap menu punya pemilih unit; data per `unit_id`; format form sama antar
      unit.
- [ ] TIDAK ada tabel unit/mesin/pegawai baru — FK existing.
- [ ] Master K3 dibuat + CRUD (lokasi patroli, emergency equipment, APD, P3K,
      APAR/RFID, kategori alat, item checklist).
- [ ] Form checklist seragam pakai pola `inspections`+`inspection_results`
      (bukan 28 tabel terpisah); tabel khusus hanya untuk struktur unik.
- [ ] TL K3L bisa isi + lihat; Manajer hanya lihat/cetak (uji POST/PUT Manajer
      → ditolak).
- [ ] Patroli: grid POA1–14, cap waktu scan manual, rekap kumulatif otomatis.
- [ ] % kesiapan emergency otomatis bila angka; teks ditampilkan apa adanya.
- [ ] Monitoring: badge status sertifikat/APAR (aktif/mendekati/expired) dengan
      hitung sisa hari — label di sistem, bukan notifikasi push.
- [ ] No. Dokumen ISO tampil benar & editable (tidak auto-generate).
- [ ] Laporan: cetak per form DAN satu dokumen penuh (semua form + lampiran)
      sekali klik.
- [ ] Lampiran upload berfungsi (storage, bukan blob).

---

## Konfirmasi ke user sebelum coding

1. Nama & PK tabel master Unit Layanan, Mesin, Pegawai existing (konsisten
   dgn modul Operasi/HAR).
2. Cakupan akses Manajer: semua unit atau tertentu?
3. Ambang "mendekati expired" untuk badge (default ≤60 hari — sesuaikan).
4. Daftar final form yang dipakai (beberapa form Excel bertanda "(NO)"/"(old)"
   = versi lama; pastikan mana yang aktif).
5. Untuk dokumen penuh: urutan & form mana saja yang wajib masuk (ikuti daftar
   isi LAPKIN, tapi konfirmasi bila ada yang dikecualikan).
