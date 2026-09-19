<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Kegiatan K3L - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page { size: A4 landscape; margin: 7mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 7.5px; color: #000; background: #fff; margin: 0; padding: 0; }
        .header-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 6px; }
        .header-table td { vertical-align: middle; border: 1px solid #000; }
        .logo-box { width: 130px; text-align: center; padding: 3px; }
        .logo-box img { max-height: 42px; max-width: 120px; }
        .title-box { text-align: center; padding: 3px 6px; }
        .title-box h1 { margin: 0; font-size: 10px; font-weight: bold; line-height: 1.25; text-transform: uppercase; }
        .title-box h2 { margin: 1.5px 0 0 0; font-size: 9.5px; font-weight: bold; line-height: 1.25; text-transform: uppercase; }
        .title-box h3 { margin: 1.5px 0 0 0; font-size: 8.5px; font-weight: bold; line-height: 1.25; text-transform: uppercase; }
        .meta-box { width: 150px; padding: 0; font-size: 7.5px; }
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { border: none; border-bottom: 1px solid #000; padding: 2.5px 4px; }
        .meta-table tr:last-child td { border-bottom: none; }
        .data-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 7.5px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 2.5px 1px; text-align: center; vertical-align: middle; }
        .data-table thead th { background-color: #ffffff; font-weight: bold; color: #000; }
        .th-day-red { color: #dc2626 !important; font-weight: bold; }
        .td-red { background-color: #ff0000 !important; color: #ffffff !important; }
        .group-row td { background-color: #e5e7eb !important; font-weight: bold; text-align: left; padding-left: 5px; text-transform: uppercase; }
        .group-roman { text-align: center !important; }
        .activity-name { text-align: left !important; padding-left: 5px !important; font-weight: normal; }
        .keterangan-cell { text-align: left !important; padding-left: 4px !important; font-size: 7px; }
        .stat-cell { font-weight: bold; text-align: center; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="logo-box">
                @if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Logo">@else<strong>PLN Nusantara Power</strong>@endif
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS 11 &amp; 6 SITE - KIT</h1>
                <h2>PLN NP UP KENDARI - {{ strtoupper($unit->name) }}</h2>
                <h3>LAPORAN PROJECT</h3>
                <h3>JADWAL KEGIATAN K3L</h3>
            </td>
            <td class="logo-box">
                @if($logoRight)<img src="{{ $logoRight }}" alt="MKP Logo">@else<strong>Mitra Karya Prima</strong>@endif
            </td>
            <td class="meta-box">
                <table class="meta-table">
                    <tr><td style="width: 75px;">Nomor Dokumen</td><td style="width: 5px;">:</td><td></td></tr>
                    <tr><td>Revisi</td><td>:</td><td></td></tr>
                    <tr><td>Tanggal Terbit</td><td>:</td><td></td></tr>
                    <tr><td>Halaman</td><td>:</td><td></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 24px;">No</th>
                <th rowspan="2" style="width: 200px;">Nama Peralatan / Kegiatan</th>
                <th colspan="{{ count($days) }}">Tanggal</th>
                <th rowspan="2" style="width: 38px;">TARGET</th>
                <th rowspan="2" style="width: 46px;">REALISASI</th>
                <th rowspan="2" style="width: 46px;">KINERJA</th>
                <th rowspan="2" style="width: 130px;">Keterangan</th>
            </tr>
            <tr>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'th-day-red' : '' }}" style="width: 16px; padding: 1.5px 0;">{{ $day['day'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($groups as $group)
                <tr class="group-row">
                    <td class="group-roman">{{ $group['roman'] }}</td>
                    <td colspan="{{ count($days) + 5 }}">{{ $group['label'] }}</td>
                </tr>
                @foreach($group['rows'] as $row)
                    <tr>
                        <td>{{ $row['no_urut'] }}</td>
                        <td class="activity-name">{{ $row['kegiatan'] }}</td>
                        @foreach($days as $day)
                            @if($day['is_red'])
                                <td class="td-red"></td>
                            @else
                                <td style="font-weight: bold;">{{ in_array($day['day'], $row['jadwal']) ? '1' : '' }}</td>
                            @endif
                        @endforeach
                        <td class="stat-cell">{{ $row['target'] }}</td>
                        <td class="stat-cell">{{ $row['realisasi_count'] }}</td>
                        <td class="stat-cell">{{ $row['performance'] }}%</td>
                        <td class="keterangan-cell">{{ $row['keterangan'] }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

</body>
</html>
