<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $barTitle }} - {{ $unit->name }} - {{ $year }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 8px; color: #000; margin: 0; padding: 0; }
        .header-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 6px; }
        .header-table td { vertical-align: middle; border: 1px solid #000; }
        .logo-box { width: 120px; text-align: center; padding: 3px; }
        .logo-box img { max-height: 40px; max-width: 110px; }
        .title-box { text-align: center; padding: 3px 6px; }
        .title-box h1 { margin: 0; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .title-box h2 { margin: 1px 0 0; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 8px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 2px 3px; text-align: center; vertical-align: middle; }
        .data-table thead th { background-color: #ea7315; color: #fff; font-weight: bold; }
        .name-cell { text-align: left !important; padding-left: 4px !important; }
        .section-row td { background-color: #fbe0c8; font-weight: bold; text-align: left; padding-left: 5px; text-transform: uppercase; }
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
                <h2>{{ $barTitle }} — TAHUN {{ $year }}</h2>
            </td>
            <td class="logo-box">@if($logoRight)<img src="{{ $logoRight }}">@else<strong>MKP</strong>@endif</td>
        </tr>
    </table>

    @php $months = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES']; @endphp
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 24px;">No</th>
                <th rowspan="2" style="width: 320px;">{{ $namaHeader }}</th>
                <th rowspan="2" style="width: 110px;">PIC Pembuat</th>
                <th colspan="12">Bulan</th>
                <th rowspan="2" style="width: 40px;">Jumlah</th>
            </tr>
            <tr>@foreach($months as $m)<th style="width: 30px;">{{ $m }}</th>@endforeach</tr>
        </thead>
        <tbody>
            <tr class="section-row"><td colspan="16">{{ $sectionTitle }}</td></tr>
            @foreach($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="name-cell">{{ $row['nama'] }}</td>
                    <td>{{ $row['pic'] }}</td>
                    @for($m = 1; $m <= 12; $m++)
                        <td class="{{ !empty($row['months'][(string) $m]) ? 'done' : '' }}">{{ !empty($row['months'][(string) $m]) ? '1' : '' }}</td>
                    @endfor
                    <td class="stat">{{ $row['jumlah'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
