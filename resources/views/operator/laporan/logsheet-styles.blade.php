{{-- Logsheet report styles: shared operator report framework. The PDF is built
     by rendering three segments (seg-a portrait, seg-b landscape, seg-c portrait)
     and merging them (FPDI) so the cover stays portrait and only the wide input
     table's page is landscape — the table keeps the SAME layout as the input
     screen (jam as rows, parameters as columns). On screen (editor) all segments
     show normally; the merger injects the per-segment hide rules + @page size. --}}
@include('operator.laporan.styles')
.ls-data { font-size: 8px; }
.ls-data th, .ls-data td { padding: 2px 3px; }
