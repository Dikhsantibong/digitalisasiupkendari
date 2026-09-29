<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Dokumen IK K3 - {{ $unitName }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm 14mm 14mm 14mm; }
        body { margin: 0; font-family: 'DejaVu Sans', Arial, sans-serif; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @foreach($docs as $doc)
        @include('k3.dokumen-ik.document', ['doc' => $doc, 'unitName' => $unitName])
        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
