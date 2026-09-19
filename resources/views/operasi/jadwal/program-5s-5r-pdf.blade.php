<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $barTitle ?? 'Jadwal Pelaksanaan 5S5R' }} - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 7px; color: #000; margin: 0; padding: 0; }
        .header-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 5px; }
        .header-table td { vertical-align: middle; border: 1px solid #000; }
        .logo-box { width: 110px; text-align: center; padding: 3px; }
        .logo-box img { max-height: 38px; max-width: 100px; }
        .title-box { text-align: center; padding: 3px 6px; }
        .title-box h1 { margin: 0; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .title-box h2 { margin: 1px 0 0; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 7px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 1.5px 1px; text-align: center; vertical-align: middle; }
        .data-table thead th { background-color: #ea7315; color: #fff; font-weight: bold; }
        .name-cell { text-align: left !important; padding-left: 3px !important; font-weight: bold; }
        .status-cell { background-color: #f3f4f6; font-weight: bold; }
        .done { background-color: #cbd5e1; font-weight: bold; }
        .stat { font-weight: bold; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="logo-box">@if($logoLeft)<img src="{{ $logoLeft }}">@else<strong>PLN</strong>@endif</td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS 11 &amp; 6 SITE - KIT</h1>
                <h2>LAPORAN PROJECT — {{ strtoupper($unit->name) }}</h2>
                <h2>{{ $barTitle ?? 'JADWAL PELAKSANAAN 5S5R' }} — {{ $monthName }} {{ $year }}</h2>
            </td>
            <td class="logo-box">@if($logoRight)<img src="{{ $logoRight }}">@else<strong>MKP</strong>@endif</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 120px;">Pelaksana</th>
                <th rowspan="2" style="width: 55px;">Rencana / Realisasi</th>
                <th colspan="{{ $daysInMonth }}">Bulan {{ $monthName }}</th>
                <th rowspan="2" style="width: 34px;">Rencana</th>
                <th rowspan="2" style="width: 30px;">Target</th>
                <th rowspan="2" style="width: 34px;">Realisasi</th>
                <th rowspan="2" style="width: 34px;">A. Kinerja</th>
            </tr>
            <tr>@for($d = 1; $d <= $daysInMonth; $d++)<th style="width: 11px;">{{ $d }}</th>@endfor</tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td rowspan="2" class="name-cell">{{ $row['pelaksana'] }}</td>
                    <td class="status-cell">RENCANA</td>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        <td class="{{ in_array($d, $row['rencana']) ? 'done' : '' }}">{{ in_array($d, $row['rencana']) ? '1' : '' }}</td>
                    @endfor
                    <td rowspan="2" class="stat">{{ $row['rencana_count'] }}</td>
                    <td rowspan="2" class="stat">{{ $row['target'] }}</td>
                    <td rowspan="2" class="stat">{{ $row['realisasi_count'] }}</td>
                    <td rowspan="2" class="stat">{{ $row['performance'] }}%</td>
                </tr>
                <tr>
                    <td class="status-cell">REALISASI</td>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        <td class="{{ in_array($d, $row['realisasi']) ? 'done' : '' }}">{{ in_array($d, $row['realisasi']) ? '1' : '' }}</td>
                    @endfor
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
