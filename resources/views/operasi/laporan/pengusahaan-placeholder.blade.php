<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 13px; line-height: 1.45; margin: 6px 0 16px; }
        .note { margin: 30px auto; width: 75%; border: 1px dashed #777; padding: 18px; text-align: center; color: #444; line-height: 1.5; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])
    <div class="title">{{ $title }}<br>BULAN {{ strtoupper($period_label) }}</div>
    <div class="note">
        Bagian <b>{{ $title }}</b> belum memiliki menu input di Pengusahaan Operasi.<br>
        Halaman ini akan terisi otomatis setelah menunya tersedia.
    </div>
</body>
</html>
