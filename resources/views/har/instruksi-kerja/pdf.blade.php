<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Instruksi Kerja Pemeliharaan - {{ $unitName }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm 18mm 16mm 18mm; }
        body { margin: 0; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @foreach($docs as $doc)
        @include('har.instruksi-kerja.document', ['doc' => $doc])
        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
