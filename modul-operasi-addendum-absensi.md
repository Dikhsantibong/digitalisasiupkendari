# ADDENDUM Modul OPERASI — Absensi & Jadwal Kerja Shift (Layer Operator)

> Tambahan untuk modul OPERASI. Sumber: `Absensi.xlsx` (JADWAL KERJA PLTD
> Containerized Poasia). **Bukan modul baru** — ini bagian modul OPERASI,
> sejajar dengan addendum Logsheet Operator.
>
> **Langkah 0:** baca `CLAUDE.md` (Laravel Boost) + `modul-operasi.md`. FK ke
> master existing (Unit Layanan, **Pegawai**). Pakai ulang fondasi generik
> (RBAC, grid, unit scoping) — jangan duplikasi.

---

## 0. Gambaran & keputusan final

Modul untuk mengelola **jadwal kerja shift & absensi** pegawai per unit per
bulan. Dari Excel: satu lembar per bulan, baris = pegawai, kolom = tanggal
(1–31), isi sel = kode shift/kehadiran, plus rekap & persentase kehadiran.

Keputusan yang sudah dikunci user:
- **Bagian dari modul OPERASI** (layer operator), bukan modul terpisah.
- **Daftar pegawai diambil dari master Pegawai existing** (NIP, jabatan, nama,
  regu) — FK, jangan buat ulang.
- **Pola shift TIDAK di-generate paksa**; setiap sel bisa **diisi/diedit
  manual per hari**.
- **NAMUN sediakan generate pola otomatis (opsional)** sebagai titik awal yang
  bisa diedit — lihat §3. (User awalnya ingin "jadwal terisi sendiri" tapi juga
  "manual per hari"; solusi: auto-generate opsional + edit manual per sel.
  Tandai sebagai keputusan yang bisa disesuaikan user.)
- **Pengisi:** leader/koordinator menjadwalkan regu; operator melihat/mengisi
  bagiannya. Konfirmasi pembagian tepatnya ke user (§7).

---

## 1. Struktur dari Excel

- **Header laporan:** Judul proyek, Nama unit (PLTD Containerized Poasia),
  Bulan, Tahun.
- **Kolom identitas:** NO, NIP, JABATAN, REGU (A/B/C), NAMA.
- **Kolom tanggal:** 1–31 (header menampilkan nama hari + tanggal). Isi sel =
  kode (lihat §2).
- **Dua kelompok pegawai:**
  - **KERJA SHIFT** (operator, leader shift) — pola P/S/M/OFF berotasi.
  - **NON-SHIFT** (koordinator, pemeliharaan, K3LH) — jam kerja reguler.
- **Rekap absensi per pegawai** (kolom kanan): jumlah PAGI, SORE, MALAM, OFF,
  SAKIT, IZIN, ALPHA, CUTI.
- **Persentase kehadiran** per pegawai (otomatis).
- **Baris TOTAL** per kode di bawah tabel.
- **Legenda & jam kerja** (di bawah): jam shift & non-shift, aturan libur.

---

## 2. Kode Kehadiran / Shift (master, seed dari Excel)

| Kode | Arti | Jam kerja |
|---|---|---|
| P | Pagi | 08.00–16.00 WITA |
| S | Sore | 16.00–24.00 WITA |
| M | Malam | 00.00–08.00 WITA |
| OFF | Libur shift | — |
| CUTI | Cuti | — |
| S (SAKIT) | Sakit | — |
| I / IZIN | Izin | — |
| A / ALPHA | Alpha (tanpa keterangan) | — |

> Catatan: di Excel "S" dipakai untuk **Sore** (shift) sekaligus muncul
> "SAKIT" di rekap — bedakan kode shift vs kode ketidakhadiran agar tidak
> ambigu. Buat master `attendance_codes` (kode, label, tipe: shift|absence,
> jam_mulai, jam_selesai, hitung_hadir bool). Konfirmasi pemetaan final ke user.

**Jam kerja non-shift:** Senin–Kamis 08.00–16.30, Jumat 07.30–16.30 WITA.

---

## 3. Aturan Bisnis

1. **Auto-generate pola shift (opsional, titik awal):** dari pola rotasi regu
   yang terlihat di Excel — contoh Regu A: OFF,OFF,S,S,P,P,M,M berulang;
   Regu B & C bergeser fase. Sediakan tombol "Generate pola" yang mengisi
   jadwal sebulan mengikuti pola regu + tanggal mulai pola, LALU semua sel
   tetap bisa diedit manual. Jangan paksa; ini bantuan, bukan penguncian.
   Simpan definisi pola per regu di master agar bisa diubah
   (`shift_patterns`: regu, urutan kode, panjang siklus).
