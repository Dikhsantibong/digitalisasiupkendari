<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pekerjaan Rutin K3L - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 7px; color: #000; background: #fff; margin: 0; padding: 0; }
        .header-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 5px; }
        .header-table td { vertical-align: middle; border: 1px solid #000; }
        .logo-box { width: 120px; text-align: center; padding: 3px; }
        .logo-box img { max-height: 38px; max-width: 110px; }
        .title-box { text-align: center; padding: 3px 6px; }
        .title-box h1 { margin: 0; font-size: 9.5px; font-weight: bold; line-height: 1.25; text-transform: uppercase; }
        .title-box h2 { margin: 1.5px 0 0 0; font-size: 8.5px; font-weight: bold; line-height: 1.25; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 6.8px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 1.5px 1px; text-align: center; vertical-align: middle; }
        .data-table thead th { background-color: #ffffff; font-weight: bold; color: #000; }
        .th-day-red { color: #dc2626 !important; font-weight: bold; }
        .td-red { background-color: #ff0000 !important; }
        .td-green { background-color: #22c55e !important; color: #000 !important; font-weight: bold; }
        .td-yellow { background-color: #fde047 !important; color: #000 !important; font-weight: bold; }
        .name-cell { text-align: left !important; padding-left: 4px !important; font-weight: 600; }
        .status-cell { font-weight: bold; background-color: #f3f4f6; }
        .stat-cell { font-weight: bold; }
        .paraf-cell { font-size: 6.5px; color: #4b5563; }
        .legend-box { margin-top: 6px; font-size: 7px; line-height: 1.35; color: #111; }
        .legend-box .title { font-weight: bold; margin-bottom: 2px; }
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
                <h2>JADWAL PEKERJAAN RUTIN K3L BULAN {{ $monthName }} {{ $year }}</h2>
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
                <th rowspan="2" style="width: 220px;">URAIAN</th>
                <th rowspan="2" style="width: 30px;">STATUS</th>
                <th colspan="{{ count($days) }}">TANGGAL</th>
                <th rowspan="2" style="width: 32px;">TARGET</th>
                <th rowspan="2" style="width: 32px;">REAL</th>
                <th rowspan="2" style="width: 42px;">KINERJA</th>
                <th rowspan="2" style="width: 50px;">PARAF</th>
            </tr>
            <tr>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'th-day-red' : '' }}" style="width: 12px; padding: 1px 0;">
                        {{ str_pad($day['day'], 2, '0', STR_PAD_LEFT) }}
                    </th>
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
                        @php
                            $planned = in_array($day['day'], $row['rencana']);
                            $realized = in_array($day['day'], $row['realisasi']);
                        @endphp
                        @if($day['is_red'])
                            <td class="td-red"></td>
                        @elseif($planned)
                            <td class="{{ $realized ? 'td-green' : 'td-yellow' }}">1</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                    <td rowspan="2" class="stat-cell">{{ $row['target'] }}</td>
                    <td rowspan="2" class="stat-cell">{{ $row['realisasi_count'] }}</td>
                    <td rowspan="2" class="stat-cell">{{ $row['performance'] ?? 0 }}%</td>
                    <td rowspan="2" class="paraf-cell">{{ $row['paraf'] ?: 'Empty' }}</td>
                </tr>
                <tr>
                    <td class="status-cell">REAL</td>
                    @foreach($days as $day)
                        @if($day['is_red'])
                            <td class="td-red"></td>
                        @elseif(in_array($day['day'], $row['realisasi']))
                            <td class="td-green">1</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="legend-box">
        <div class="title">Keterangan :</div>
        <div>v : kondisi baik</div>
        <div>x : kondisi tidak baik</div>
    </div>

</body>
</html>
