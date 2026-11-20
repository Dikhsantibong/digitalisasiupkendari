{{--
    The Laporan Administrasi Keuangan document printed on A4 portrait
    (App\Services\Keuangan\KeuanganDocumentBuilder::pdf): the laporan table then the
    bukti & lampiran, their styles scoped under .keu-lap / .keu-bukti.
    Params: $css (scoped styles), $body (HTML, logos & photos embedded).
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Administrasi Keuangan</title>
    <style>
        @page { margin: 10mm 10mm 12mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #000; margin: 0; }
        table { border-collapse: collapse; }
        {!! $css !!}
    </style>
</head>
<body>
    {!! $body !!}
</body>
</html>
