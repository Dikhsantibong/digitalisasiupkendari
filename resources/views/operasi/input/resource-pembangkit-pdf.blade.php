<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Resource Pembangkit - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 8.5px;
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
            padding: 4px;
        }
        .logo-box-left img {
            max-height: 44px;
            max-width: 120px;
        }
        .logo-box-right {
            width: 130px;
            text-align: center;
            padding: 4px;
        }
        .logo-box-right img {
            max-height: 44px;
            max-width: 120px;
        }
        .title-box {
            text-align: center;
            padding: 4px 6px;
        }
        .title-box h1 {
            margin: 0;
            font-size: 10px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .title-box h2 {
            margin: 2px 0 0 0;
            font-size: 9.5px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .title-box h3 {
            margin: 2px 0 0 0;
            font-size: 10px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-header {
            width: 100%;
            background-color: #ea7315;
            color: #000;
            font-weight: bold;
            font-size: 10px;
            text-align: center;
            padding: 5px 0;
            border: 1.5px solid #000;
            border-bottom: none;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            font-size: 8px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 3.5px 6px;
            vertical-align: middle;
        }
        .data-table thead th {
            background-color: #ea7315;
            color: #000;
            font-weight: bold;
            text-align: center;
            font-size: 8.5px;
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
                <h3>LAPORAN RESOURCE PEMBANGKIT</h3>
                <div style="font-size: 8px; font-weight: normal; margin-top: 2px;">
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
        LAPORAN BBM PEMBANGKIT
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 45px;">TGL</th>
                <th style="width: 130px;">STOK AWAL</th>
                <th style="width: 130px;">PEMAKAIAN</th>
                <th style="width: 130px;">PENGIRIMAN</th>
                <th style="width: 130px;">STOK AKHIR</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td class="text-center font-bold">{{ $row['tanggal'] }}</td>
                    <td class="text-right">{{ $row['stok_awal'] > 0 ? number_format($row['stok_awal'], 0, ',', '.') : '' }}</td>
                    <td class="text-right">{{ $row['pemakaian'] > 0 ? number_format($row['pemakaian'], 0, ',', '.') : '' }}</td>
                    <td class="text-right">{{ $row['pengiriman'] > 0 ? number_format($row['pengiriman'], 0, ',', '.') : '' }}</td>
                    <td class="text-right">{{ $row['stok_akhir'] > 0 ? number_format($row['stok_akhir'], 0, ',', '.') : '0' }}</td>
                </tr>
            @endforeach
            <tr style="background-color: #f9f9f9;" class="font-bold">
                <td class="text-center">TOTAL</td>
                <td class="text-right">-</td>
                <td class="text-right">{{ number_format($totalPemakaian, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalPengiriman, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($latestStokAkhir, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
