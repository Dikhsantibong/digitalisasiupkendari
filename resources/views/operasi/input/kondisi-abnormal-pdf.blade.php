<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kondisi Abnormal dan Gangguan Pembangkit - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 7mm;
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
            border: 2px solid #000;
            margin-bottom: 4px;
        }
        .header-table td {
            vertical-align: middle;
            border: 1px solid #000;
        }
        .logo-box-left {
            width: 130px;
            text-align: center;
            padding: 3px;
        }
        .logo-box-left img {
            max-height: 40px;
            max-width: 120px;
        }
        .logo-box-right {
            width: 130px;
            text-align: center;
            padding: 3px;
        }
        .logo-box-right img {
            max-height: 40px;
            max-width: 120px;
        }
        .title-box {
            text-align: center;
            padding: 3px 6px;
        }
        .title-box h1 {
            margin: 0;
            font-size: 9.5px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .title-box h2 {
            margin: 1.5px 0 0 0;
            font-size: 9px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .title-box h3 {
            margin: 1.5px 0 0 0;
            font-size: 9.5px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-header {
            width: 100%;
            background-color: #ea7315;
            color: #000;
            font-weight: bold;
            font-size: 9.5px;
            text-align: center;
            padding: 4px 0;
            border: 1.5px solid #000;
            border-bottom: none;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            font-size: 7.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 2.5px 4px;
            vertical-align: middle;
        }
        .data-table thead th {
            background-color: #ea7315;
            color: #000;
            font-weight: bold;
            text-align: center;
            font-size: 8px;
        }
        .text-center {
            text-align: center !important;
        }
        .text-right {
            text-align: right !important;
        }
        .font-bold {
            font-weight: bold;
        }
        .note-box {
            margin-top: 6px;
            font-size: 7.5px;
            font-weight: bold;
            line-height: 1.4;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="logo-box-left">
                @if($logoLeft)
                    <img src="{{ $logoLeft }}" alt="PLN Nusantara Power">
                @else
                    <strong>PLN Nusantara Power</strong>
                @endif
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT</h1>
                <h2>LAPORAN PROJECT {{ strtoupper($unit->name) }}</h2>
                <h3>LAPORAN KONDISI ABNORMAL DAN GANGGUAN PEMBANGKIT</h3>
                <div style="font-size: 7.5px; font-weight: normal; margin-top: 2px;">
                    Periode: {{ $monthName }} {{ $year }}
                </div>
            </td>
            <td class="logo-box-right">
                @if($logoRight)
                    <img src="{{ $logoRight }}" alt="MKP">
                @else
                    <strong>MITRA KARYA PRIMA</strong>
                @endif
            </td>
        </tr>
    </table>

    <div class="section-header">
        LAPORAN KONDISI ABNORMAL DAN GANGGUAN PEMBANGKIT
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 28px;">NO</th>
                <th rowspan="2" style="width: 250px;">URAIAN KONDISI</th>
                <th rowspan="2" style="width: 75px;">TANGGAL</th>
                <th colspan="4">STATUS</th>
            </tr>
            <tr>
                <th style="width: 55px;">ABNORMAL</th>
                <th style="width: 55px;">DURASI</th>
                <th style="width: 55px;">GANGGUAN</th>
                <th style="width: 55px;">DURASI</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td class="text-center font-bold">{{ $row['no_urut'] }}</td>
                    <td>{{ $row['uraian_kondisi'] }}</td>
                    <td class="text-center">{{ $row['tanggal'] }}</td>
                    <td class="text-center font-bold">{{ $row['is_abnormal'] }}</td>
                    <td class="text-center">{{ $row['durasi_abnormal'] }}</td>
                    <td class="text-center font-bold">{{ $row['is_gangguan'] }}</td>
                    <td class="text-center">{{ $row['durasi_gangguan'] }}</td>
                </tr>
            @endforeach
            <tr style="background-color: #f9f9f9;" class="font-bold">
                <td colspan="3" class="text-center font-bold">TOTAL</td>
                <td class="text-center">{{ $totalAbnormalCount }}</td>
                <td class="text-center">{{ $totalAbnormalDurasi > 0 ? $totalAbnormalDurasi : 0 }}</td>
                <td class="text-center">{{ $totalGangguanCount }}</td>
                <td class="text-center">{{ $totalGangguanDurasi > 0 ? $totalGangguanDurasi : 0 }}</td>
            </tr>
        </tbody>
    </table>

    <div class="note-box">
        NOTE : 1. KOLOM ABNORMAL DAN GANGGUAN DI ISI ANGKA 1<br>
        2. KOLOM DURASI DI ISI JAM
    </div>

</body>
</html>
