<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Piket Oncall Pemeliharaan - {{ $unit->name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 8mm 8mm 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 8px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: middle;
            border: 1px solid #000;
        }
        .logo-box {
            width: 140px;
            text-align: center;
            padding: 2px;
        }
        .logo-box img {
            max-height: 48px;
            max-width: 130px;
        }
        .title-box {
            text-align: center;
            padding: 2px 8px;
        }
        .title-box h1 {
            margin: 0;
            font-size: 11px;
            font-weight: bold;
            line-height: 1.3;
            text-transform: uppercase;
        }
        .title-box h2 {
            margin: 2px 0 0 0;
            font-size: 10.5px;
            font-weight: bold;
            line-height: 1.3;
            text-transform: uppercase;
        }
        .title-box h3 {
            margin: 2px 0 0 0;
            font-size: 10px;
            font-weight: bold;
            line-height: 1.3;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 8px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table thead th {
            background-color: #d1d5db;
            font-weight: bold;
            color: #000;
        }
        .th-day-red {
            color: #dc2626 !important;
            font-weight: bold;
        }
        .category-row td {
            background-color: #f3f4f6;
            font-weight: bold;
            padding: 3px 4px;
        }
        .category-name {
            text-align: left !important;
            padding-left: 6px !important;
            font-weight: bold;
            text-transform: uppercase;
        }
        .person-name {
            text-align: left !important;
            padding-left: 6px !important;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .cell-piket-red {
            background-color: #dc2626 !important;
            color: #000000 !important;
            font-weight: bold;
        }
        .cell-piket-normal {
            font-weight: bold;
            color: #000000;
        }
        .phone-cell {
            font-size: 7.5px;
            text-align: left !important;
            padding-left: 4px !important;
        }
        .stat-cell {
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="logo-box">
                @if($logoLeft)
                    <img src="{{ $logoLeft }}" alt="PLN Logo">
                @else
                    <strong>PLN Nusantara Power</strong>
                @endif
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS 6 SITE UP KENDARI</h1>
                <h2>JADWAL PIKET ONCALL PEMELIHARAAN PEMBANGKIT BULAN {{ $monthName }} {{ $year }}</h2>
                <h3>{{ strtoupper($unit->name) }}</h3>
            </td>
            <td class="logo-box">
                @if($logoRight)
                    <img src="{{ $logoRight }}" alt="MKP Logo">
                @else
                    <strong>Mitra Karya Prima</strong>
                @endif
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 26px;">No.</th>
                <th rowspan="2" style="width: 140px;">Nama</th>
                <th rowspan="2" style="width: 95px;">No. Hp</th>
                <th colspan="{{ count($days) }}">{{ $monthName }} {{ $year }}</th>
                <th rowspan="2" style="width: 45px;">TARGET</th>
                <th rowspan="2" style="width: 55px;">REALISASI</th>
                <th rowspan="2" style="width: 65px;">A. KINERJA</th>
            </tr>
            <tr>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'th-day-red' : '' }}" style="width: 17px; padding: 2px 0;">
                        {{ $day['day'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $group)
                <!-- Category Divider Row -->
                <tr class="category-row">
                    <td>{{ $group['roman'] }}</td>
                    <td class="category-name">{{ $group['kategori'] }}</td>
                    <td></td>
                    @foreach($days as $day)
                        <td></td>
                    @endforeach
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>

                <!-- Personnel Rows -->
                @foreach($group['personnel'] as $person)
                    <tr>
                        <td>{{ $person['no'] }}</td>
                        <td class="person-name">{{ $person['nama'] }}</td>
                        <td class="phone-cell">{{ $person['no_hp'] }}</td>
                        @foreach($days as $day)
                            @php
                                $isPiket = in_array($day['day'], $person['piket']);
                            @endphp
                            @if($isPiket && $day['is_red'])
                                <td class="cell-piket-red">1</td>
                            @elseif($isPiket)
                                <td class="cell-piket-normal">1</td>
                            @else
                                <td></td>
                            @endif
                        @endforeach
                        <td class="stat-cell">{{ $person['target'] }}</td>
                        <td class="stat-cell">{{ $person['realisasi'] }}</td>
                        <td class="stat-cell">{{ $person['performance'] }}%</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="{{ count($days) + 6 }}" style="padding: 16px; text-align: center; color: #666;">
                        Belum ada personil yang dijadwalkan untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
