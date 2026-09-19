<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Logbook Pemantauan Pemanfaatan Air Limbah - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 10mm; }
        * { box-sizing: border-box; }
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
            width: 130px;
            text-align: center;
            padding: 4px;
        }
        .logo-box img {
            max-height: 42px;
            max-width: 120px;
        }
        .title-box {
            text-align: center;
            padding: 4px 8px;
        }
        .title-box h1 {
            margin: 0;
            font-size: 10px;
            font-weight: bold;
            line-height: 1.3;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .title-box h2 {
            margin: 2px 0 0 0;
            font-size: 9px;
            font-weight: bold;
            line-height: 1.3;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
        .title-box h3 {
            margin: 3px 0 0 0;
            font-size: 9.5px;
            font-weight: bold;
            line-height: 1.3;
            text-transform: uppercase;
            text-decoration: underline;
        }
        .title-box .period {
            margin: 2px 0 0 0;
            font-size: 7.5px;
            font-weight: normal;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 8px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table thead th {
            background-color: #85b5e1;
            font-weight: bold;
            color: #000;
            line-height: 1.25;
        }
        .text-left {
            text-align: left !important;
            padding-left: 8px !important;
        }
        .num-cell {
            font-weight: bold;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 8px;
        }
        .signature-table td {
            text-align: center;
            vertical-align: top;
            padding: 2px;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="logo-box">
                @if($logoLeft)
                    <img src="{{ $logoLeft }}" alt="PLN Nusantara Power">
                @else
                    <strong>PLN Nusantara Power</strong>
                @endif
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS UP KENDARI {{ strtoupper($unit->name) }}</h1>
                <h2>LAPORAN PROJECT SENTRAL {{ strtoupper($unit->name) }}</h2>
                <h3>LOGBOOK PEMANTAUAN PEMANFAATAN AIR LIMBAH</h3>
                <div class="period">Bulan: {{ $monthName }} {{ $year }}</div>
            </td>
            <td class="logo-box">
                @if($logoRight)
                    <img src="{{ $logoRight }}" alt="MKP Mitra Karya Prima">
                @else
                    <strong>Mitra Karya Prima</strong>
                @endif
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 25px;">No</th>
                <th rowspan="2" style="width: 180px;">Area Penyiraman</th>
                <th rowspan="2" style="width: 90px;">Tanggal</th>
                <th rowspan="2" style="width: 90px;">Waktu Penyiraman</th>
                <th rowspan="2" style="width: 120px;">Metode Pemanfaatan</th>
                <th colspan="3">Debit Air (Flow Meter)</th>
                <th rowspan="2" style="width: 120px;">Rotasi dan Frekuensi penyiraman (kali/bulan)</th>
                <th rowspan="2" style="width: 55px;">PIC</th>
            </tr>
            <tr>
                <th style="width: 45px;">Awal</th>
                <th style="width: 45px;">Akhir</th>
                <th style="width: 45px;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="num-cell">{{ $row['no_urut'] }}</td>
                    <td class="text-left">{{ $row['area_penyiraman'] }}</td>
                    <td>{{ $row['tanggal_formatted'] }}</td>
                    <td>{{ $row['waktu_penyiraman'] }}</td>
                    <td class="text-left">{{ $row['metode_pemanfaatan'] }}</td>
                    <td>{{ $row['debit_awal'] }}</td>
                    <td>{{ $row['debit_akhir'] }}</td>
                    <td class="num-cell">{{ $row['debit_jumlah'] }}</td>
                    <td>{{ $row['frekuensi'] }}</td>
                    <td>{{ $row['pic'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="padding: 16px; color: #666; font-style: italic;">
                        Belum ada data logbook pemantauan air limbah untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signature-table">
        <tr>
            <td style="width: 50%;">
                <div>Disusun Oleh:</div>
                <div style="font-weight: bold; margin-top: 2px;">Pelaksana K3L</div>
                <div style="height: 45px;"></div>
                <div style="text-decoration: underline; font-weight: bold;">( .................................................. )</div>
                <div>Staff K3L &amp; Keamanan</div>
            </td>
            <td style="width: 50%;">
                <div>Mengetahui:</div>
                <div style="font-weight: bold; margin-top: 2px;">Team Leader / Penanggung Jawab Unit</div>
                <div style="height: 45px;"></div>
                <div style="text-decoration: underline; font-weight: bold;">( .................................................. )</div>
                <div>Team Leader {{ $unit->name }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
