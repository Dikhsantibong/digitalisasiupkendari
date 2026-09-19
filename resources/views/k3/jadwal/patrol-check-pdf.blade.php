<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Patrol Check Harian K3L - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page { size: A4 landscape; margin: 7mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 7px; color: #000; background: #fff; margin: 0; padding: 0; }
        .header-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 6px; }
        .header-table td { vertical-align: middle; border: 1px solid #000; }
        .logo-box { width: 120px; text-align: center; padding: 3px; }
        .logo-box img { max-height: 40px; max-width: 110px; }
        .title-box { text-align: center; padding: 3px 6px; }
        .title-box h1 { margin: 0; font-size: 9.5px; font-weight: bold; line-height: 1.25; text-transform: uppercase; }
        .title-box h2 { margin: 1.5px 0 0 0; font-size: 8.5px; font-weight: bold; line-height: 1.25; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 7px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 1.5px 1px; text-align: center; vertical-align: middle; }
        .data-table thead th { background-color: #ffffff; font-weight: bold; color: #000; }
        .th-day-red { color: #dc2626 !important; font-weight: bold; }
        .td-red { background-color: #ff0000 !important; }
        .td-green { background-color: #22c55e !important; }
        .td-yellow { background-color: #fde047 !important; }
        .name-cell { text-align: left !important; padding-left: 4px !important; }
        .status-cell { font-weight: bold; background-color: #f3f4f6; }
        .stat-cell { font-weight: bold; }
        .ket-cell { text-align: left !important; padding-left: 3px !important; }
        .sla-row td { font-weight: bold; background-color: #e5e7eb; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="logo-box">
                @if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN">@else<strong>PLN Nusantara Power</strong>@endif
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS 11 &amp; 6 SITE - KIT</h1>
                <h2>PLN NP UP KENDARI - {{ strtoupper($unit->name) }} · LAPORAN PROJECT</h2>
                <h2>PATROL CHECK HARIAN K3 DAN LINGKUNGAN BULAN {{ $monthName }} {{ $year }}</h2>
            </td>
            <td class="logo-box">
                @if($logoRight)<img src="{{ $logoRight }}" alt="MKP">@else<strong>Mitra Karya Prima</strong>@endif
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 18px;">No</th>
                <th rowspan="2" style="width: 190px;">Uraian Pekerjaan</th>
                <th rowspan="2" style="width: 28px;">Status</th>
                <th colspan="{{ count($days) }}">Tanggal</th>
                <th rowspan="2" style="width: 28px;">TARGET</th>
                <th rowspan="2" style="width: 34px;">REALISASI</th>
                <th rowspan="2" style="width: 40px;">PERSENTASE</th>
                <th rowspan="2" style="width: 100px;">KETERANGAN</th>
                <th rowspan="2" style="width: 34px;">BOBOT SLA</th>
            </tr>
            <tr>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'th-day-red' : '' }}" style="width: 12px; padding: 1px 0;">{{ $day['day'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td rowspan="2">{{ $row['no_urut'] }}</td>
                    <td rowspan="2" class="name-cell">{{ $row['uraian'] }}</td>
                    <td class="status-cell">RENC</td>
                    @foreach($days as $day)
                        @php $planned = in_array($day['day'], $row['rencana']); $realized = in_array($day['day'], $row['realisasi']); @endphp
                        @if($day['is_red'])
                            <td class="td-red"></td>
                        @elseif($planned)
                            <td class="{{ $realized ? 'td-green' : 'td-yellow' }}" style="font-weight: bold;">1</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                    <td rowspan="2" class="stat-cell">{{ $row['target'] }}</td>
                    <td rowspan="2" class="stat-cell">{{ $row['realisasi_count'] }}</td>
                    <td rowspan="2" class="stat-cell">{{ $row['performance'] }}%</td>
                    <td rowspan="2" class="ket-cell">{{ $row['keterangan'] }}</td>
                    <td rowspan="2" class="stat-cell">{{ rtrim(rtrim(number_format((float) $row['bobot_sla'], 2, '.', ''), '0'), '.') }}</td>
                </tr>
                <tr>
                    <td class="status-cell">REAL</td>
                    @foreach($days as $day)
                        @if($day['is_red'])
                            <td class="td-red"></td>
                        @elseif(in_array($day['day'], $row['realisasi']))
                            <td class="td-green" style="font-weight: bold;">1</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
            <tr class="sla-row">
                <td colspan="{{ 3 + count($days) + 3 }}" style="text-align: right; padding-right: 5px; text-transform: uppercase;">Bobot SLA Keseluruhan</td>
                <td></td>
                <td>{{ $overallSla }}%</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
