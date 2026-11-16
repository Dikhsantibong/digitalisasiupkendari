<?php

use App\Enums\SignatureMode;

return [
    /*
    |--------------------------------------------------------------------------
    | Model Tampilan Tanda Tangan Dokumen
    |--------------------------------------------------------------------------
    | Menentukan bentuk tampilan tanda tangan setelah disetujui/disahkan pada
    | laporan dokumen. Ubah nilainya atau ganti binding SignatureMarkResolver
    | di AppServiceProvider bila suatu saat memakai penyedia tanda tangan lain.
    |
    | Opsi bawaan: 'qr' (barcode QR + logo PLN), 'image' (gambar ttd pegawai),
    | 'text' (teks stempel elektronik saja).
    */
    'signature_mode' => env('REPORT_SIGNATURE_MODE', SignatureMode::Qr->value),
];
