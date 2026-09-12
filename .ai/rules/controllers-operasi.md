---
paths:
  - 'app/Http/Controllers/Operasi/**'
---

# Controllers Operasi

## Logsheet Operator (layer input dalam OPERASI) + gotcha kolom date
Logsheet operator = bagian modul OPERASI (bukan modul baru). Role `operator` (RoleName::Operator) hanya punya `operasi.logsheet.write`+`view`, TANPA operasi.input/laporan/BA (uji: operator ke route input harian → 403). TL Operasi & Manager UL dapat `operasi.logsheet.view` (read-only). Parameter kolom dari master `logsheet_parameters` (JANGAN hardcode; plant_type=all sekarang, PLTM/PLTG disiapkan lewat baris ber-plant_type). Slot waktu = nilai (bukan 24 baris tetap): template jam + 17:30/18:30/19:30/20:30/21:30, boleh tambah slot. `operator_logsheets` unique(engine_id,log_date), status draft|submitted (LogsheetStatus); submit mengunci edit operator (store pada sheet submitted → 422). Readings model panjang (baris per parameter per time_slot), key frontend `p_{id}`. Auto-agregasi ke daily_engine_reports SENGAJA belum aktif: service `App\Services\Operasi\LogsheetAggregator` (TODO, tak dipanggil) + kolom `daily_engine_reports.source` (default manual). GOTCHA: kolom bertipe `date` (cast 'date') tersimpan '00:00:00' di SQLite → JANGAN query `where('log_date',$str)`; pakai `whereDate('log_date',$str)`, dan untuk assertDatabaseHas tanggal query model lalu bandingkan ->toDateString().
