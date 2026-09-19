<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Unsafe Action dan Unsafe Condition - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
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
            border: 2px solid #000;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: middle;
            border: 1px solid #000;
        }
        .logo-box-left {
            width: 140px;
            text-align: center;
            padding: 4px;
        }
        .logo-box-left img {
            max-height: 44px;
            max-width: 130px;
        }
        .logo-box-right {
            width: 140px;
            text-align: center;
            padding: 4px;
        }
        .logo-box-right img {
            max-height: 44px;
            max-width: 130px;
        }
        .title-box {
            text-align: center;
            padding: 6px;
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
            font-size: 11px;
            font-weight: bold;
            line-height: 1.3;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            font-size: 8px;
            margin-bottom: 12px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 5px 4px;
            vertical-align: middle;
        }
        .data-table thead th {
            background-color: #ea7315;
            color: #000;
            font-weight: bold;
            text-align: center;
            font-size: 8.5px;
        }
        .data-table tbody td {
            text-align: left;
        }
        .text-center {
            text-align: center !important;
        }
        .eviden-img {
            max-width: 75px;
            max-height: 60px;
            display: block;
            margin: 0 auto;
            border: 1px solid #ccc;
        }
        .summary-table {
            width: 280px;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 8px;
        }
        .summary-table th {
            background-color: #ea7315;
            color: #000;
            font-weight: bold;
            padding: 4px;
            border: 1px solid #000;
            text-align: center;
        }
        .summary-table td {
            padding: 4px 6px;
            border: 1px solid #000;
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
                <h1>JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE -KIT</h1>
                <h2>LAPORAN PROJECT {{ strtoupper($unit->name) }}</h2>
                <h3>LAPORAN UNSAFE ACTION DAN UNSAFE CONDITION</h3>
                <div style="font-size: 8.5px; font-weight: normal; margin-top: 3px;">
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

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 25px;">NO</th>
                <th rowspan="2" style="width: 75px;">PERIODE</th>
                <th rowspan="2" style="width: 90px;">KATEGORI</th>
                <th rowspan="2" style="width: 140px;">TEMUAN</th>
                <th rowspan="2" style="width: 75px;">KONDISI</th>
                <th rowspan="2" style="width: 120px;">TINDAK LANJUT</th>
                <th rowspan="2" style="width: 120px;">REKOMENDASI</th>
                <th rowspan="2" style="width: 80px;">LOKASI</th>
                <th rowspan="2" style="width: 55px;">KETERANGAN</th>
                <th colspan="2">EVIDEN</th>
            </tr>
            <tr>
                <th style="width: 85px;">SEBELUM</th>
                <th style="width: 85px;">SESUDAH</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ $row['periode'] }}</td>
                    <td class="text-center font-bold">{{ $row['kategori'] }}</td>
                    <td>{{ $row['temuan'] }}</td>
                    <td class="text-center">{{ $row['kondisi'] }}</td>
                    <td>{{ $row['tindak_lanjut'] }}</td>
                    <td>{{ $row['rekomendasi'] }}</td>
                    <td class="text-center">{{ $row['lokasi'] }}</td>
                    <td class="text-center font-bold">{{ $row['keterangan'] }}</td>
                    <td class="text-center">
                        @if($row['foto_sebelum'])
                            <img src="{{ $row['foto_sebelum'] }}" class="eviden-img" alt="Sebelum">
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center">
                        @if($row['foto_sesudah'])
                            <img src="{{ $row['foto_sesudah'] }}" class="eviden-img" alt="Sesudah">
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center" style="padding: 20px;">
                        Tidak ada data temuan unsafe action / condition pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary-table">
        <thead>
            <tr>
                <th style="width: 25px;">NO</th>
                <th colspan="2">TEMUAN KONDISI</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td>UNSAFE ACTION</td>
                <td class="text-center font-bold" style="width: 50px;">{{ $unsafeActionCount }}</td>
            </tr>
            <tr>
                <td class="text-center">2</td>
                <td>UNSAFE CONDITION</td>
                <td class="text-center font-bold" style="width: 50px;">{{ $unsafeConditionCount }}</td>
            </tr>
            <tr style="background-color: #f5f5f5;">
                <td colspan="2" style="font-weight: bold; text-align: right;">TOTAL TEMUAN:</td>
                <td class="text-center font-bold">{{ $unsafeActionCount + $unsafeConditionCount }}</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
