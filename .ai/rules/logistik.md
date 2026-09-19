---
paths:
  - 'app/Http/Controllers/Logistik/**'
---

# Logistik

## Modul LOGISTIK & GUDANG — pola & RBAC
Modul baru Logistik & Gudang mengikuti pola HAR (template terdekat). RBAC: PermissionGroup::Logistik, permission `logistik.{input.view,input.write,laporan.view,master.view_any,master.manage}`, role `tl_logistik` (TeamLeaderLogistik, scope Unit) + Manager UL dapat `logistik.laporan.view`. Registry: `work_modules` seed `logistik`. Routes di `routes/logistik.php` (prefix/name `logistik.`) — saat ini hanya hub `jadwal.index`, `input.index`, `laporan.index`; sub-halaman menyusul. Controller di `app/Http/Controllers/Logistik/`, gate tiap aksi dengan hasPermissionTo(PermissionName::Logistik*) + canAccessUnit. Halaman React `resources/js/pages/logistik/{jadwal,input,laporan}`. Transaksi wajib ber-unit_id (trait BelongsToUnit). Setelah tambah route jalankan `php artisan wayfinder:generate --with-form`. Selalu update `modul-logistik-gudang.md` di root setiap modul berubah. Menu: 7 jadwal (harian, pemeliharaan, piket on-call, patrol-check stok, 5S5R, meeting, pembuatan IK) + 5 laporan (patrol check, inventaris lainnya, peralatan/material/tools, kondisi stok tools&material, permit to work).

## Logistik: mesin sheet & form tabel, laporan dari pdfView
Jadwal & input berbentuk matriks (kode per tanggal/bulan/slot/level) = sheet di `App\Support\LogistikJadwal::SHEETS` (layout kegiatan|pelaksana|shift|ik|patrol|aplikasi|checklist|maturity, `menu` jadwal|input, tabel `logistik_jadwal_rows`) via `JadwalSheetController`. Input tabel bebas = subclass `App\Support\LogistikForms\LogistikForm` di `LogistikForms::ALL` (kolom computed formula/count_filled/percent dihitung ulang saat baca, jangan disimpan; tabel `logistik_form_rows`) via `FormController`. Tambah jadwal/input baru lewat definisi, bukan controller baru. Setiap controller sediakan `pdfView()`; Laporan Logistik (`LogistikDocumentBuilder::sources()`) menyisipkan view PDF itu sebagai fragmen ber-CSS scoped — kop harus di <body> (bukan partial di <head>) agar ikut tersisip. Tanpa tanda tangan, dropdown komponen React. Update `modul-logistik-gudang.md`.
