<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Meeting Shift - {{ $unit->name }}</title>
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
        .name-cell { text-align: left !important; padding-left: 3px !important; }
        .shift-name { background-color: #f3f4f6; font-weight: bold; }
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
                <h2>JADWAL MEETING SHIFT PEMBANGKIT — {{ $monthName }} {{ $year }}</h2>
            </td>
            <td class="logo-box">@if($logoRight)<img src="{{ $logoRight }}">@else<strong>MKP</strong>@endif</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 160px;">Hari / Tanggal</th>
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
                @php $isShift = $row['row_type'] === 'shift'; @endphp
                <tr>
                    <td class="name-cell {{ $isShift ? 'shift-name' : '' }}">{{ $row['label'] }}</td>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php $v = $row['days'][(string) $d] ?? ''; @endphp
                        @if($isShift)
                            <td>{{ $v }}</td>
                        @else
                            <td class="{{ $v !== '' ? 'done' : '' }}">{{ $v !== '' ? '1' : '' }}</td>
                        @endif
                    @endfor
                    <td class="stat">{{ $isShift ? '' : $row['target'] }}</td>
                    <td class="stat">{{ $isShift ? '' : $row['target'] }}</td>
                    <td class="stat">{{ $isShift ? '' : $row['realisasi'] }}</td>
                    <td class="stat">{{ $row['performance'] === null ? '' : $row['performance'].'%' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
