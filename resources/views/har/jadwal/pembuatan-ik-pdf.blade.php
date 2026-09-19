<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pembuatan IK Pemeliharaan - {{ $unit->name }} - {{ $year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 7mm 7mm 7mm 7mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 7.5px;
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
            padding: 3px;
        }
        .logo-box img {
            max-height: 42px;
            max-width: 130px;
        }
        .title-box {
            text-align: center;
            padding: 0;
        }
        .title-row {
            padding: 2.5px 4px;
            border-bottom: 1px solid #000;
            font-size: 9.5px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .title-row:last-child {
            border-bottom: none;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 7.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 2.5px 2px;
            text-align: center;
            vertical-align: middle;
        }
        .th-orange {
            background-color: #ed7d31 !important;
            color: #000000 !important;
            font-weight: bold;
        }
        .category-row td {
            background-color: #ffffff;
            font-weight: bold;
            padding: 2.5px 4px;
        }
        .category-name {
            text-align: left !important;
            padding-left: 6px !important;
            font-weight: bold;
            text-transform: uppercase;
        }
        .ik-title {
            text-align: left !important;
            padding-left: 5px !important;
            font-weight: normal;
        }
        .pic-name {
            text-align: left !important;
            padding-left: 5px !important;
            font-weight: normal;
            text-transform: uppercase;
        }
        .month-cell {
            font-weight: bold;
            font-size: 8px;
        }
        .month-cell-rencana {
            color: #b45309;
            font-weight: bold;
        }
        .month-cell-realisasi {
            color: #047857;
            font-weight: bold;
        }
        .month-cell-both {
            color: #0f766e;
            font-weight: bold;
        }
        .recap-table {
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 7.5px;
            margin-top: 6px;
        }
        .recap-table th, .recap-table td {
            border: 1px solid #000;
            padding: 2.5px 6px;
            vertical-align: middle;
        }
        .total-row td {
            font-weight: bold;
            background-color: #f9fafb;
        }
    </style>
</head>
<body>

    <!-- Header Box matching media_1789442233227.png -->
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
                <div class="title-row">JASA PENDUKUNG TEKNIS 6 SITE - KIT UP KENDARI</div>
                <div class="title-row">LAPORAN PROJECT {{ strtoupper($unit->name) }}</div>
                <div class="title-row">JADWAL PEMBUATAN IK PEMELIHARAAN PEMBANGKIT - TAHUN {{ $year }}</div>
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

    <!-- Main Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" class="th-orange" style="width: 26px;">NO</th>
                <th rowspan="2" class="th-orange" style="width: 250px;">INSTRUKSI KERJA</th>
                <th rowspan="2" class="th-orange" style="width: 140px;">PIC PEMBUAT</th>
                <th colspan="12" class="th-orange">BULAN</th>
                <th rowspan="2" class="th-orange" style="width: 45px;">JUMLAH</th>
            </tr>
            <tr>
                <th class="th-orange" style="width: 24px;">JAN</th>
                <th class="th-orange" style="width: 24px;">FEB</th>
                <th class="th-orange" style="width: 24px;">MAR</th>
                <th class="th-orange" style="width: 24px;">APR</th>
                <th class="th-orange" style="width: 24px;">MAY</th>
                <th class="th-orange" style="width: 24px;">JUN</th>
                <th class="th-orange" style="width: 24px;">JUL</th>
                <th class="th-orange" style="width: 24px;">AUG</th>
                <th class="th-orange" style="width: 24px;">SEP</th>
                <th class="th-orange" style="width: 24px;">OCT</th>
                <th class="th-orange" style="width: 24px;">NOV</th>
                <th class="th-orange" style="width: 24px;">DEC</th>
            </tr>
        </thead>
        <tbody>
            <!-- Category Divider Row -->
            <tr class="category-row">
                <td>A.</td>
                <td class="category-name" colspan="14">PEMBUATAN INTRUKSI KERJA</td>
            </tr>

            <!-- IK Rows -->
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['no_urut'] }}</td>
                    <td class="ik-title">{{ $row['instruksi_kerja'] }}</td>
                    <td class="pic-name">{{ $row['pic_pembuat'] }}</td>
                    @for($m = 1; $m <= 12; $m++)
                        @php
                            $inRencana = in_array($m, $row['rencana_bulan']);
                            $inRealisasi = in_array($m, $row['realisasi_bulan']);
                        @endphp
                        <td class="month-cell">
                            @if($inRencana && $inRealisasi)
                                <span class="month-cell-both">R &amp; &#10003;</span>
                            @elseif($inRencana)
                                <span class="month-cell-rencana">R</span>
                            @elseif($inRealisasi)
                                <span class="month-cell-realisasi">&#10003;</span>
                            @endif
                        </td>
                    @endfor
                    <td style="font-weight: bold;">{{ $row['jumlah'] }}</td>
                </tr>
            @endforeach

            <!-- Total IK Row at bottom of table -->
            <tr class="total-row">
                <td colspan="3" style="text-align: center; font-weight: bold; padding: 3px 0;">TOTAL IK</td>
                @for($m = 1; $m <= 12; $m++)
                    <td>{{ $monthTotals[$m] }}</td>
                @endfor
                <td>{{ $grandTotalIk }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Recap Table at Bottom (Matches image) -->
    <table class="recap-table" style="width: 480px;">
        <thead>
            <tr>
                <th class="th-orange" style="width: 26px; text-align: center;">A.</th>
                <th class="th-orange" style="width: 220px; text-align: left; padding-left: 6px;">IK</th>
                <th class="th-orange" style="width: 70px; text-align: center;">NILAI</th>
                <th class="th-orange" style="width: 70px; text-align: center;">A.DATA</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center;">1</td>
                <td style="text-align: left; padding-left: 6px; font-weight: bold;">RENCANA</td>
                <td style="text-align: center; font-weight: bold;">{{ $totalRencana }}</td>
                <td style="text-align: center; font-weight: bold;">{{ $kinerja }}%</td>
            </tr>
            <tr>
                <td style="text-align: center;">2</td>
                <td style="text-align: left; padding-left: 6px; font-weight: bold;">REALISASI</td>
                <td style="text-align: center; font-weight: bold;">{{ $totalRealisasi }}</td>
                <td style="text-align: center;"></td>
            </tr>
        </tbody>
    </table>

</body>
</html>
