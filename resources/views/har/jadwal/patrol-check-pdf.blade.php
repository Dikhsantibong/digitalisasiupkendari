<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Piket Patrol Check Harian - {{ $unit->name }}</title>
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
            font-size: 8px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table th {
            background-color: #ffffff;
            font-weight: bold;
        }
        .data-table th.th-red, .data-table td.td-red {
            background-color: #dc2626 !important;
            color: #000000;
            font-weight: bold;
        }
        .cell-piket {
            background-color: #00b0f0 !important;
            color: #000000 !important;
            font-weight: bold;
        }
        .operator-name {
            text-align: left !important;
            padding-left: 6px !important;
            font-weight: bold;
            text-transform: uppercase;
        }
        .recap-cell {
            background-color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            text-align: center;
        }
        .footer-table {
            margin-top: 12px;
            font-size: 8.5px;
        }
        .footer-table td {
            vertical-align: middle;
            padding: 2px 0;
        }
        .color-box {
            display: inline-block;
            width: 20px;
            height: 12px;
            border: 1px solid #000;
            margin-right: 4px;
            vertical-align: middle;
        }
        .note-text {
            font-style: italic;
            font-size: 8px;
            margin-top: 4px;
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
                <h1>JASA PENDUKUNG TEKNIS - 6 SITE</h1>
                <h1>PLN NP UP KENDARI - {{ strtoupper($unit->name) }}</h1>
                <h2>LAPORAN PROJECT</h2>
                <h2>JADWAL PIKET PATROL CHECK HARIAN</h2>
                <h3>PERIODE : {{ $monthName }} {{ $year }}</h3>
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
                <th style="width: 140px;" rowspan="1">NAMA</th>
                <th style="width: 90px;" rowspan="1">RENCANA / REALISASI</th>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'th-red' : '' }}" style="width: 18px;">
                        {{ $day['day'] }}
                    </th>
                @endforeach
                <th style="width: 50px;">RENCANA</th>
                <th style="width: 50px;">REALISASI</th>
                <th style="width: 50px;">TARGET</th>
                <th style="width: 60px;">ANALISA KINERJA</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <!-- Rencana Row -->
                <tr>
                    <td rowspan="2" class="operator-name">{{ $row['name'] }}</td>
                    <td>RENCANA</td>
                    @foreach($days as $day)
                        @php
                            $d = (string) $day['day'];
                            $valRencana = $row['rencana'][$d] ?? null;
                            $hasPiket = !empty($valRencana) && (string) $valRencana === '1';
                        @endphp
                        @if($day['is_red'])
                            <td class="td-red"></td>
                        @elseif($hasPiket)
                            <td class="cell-piket">1</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach

                    @if($loop->first)
                        <td rowspan="{{ count($rows) * 2 }}" class="recap-cell">{{ $totalRencana }}</td>
                        <td rowspan="{{ count($rows) * 2 }}" class="recap-cell">{{ $totalRealisasi }}</td>
                        <td rowspan="{{ count($rows) * 2 }}" class="recap-cell">{{ $targetWorkingDays }}</td>
                        <td rowspan="{{ count($rows) * 2 }}" class="recap-cell">{{ $performance }}%</td>
                    @endif
                </tr>

                <!-- Realisasi Row -->
                <tr>
                    <td>REALISASI</td>
                    @foreach($days as $day)
                        @php
                            $d = (string) $day['day'];
                            $valRealisasi = $row['realisasi'][$d] ?? null;
                            $hasPiket = !empty($valRealisasi) && (string) $valRealisasi === '1';
                        @endphp
                        @if($day['is_red'])
                            <td class="td-red"></td>
                        @elseif($hasPiket)
                            <td class="cell-piket">1</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 2 + count($days) + 4 }}" style="padding: 12px; text-align: center; font-style: italic;">
                        Tidak ada data operator untuk unit ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td style="width: 90px; font-weight: bold;">Keterangan:</td>
            <td style="width: 28px;"><div class="color-box" style="background-color: #00b0f0;"></div></td>
            <td>: Hari Piket</td>
        </tr>
        <tr>
            <td></td>
            <td><div class="color-box" style="background-color: #dc2626;"></div></td>
            <td>: Off/Libur</td>
        </tr>
        <tr>
            <td style="font-style: italic; font-weight: bold; padding-top: 8px;">Note:</td>
            <td colspan="2" style="font-style: italic; padding-top: 8px;">
                Jika personel berhalangan, harap digantikan dengan personel lain
            </td>
        </tr>
    </table>

</body>
</html>
