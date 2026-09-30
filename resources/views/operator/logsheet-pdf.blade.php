{{-- PDF (A4 landscape) Logsheet Operator — satu mesin satu hari: slot jam × parameter. Data: Operator\LogsheetController::pdf(). --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Logsheet {{ $engine->name }} - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 8mm 8mm 8mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 7px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .head td { vertical-align: middle; }
        .head img { max-height: 30px; max-width: 120px; }
        .title { text-align: center; font-weight: bold; font-size: 12px; }
        .sub { text-align: center; font-size: 8px; padding-top: 2px; }
        .info td { font-size: 8px; padding: 1px 4px; }
        .grid th, .grid td { border: 1px solid #000; padding: 1.5px 2px; text-align: center; }
        .grid th { background: #d9d9d9; font-weight: bold; font-size: 6.5px; }
        .grid th.uom { background: #f2f2f2; font-weight: normal; font-size: 6px; }
        .grid td.jam { font-weight: bold; background: #f2f2f2; }
        .grid tr.peak td { background: #fff7e0; }
        .stats td { border: 1px solid #000; padding: 2px 4px; font-size: 7.5px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 20%;">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
            <td>
                <div class="title">LOGSHEET OPERATOR {{ strtoupper($unit->name) }}</div>
                <div class="sub">{{ strtoupper($engine->name) }} — {{ $hariTanggal }}</div>
            </td>
            <td style="width: 20%; text-align: right;">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
        </tr>
    </table>
    <table class="info" style="margin: 4px 0;">
        <tr>
            <td style="width: 33%;"><b>Mesin:</b> {{ $engine->name }}</td>
            <td style="width: 33%;"><b>Regu / Shift:</b> {{ $shift ?? '-' }}</td>
            <td style="text-align: right;"><b>Status:</b> {{ $status }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="width: 30px;">JAM</th>
                @foreach($parameters as $parameter)
                    <th>{{ $parameter->label() }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($parameters as $parameter)
                    <th class="uom">{{ $parameter->unit_of_measure ?: '-' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="{{ in_array($row['time_slot'], ['17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00', '21:30'], true) ? 'peak' : '' }}">
                    <td class="jam">{{ $row['time_slot'] }}</td>
                    @foreach($parameters as $parameter)
                        <td>{{ $row['values']['p_'.$parameter->id] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="stats" style="margin-top: 5px; width: auto;">
        <tr>
            @foreach($stats as $stat)
                <td><b>{{ $stat['label'] }}:</b> {{ $stat['value'] }} {{ $stat['unit'] ?? '' }}</td>
            @endforeach
        </tr>
    </table>
</body>
</html>
