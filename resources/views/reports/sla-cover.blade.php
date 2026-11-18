{{--
    Sampul Laporan SLA gabungan: the cover PNG drawn by
    App\Services\Reports\SlaCover, full-bleed on one A4 portrait page.
    Params: $cover (data URI).
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Sampul Laporan SLA</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; }
        img { display: block; width: 210mm; height: 296.5mm; }
    </style>
</head>
<body>
    <img src="{{ $cover }}" alt="Sampul Laporan Service Level Agreement (SLA)">
</body>
</html>
