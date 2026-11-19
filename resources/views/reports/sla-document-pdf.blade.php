{{--
    A saved (edited) SLA document printed on A4 landscape — one unit block of
    App\Services\Reports\SlaDocumentBuilder with the SLA styles scoped under
    .sla-doc, and the same printed-on footer as reports/sla-pdf.
    Params: $css (scoped styles), $body (HTML, logos embedded), $foot (footer text).
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan SLA</title>
    <style>
        @page { margin: 9mm 9mm 11mm; }
        body { font-family: 'DejaVu Serif', 'Times New Roman', serif; font-size: 7.6px; color: #000; margin: 0; }
        .sla-doc-foot { position: fixed; bottom: -7mm; left: 0; right: 0; font-size: 6.5px; color: #64748b; }
        {!! $css !!}
    </style>
</head>
<body>
    <div class="sla-doc-foot">{{ $foot }}</div>
    {!! $body !!}
</body>
</html>
