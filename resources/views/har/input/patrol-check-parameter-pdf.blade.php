{{-- PDF (A4 landscape) Patrol Check Parameter Mesin — definisi: App\Support\HarPatrolCheckParameter, data: Har\PatrolCheckParameterController::pdfView(). --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Patrol Check Parameter Mesin - {{ $unit->name }} - {{ $periodLabel }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 8mm 12mm 8mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 7px; color: #000; margin: 0; }
        .kop { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 17%; padding: 3px 6px; }
        .kop .logo img { max-height: 34px; max-width: 120px; }
        .kop .logo-right { text-align: right; }
        .kop .line { text-align: center; font-weight: bold; font-size: 10px; line-height: 1.4; }
        .meta { width: 100%; border-collapse: collapse; }
        .meta td { padding: 1px 2px; font-size: 7.5px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 1px 2px; text-align: center; vertical-align: middle; }
        table.grid th { font-weight: normal; font-size: 6.8px; }
        table.grid td { height: 11px; font-size: 7.5px; }
        table.grid tr { page-break-inside: avoid; }
        table.grid tr.red td { background: #ff0000; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td class="logo">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
            <td class="line">
                JASA PENDUKUNG TEKNIS 6 KIT<br>
                LAPORAN PROJECT {{ strtoupper($unit->name) }}<br>
                PATROL CHECK PEMELIHARAAN
            </td>
            <td class="logo logo-right">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
        </tr>
    </table>

    <table class="meta">
        <tr><td>{{ $periodLabel }}</td></tr>
        <tr><td><em>MESIN: {{ $machine ? strtoupper($machine->name) : '' }}</em></td></tr>
    </table>

    <table class="grid">
        <thead>
            @foreach($headerRows as $level => $cells)
                <tr>
                    @foreach($cells as $cell)
                        <th colspan="{{ $cell['colspan'] }}" rowspan="{{ $cell['rowspan'] }}"@if($level === 0 && $loop->first) style="width: {{ $widths[0] }}%;"@endif>{{ $cell['label'] }}</th>
                    @endforeach
                </tr>
            @endforeach
        </thead>
        <tbody>
            @foreach($days as $day)
                @php $values = $readings[(int) $day['label']] ?? []; @endphp
                <tr class="{{ $day['is_red'] ? 'red' : '' }}">
                    <td style="width: {{ $widths[0] }}%;">{{ $day['label'] }}</td>
                    @foreach($columns as $index => $column)
                        <td style="width: {{ $widths[$index + 1] }}%;">{{ $values[$column['key']] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
