<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal FLM Operator - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 6.5px; color: #000; margin: 0; padding: 0; }
        .header-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 5px; }
        .header-table td { vertical-align: middle; border: 1px solid #000; }
        .logo-box { width: 110px; text-align: center; padding: 3px; }
        .logo-box img { max-height: 38px; max-width: 100px; }
        .title-box { text-align: center; padding: 3px 6px; }
        .title-box h1 { margin: 0; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .title-box h2 { margin: 1px 0 0; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 6.5px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 1px; text-align: center; vertical-align: middle; }
        .data-table thead th { background-color: #ea7315; color: #fff; font-weight: bold; }
        .cat-cell { background-color: #fbe0c8; font-weight: bold; text-transform: uppercase; }
        .name-cell { text-align: left !important; padding-left: 3px !important; }
        .minutes-name { color: #b91c1c; }
        .done { background-color: #cbd5e1; font-weight: bold; }
        .stat { font-weight: bold; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="logo-box">@if($logoLeft)<img src="{{ $logoLeft }}">@else<strong>PLN</strong>@endif</td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS 11 SITE KIT</h1>
                <h2>LAPORAN PROJECT — {{ strtoupper($unit->name) }}</h2>
                <h2>JADWAL FIRST LINE MAINTENANCE (FLM) OPERATOR — {{ $monthName }} {{ $year }}</h2>
            </td>
            <td class="logo-box">@if($logoRight)<img src="{{ $logoRight }}">@else<strong>MKP</strong>@endif</td>
        </tr>
    </table>

    @php
        $sections = ['rutin' => 'FLM RUTIN', 'non_rutin' => 'FLM NON RUTIN'];
        $grouped = collect($rows)->groupBy('section');
    @endphp

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 60px;">Kategori</th>
                <th rowspan="2" style="width: 120px;">Uraian</th>
                <th rowspan="2" style="width: 34px;">Shift</th>
                <th colspan="{{ $daysInMonth }}">Bulan {{ $monthName }}</th>
                <th rowspan="2" style="width: 40px;">Realisasi</th>
                <th rowspan="2" style="width: 34px;">Target</th>
                <th rowspan="2" style="width: 34px;">%</th>
            </tr>
            <tr>
                @for($d = 1; $d <= $daysInMonth; $d++)<th style="width: 11px;">{{ $d }}</th>@endfor
            </tr>
        </thead>
        <tbody>
            @foreach($sections as $key => $label)
                @php $items = $grouped->get($key, collect()); @endphp
                @foreach($items as $i => $row)
                    <tr>
                        @if($i === 0)
                            <td class="cat-cell" rowspan="{{ count($items) }}">{{ $label }}</td>
                        @endif
                        <td class="name-cell {{ $row['row_type'] === 'minutes' ? 'minutes-name' : '' }}">{{ $row['label'] }}</td>
                        <td>{{ $row['shift'] }}</td>
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            @php $v = $row['days'][(string) $d] ?? ''; @endphp
                            @if($row['row_type'] === 'mark')
                                <td class="{{ $v !== '' ? 'done' : '' }}">{{ $v !== '' ? '1' : '' }}</td>
                            @else
                                <td>{{ $v }}</td>
                            @endif
                        @endfor
                        <td class="stat">{{ $row['row_type'] === 'shift' ? '' : $row['realisasi'] }}</td>
                        <td class="stat">{{ $row['row_type'] === 'shift' ? '' : $row['target'] }}</td>
                        <td class="stat">{{ $row['percentage'] === null ? '' : $row['percentage'].'%' }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
