<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Kegiatan Pemeliharaan P0 - P5 - {{ $unit->name }}</title>
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
            margin-bottom: 4px;
        }
        .header-table td {
            vertical-align: middle;
            border: 1px solid #000;
        }
        .logo-box {
            width: 130px;
            text-align: center;
            padding: 4px;
        }
        .logo-box img {
            max-height: 48px;
            max-width: 120px;
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
        }
        .title-box h2 {
            margin: 2px 0 0 0;
            font-size: 10px;
            font-weight: bold;
        }
        .legend-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            font-weight: bold;
        }
        .legend-table td {
            padding: 1px 4px;
            vertical-align: middle;
        }
        .color-box {
            display: inline-block;
            width: 16px;
            height: 11px;
            border: 1px solid #000;
            vertical-align: middle;
        }
        .info-bar {
            margin-top: 2px;
            margin-bottom: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            font-size: 7.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 2px 1px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table th {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .data-table th.th-red, .data-table td.td-red {
            background-color: #dc2626 !important;
            color: #ffffff !important;
            font-weight: bold;
        }
        .cell-yellow {
            background-color: #ffff00 !important;
            color: #000000 !important;
            font-weight: bold;
        }
        .cell-green {
            background-color: #92d050 !important;
            color: #000000 !important;
            font-weight: bold;
        }
        .cell-bold {
            font-weight: bold;
            color: #000;
        }
        .machine-name-cell {
            text-align: left;
            padding-left: 3px;
            font-weight: bold;
        }
        .machine-sub {
            font-size: 7px;
            font-weight: normal;
            color: #333;
        }
        .text-left {
            text-align: left !important;
            padding-left: 3px !important;
        }
        .whitespace-pre {
            white-space: pre-line;
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
                <h2>JADWAL KEGIATAN PEMELIHARAAN P0 - P5</h2>
            </td>
            <td class="logo-box">
                @if($logoRight)
                    <img src="{{ $logoRight }}" alt="MKP Logo">
                @else
                    <strong>MKP MITRA KARYA PRIMA</strong>
                @endif
            </td>
        </tr>
    </table>

    <table class="legend-table" style="margin-bottom: 4px;">
        <tr>
            <td style="width: 45px;"><strong>KET :</strong></td>
            <td style="width: 110px;">P0 = 50 JAM</td>
            <td style="width: 110px;">P3 = 500 JAM</td>
            <td style="width: 22px;"><span class="color-box" style="background-color: #ffff00;"></span></td>
            <td style="width: 150px;">Ganti Pelumas</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td>P1 = 125 JAM</td>
            <td>P4 = 1.500 JAM</td>
            <td style="width: 22px;"><span class="color-box" style="background-color: #92d050;"></span></td>
            <td style="width: 150px;">Ganti Pelumas+Cleaning Radiator</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td>P2 = 250 JAM</td>
            <td>P5 = 3.000 JAM</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <div class="info-bar">
        <div><strong>MKP &nbsp;&nbsp;: {{ strtoupper($unit->serviceUnit ? $unit->serviceUnit->name : $unit->name) }}</strong></div>
        <div><strong>BULAN : {{ $monthName }} {{ $year }}</strong></div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;">NO</th>
                <th rowspan="2" style="width: 125px;">MESIN / TIPE / S.N</th>
                <th style="width: 38px;">{{ $monthName }}</th>
                <th colspan="{{ count($days) }}">JENIS HAR</th>
                <th rowspan="2" style="width: 85px;">JAM OPERASI PEMELIHARAAN</th>
                <th rowspan="2" style="width: 85px;">KETERANGAN</th>
            </tr>
            <tr>
                <th>{{ $year }}</th>
                @foreach($days as $d)
                    <th class="{{ $d['is_red'] ? 'th-red' : '' }}" style="width: 16px;">
                        {{ $d['day'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $idx => $row)
                @php
                    $rencanaColors = $row['warna']['rencana'] ?? [];
                    $realisasiColors = $row['warna']['realisasi'] ?? [];
                @endphp

                {{-- Row 1: RENC --}}
                <tr>
                    <td rowspan="3" style="font-weight: bold;">{{ $idx + 1 }}</td>
                    <td rowspan="2" class="machine-name-cell">
                        <div>{{ $row['name'] }}</div>
                        <div class="machine-sub">{{ $row['type'] }}</div>
                        @if($row['serial_number'])
                            <div class="machine-sub">SN. {{ $row['serial_number'] }}</div>
                        @endif
                    </td>
                    <td style="font-weight: bold; background: #f8fafc;">RENC</td>
                    @foreach($days as $d)
                        @php
                            $val = $row['rencana'][$d['day']] ?? '';
                            $customColor = $rencanaColors[$d['day']] ?? '';
                            $cellClass = '';
                            if ($customColor === 'yellow') {
                                $cellClass = 'cell-yellow';
                            } elseif ($customColor === 'green') {
                                $cellClass = 'cell-green';
                            } elseif ($val) {
                                $cellClass = 'cell-bold';
                            }
                            if (!$cellClass && $d['is_red']) {
                                $cellClass = 'td-red';
                            }
                        @endphp
                        <td class="{{ $cellClass }}">
                            {{ $val }}
                        </td>
                    @endforeach
                    <td rowspan="3" class="text-left whitespace-pre">{{ $row['operating_hours'] }}</td>
                    <td rowspan="3" class="text-left whitespace-pre">{{ $row['keterangan'] }}</td>
                </tr>

                {{-- Row 2: REAL --}}
                <tr>
                    <td style="font-weight: bold; background: #f8fafc;">REAL</td>
                    @foreach($days as $d)
                        @php
                            $val = $row['realisasi'][$d['day']] ?? '';
                            $customColor = $realisasiColors[$d['day']] ?? '';
                            $cellClass = '';
                            if ($customColor === 'yellow') {
                                $cellClass = 'cell-yellow';
                            } elseif ($customColor === 'green') {
                                $cellClass = 'cell-green';
                            } elseif ($val === 'P2') {
                                $cellClass = 'cell-yellow';
                            } elseif ($val === 'P3') {
                                $cellClass = 'cell-green';
                            } elseif ($val) {
                                $cellClass = 'cell-yellow';
                            } elseif ($d['is_red']) {
                                $cellClass = 'td-red';
                            }
                        @endphp
                        <td class="{{ $cellClass }}">
                            {{ $val }}
                        </td>
                    @endforeach
                </tr>

                {{-- Row 3: WAKTU & DURASI --}}
                <tr>
                    <td style="font-weight: bold; background: #f8fafc;">WAKTU</td>
                    <td style="font-weight: bold; background: #f8fafc;">DURASI</td>
                    @foreach($days as $d)
                        @php
                            $val = $row['durasi'][$d['day']] ?? '';
                        @endphp
                        <td class="{{ $d['is_red'] ? 'td-red' : '' }}">
                            {{ $val }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($days) + 5 }}" style="padding: 15px; color: #64748b;">
                        Belum ada data mesin aktif pada unit ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
