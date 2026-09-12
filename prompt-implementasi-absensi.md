# PROMPT UNTUK CLAUDE CODE — Absensi & Jadwal Kerja Shift (Layer dalam Modul OPERASI)

> **Langkah 0:** baca `CLAUDE.md` (Laravel Boost). Lalu baca `modul-operasi.md`
> DAN `modul-operasi-addendum-absensi.md`. Ini **BUKAN modul baru** — Absensi
> adalah bagian modul OPERASI. **Pakai ulang** fondasi generik (RBAC,
> `work_modules`, komponen grid, unit scoping). FK ke master existing (Unit
> Layanan, Pegawai) — jangan buat ulang.

Stack: **Laravel + Inertia + React + TypeScript + Tailwind + MySQL** (Laravel Boost).

---

## Konteks & keputusan final (jangan ditanyakan ulang)

Bangun fitur **Absensi & Jadwal Kerja Shift** di modul OPERASI: kelola jadwal
shift & kehadiran pegawai per unit per bulan (pengganti file Excel jadwal).

- **Bagian dari modul OPERASI**, bukan modul terpisah.
- **Pegawai dari master existing** (NIP, jabatan, nama, regu) → FK.
- **Multi-unit**: semua data ber-`unit_id`; hanya unit yang boleh diakses user.
- **Isi manual per hari** per sel, TAPI sediakan **generate pola shift opsional**
  sebagai titik awal yang bisa diedit (bukan penguncian).
- **Kelompok pegawai**: shift (P/S/M/OFF rotasi) & non-shift (kerja reguler).
- **Rekap & % kehadiran dihitung otomatis**, bukan input.
- **Hari libur** dari master (seed 25 hari libur nasional 2026 tersedia di
  `absensi_libur_seed.json`).

---

## Hak Akses

- **Leader/Koordinator**: menjadwalkan & edit jadwal regu — `operasi.absensi.write`.
- **Operator**: lihat jadwalnya; isi bagian sendiri bila diizinkan —
  `operasi.absensi.view` (+ `.write` bila diberi).
- **TL Operasi & Manajer**: lihat + cetak — `operasi.absensi.view`.
- RBAC generik yang sudah ada (kode permission, bukan nama role); scoping unit
  via `user_units`. Konfirmasi ke user siapa persis yang boleh mengedit.

---

## Skema Database (ber-`unit_id`)

Ikuti `modul-operasi-addendum-absensi.md` §4:
- `attendance_codes` — P, S, M, OFF, CUTI, SAKIT, IZIN, ALPHA (tipe shift|absence,
  jam, hitung_hadir).
- `shift_patterns` — pola rotasi per regu (sequence JSON, cycle_days) untuk
  generate.
- `work_schedules` — header jadwal bulanan (unit, year, month, group_type
  shift|non_shift).
- `work_schedule_entries` — 1 pegawai + 1 tanggal + kode kehadiran.
- `holidays` — seed dari file libur.

Jika `regu` belum ada di master pegawai existing, konfirmasi ke user: tambah
kolom via ALTER atau simpan snapshot `regu` di entry.

---

## Fitur — Menu Absensi & Jadwal (per unit)

- Pemilih **unit + bulan + tahun + kelompok** (shift/non-shift).
- **Grid mirip Excel**: baris = pegawai (master, difilter unit), kolom = tanggal
  1–31 (header nama hari; kolom libur diberi warna dari `holidays`), sel =
  dropdown/ketik kode. Komponen grid reusable + keyboard nav + paste.
- **Rekap otomatis** per pegawai (P/S/M/OFF/SAKIT/IZIN/ALPHA/CUTI) + **%
  kehadiran** (read-only), via satu service kalkulasi.
- Tombol **"Generate pola shift"**: isi otomatis dari `shift_patterns` + tanggal
  mulai; semua sel tetap editable manual setelahnya.
- Baris TOTAL per kode otomatis.
- **Cetak PDF** jadwal bulanan (format mirip Excel) sekali klik.
- Endpoint: `operasi.absensi.write` (edit) / `.view` (lihat) + cek hak unit.

---

## Definition of Done

- [ ] Baca `CLAUDE.md` + kedua dokumen; ikuti Laravel Boost.
- [ ] Absensi berada di dalam modul OPERASI (bukan modul baru); pakai ulang
      fondasi (RBAC, grid, unit scoping).
- [ ] TIDAK ada tabel pegawai/unit baru — FK master existing (tunjukkan tabel
      yang dipakai).
- [ ] Grid jadwal mirip Excel: baris pegawai, kolom tanggal, sel kode; libur
      berwarna; paste dari Excel jalan.
- [ ] Generate pola shift opsional berfungsi & hasilnya tetap bisa diedit
      manual per sel.
- [ ] Rekap per kode & % kehadiran dihitung otomatis di satu service (tidak
      disimpan sebagai input).
- [ ] `attendance_codes` & `shift_patterns` dari master (CRUD), bukan hardcode.
- [ ] Kode "S" (Sore) vs "SAKIT" tidak ambigu.
- [ ] Hari libur ter-seed & dipakai untuk menandai kolom.
- [ ] Hak akses: leader edit, operator lihat/isi bagiannya, Manajer/TL lihat;
      route ditolak sesuai permission.
- [ ] Cetak PDF jadwal bulanan sekali klik.

---

## Konfirmasi ke user sebelum coding

1. Siapa yang boleh mengedit: leader/koordinator saja atau operator juga?
2. Rumus % kehadiran yang benar (contoh Excel ~0.74; pembaginya berapa?).
3. Pemetaan kode final (S=Sore vs SAKIT, dll).
4. Pola rotasi resmi tiap regu (A/B/C) untuk seed `shift_patterns`.
5. `regu` sudah ada di master pegawai existing atau perlu ditambah?
6. Non-shift: perlu jadwal harian per tanggal, atau cukup penanda kerja reguler?
