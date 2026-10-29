---
paths:
  - 'resources/js/pages/**'
---

# Pages

## HP operator: TANPA filter unit/bulan/tahun/tanggal/shift (menggantikan aturan CompactFilters)
Keputusan user (2026-10-29): di tampilan HP (cabang compact) halaman input operator TIDAK menampilkan filter Unit/Bulan/Tahun/Tanggal/Shift sama sekali — unit dari akun, periode = sekarang, shift logsheet otomatis dari regu (`LogsheetController::reguOf`: regu Jadwal Shift tanggal itu, else `employees.regu`). Komponen compact-filters sudah dihapus. Yang tetap tampil hanya pilihan yang memang bagian input (Mesin di Patrol Check & Logsheet, minggu di 5S5R, hari di Patrol). Tanpa PageHeader/deskripsi, KPI, PDF/Excel di HP. Desktop tetap lengkap.