2. **Rekap otomatis per pegawai:** hitung jumlah tiap kode (P/S/M/OFF/SAKIT/
   IZIN/ALPHA/CUTI) dari sel tanggal.
3. **Persentase kehadiran:** definisi ikut Excel (mis. hari hadir shift ÷ total
   hari kerja). Konfirmasi rumus persisnya ke user; buat di satu service.
4. **Total per kode:** jumlah lintas pegawai per kode.
5. **Hari libur:** dari master `holidays` (seed 25 hari libur nasional 2026 di
   file terlampir) untuk menandai/mewarnai kolom libur — tidak mengubah shift
   (operasi tetap jalan 24 jam).

---

## 4. Skema Database (ber-`unit_id`)

```
attendance_codes    id, code, label, type (shift|absence),
                    jam_mulai (nullable), jam_selesai (nullable),
                    hitung_hadir (bool), sort_order
   -- seed: P, S, M, OFF, CUTI, SAKIT, IZIN, ALPHA

shift_patterns      id, unit_id, regu (A|B|C|...), sequence (JSON: ['OFF','OFF',
                    'S','S','P','P','M','M']), cycle_days, is_active
   -- definisi pola rotasi per regu (untuk auto-generate)

work_schedules      -- header jadwal bulanan per unit
  id, unit_id, year, month, group_type (shift|non_shift),
  generated_at (nullable), locked_at (nullable), input_by, timestamps
  unique(unit_id, year, month, group_type)

work_schedule_entries   -- 1 baris = 1 pegawai + 1 tanggal
  id, work_schedule_id (FK), employee_id (FK master pegawai existing),
  work_date, attendance_code_id (FK attendance_codes),
  regu (snapshot, nullable), note (nullable)
  index(work_schedule_id, employee_id, work_date)
  -- rekap & persentase DIHITUNG dari sini, tidak disimpan

holidays            id, year, date, day_name, description, is_national (bool)
   -- seed dari 'Kalender Hari Libur' (25 hari libur 2026)
```

> Pegawai (NIP, jabatan, nama, regu) dari master existing. Jika `regu` belum
> ada di master pegawai, tambah kolom via ALTER atau simpan snapshot `regu` di
> entry — konfirmasi ke user.

---

## 5. Hak Akses

- **Leader/Koordinator** (role terkait, mis. `leader_shift` atau reuse role
  operasi): menjadwalkan & mengedit jadwal seluruh regu — `operasi.absensi.write`.
- **Operator**: melihat jadwalnya; bila diizinkan, mengisi bagian sendiri —
  `operasi.absensi.view` (+ `operasi.absensi.write` bila diberi).
- **TL Operasi & Manajer**: lihat + cetak — `operasi.absensi.view`.
- RBAC generik yang sudah ada; scoping unit via `user_units`.
- Konfirmasi ke user siapa persis yang boleh mengedit (leader saja, atau
  operator juga).

---

## 6. Fitur — Menu Absensi & Jadwal (per unit)

- Pilih **unit + bulan + tahun + kelompok** (shift / non-shift).
- **Grid mirip Excel:** baris = pegawai (dari master, difilter unit), kolom =
  tanggal 1–31, sel = dropdown/ketik kode kehadiran. Header kolom tampilkan
  nama hari; kolom hari libur diberi warna (dari `holidays`). Pakai komponen
  grid reusable modul Operasi + navigasi keyboard + paste.
- Kolom kanan **rekap otomatis** (P/S/M/OFF/SAKIT/IZIN/ALPHA/CUTI) + **%
  kehadiran**, read-only.
- Tombol **"Generate pola shift"** (opsional): isi otomatis dari `shift_patterns`
  + tanggal mulai, lalu bisa diedit manual.
- Baris TOTAL per kode otomatis.
- **Cetak PDF** jadwal bulanan (format mirip Excel) sekali klik.
- Endpoint: `operasi.absensi.write` (edit) / `.view` (lihat) + cek hak unit.

---

## 7. Konfirmasi ke user

1. Siapa yang boleh mengedit jadwal: leader/koordinator saja, atau operator
   juga boleh isi bagiannya?
2. Rumus persentase kehadiran yang benar (di Excel ~0.74; pembagi berapa?).
3. Pemetaan kode final — khususnya "S" (Sore) vs "SAKIT" agar tidak ambigu.
4. Pola rotasi resmi tiap regu (A/B/C) untuk seed `shift_patterns`.
5. Field `regu` sudah ada di master pegawai existing atau perlu ditambahkan?
6. Kelompok non-shift: apakah perlu jadwal harian juga, atau cukup penanda
   "kerja reguler" tanpa kode per tanggal?
