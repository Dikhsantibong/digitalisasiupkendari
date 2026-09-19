<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Meeting Pemeliharaan Pembangkit - {{ $unit->name }}</title>
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
            margin-bottom: 6px;
        }
        .header-table td {
            vertical-align: middle;
            border: 1px solid #000;
        }
        .logo-box {
            width: 140px;
            text-align: center;
            padding: 4px;
        }
        .logo-box img {
            max-height: 48px;
            max-width: 130px;
        }
        .title-box {
            text-align: center;
            padding: 4px;
        }
        .title-box h1 {
            margin: 0;
            font-size: 11px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .title-box h2 {
            margin: 2px 0 0 0;
            font-size: 10px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .title-box h3 {
            margin: 2px 0 0 0;
            font-size: 9.5px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 7.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 2.5px 1px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table th {
            background-color: #ed7d31;
            color: #000000;
            font-weight: bold;
        }
        .data-table th.text-red {
            color: #dc2626 !important;
            font-weight: bold;
        }
        .uraian-cell {
            text-align: left !important;
            padding-left: 6px !important;
            font-weight: bold;
            font-size: 8px;
        }
        .category-cell {
            font-weight: bold;
            font-size: 7px;
            background-color: #ffffff;
        }
        .recap-cell {
            background-color: #ffffff;
            font-weight: bold;
            font-size: 8.5px;
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
                <h1>JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI</h1>
                <h2>LAPORAN PROJECT {{ strtoupper($unit->name) }}</h2>
                <h3>JADWAL MEETING PEMELIHARAAN PEMBANGKIT</h3>
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
            <!-- Header Row 1 -->
            <tr>
                <th rowspan="2" style="width: 130px;">URAIAN</th>
                <th style="width: 70px;">{{ $monthName }}</th>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'text-red' : '' }}" style="width: 16px;">
                        {{ $day['dow'] }}
                    </th>
                @endforeach
                <th rowspan="2" style="width: 50px;">RENCANA</th>
                <th rowspan="2" style="width: 45px;">TARGET</th>
                <th rowspan="2" style="width: 50px;">REALISASI</th>
                <th rowspan="2" style="width: 60px;">ANALISA KINERJA</th>
            </tr>
            <!-- Header Row 2 -->
            <tr>
                <th>{{ $year }}</th>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'text-red' : '' }}">
                        {{ $day['day'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <!-- Subrow 1: Rencana -->
                <tr>
                    <td rowspan="2" class="uraian-cell">{{ $row['uraian'] }}</td>
                    <td class="category-cell">RENCANA</td>
                    @foreach($days as $day)
                        @php
                            $d = (string) $day['day'];
                            $valRencana = $row['rencana'][$d] ?? 0;
                        @endphp
                        <td>{{ $valRencana ?: 0 }}</td>
                    @endforeach
                    <td rowspan="2" class="recap-cell">{{ $row['total_rencana'] }}</td>
                    <td rowspan="2" class="recap-cell">{{ $row['target'] }}</td>
                    <td rowspan="2" class="recap-cell">{{ $row['total_realisasi'] }}</td>
                    <td rowspan="2" class="recap-cell">{{ $row['performance'] }}%</td>
                </tr>

                <!-- Subrow 2: Realisasi -->
                <tr>
                    <td class="category-cell">REALISASI</td>
                    @foreach($days as $day)
                        @php
                            $d = (string) $day['day'];
                            $valRealisasi = $row['realisasi'][$d] ?? 0;
                        @endphp
                        <td>{{ $valRealisasi ?: 0 }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 2 + count($days) + 4 }}" style="padding: 12px; text-align: center; font-style: italic;">
                        Tidak ada data meeting pemeliharaan.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
