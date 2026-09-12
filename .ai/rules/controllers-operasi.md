---
paths:
  - 'app/Http/Controllers/Operasi/**'
---

# Controllers Operasi

## Logsheet & Absensi sekarang di modul OPERATOR
Layer input operator (logsheet harian + jadwal/absensi shift) sudah DIPINDAH ke modul tersendiri `operator` — lihat `.ai/rules/controllers-operator.md`. JANGAN tambahkan controller logsheet/absensi di namespace Operasi lagi. OPERASI hanya boleh menarik datanya nanti via `App\Services\Operasi\LogsheetAggregator` (belum aktif).

## Laporan: satu pintu "Buka Dokumen" + pratinjau PDF di editor (HAR/Operasi/K3 seragam)
Keputusan user: TIDAK ada lagi tombol "Lihat & Cetak" terpisah di menu Laporan. Semua modul (HAR, Operasi, K3) hanya punya satu tombol "Buka Dokumen (Lihat, Edit & Cetak)" yang membuka editor dokumen. Pratinjau ada DI DALAM editor: komponen shared `components/document/document-editor.tsx` punya 3 mode — Teks (PDF) · Excel · **Pratinjau PDF** (iframe ke route `*.laporan.document.pdf`, jadi pratinjau = hasil cetak persis). Print-preview React lama (`operasi/laporan/monthly-engine`, `har/laporan/monthly`) + route `laporan.show`/`spreadsheet`/`har.laporan.monthly` masih ADA tapi tak ditaut dari UI (jangan hapus, dipakai test). K3 tak punya print-preview terpisah (dulu sempat ada `k3.laporan.monthly`, sudah dihapus).
Logo PDF: SEMUA controller PDF laporan pakai trait `App\Http\Controllers\Concerns\EmbedsReportLogo` (regex ganti `src=…logo/sidebar-logo.png` → data URI base64). WAJIB pakai trait ini, JANGAN `str_replace('/logo/…')` — TinyMCE mengubah src jadi URL absolut saat disimpan sehingga str_replace path relatif meleset dan dompdf menampilkan alt "Logo".
Operasi: `LaporanDocumentController` edit/store/pdf (route `operasi.laporan.document.{edit,store,pdf}` param `{report}`). Persist ke `OperasiReportDocument` (tabel `operasi_report_documents`, unique unit+report_code+engine_id+month+year) — BUKAN `DocumentRecord` (di-cast enum BeritaAcaraType). content_html default = letterhead + `DocumentGridBuilder::gridToHtml(forMonthlyReport($data))`; PDF via blade `operasi.laporan.pdf-shell` + letterhead `operasi.laporan.partials.letterhead`. Gate `operasi.laporan.view`.

## Gotcha kolom bertipe date (SQLite)
Kolom `date` (cast 'date') tersimpan '00:00:00' di SQLite in-memory (test) → JANGAN query `where('some_date',$str)`; pakai `whereDate('some_date',$str)`. Untuk assertDatabaseHas tanggal, query model lalu bandingkan `->toDateString()`.
