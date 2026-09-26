<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Checklist Commissioning Test Mesin - {{ $unit->name }} - {{ $namaMesin }}</title>
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
            border: 1.5px solid #000;
            margin-bottom: 5px;
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
            padding: 2.5px 6px;
            border-bottom: 1px solid #000;
            font-size: 9px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .title-row:last-child {
            border-bottom: none;
        }
        .sub-bar {
            width: 100%;
            margin-bottom: 5px;
            font-size: 8.5px;
            font-weight: bold;
        }
        .sub-bar td {
            padding: 2px 0;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 7.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 3.5px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .th-orange {
            background-color: #ed7d31 !important;
            color: #000000 !important;
            font-weight: bold;
            font-size: 7.5px;
            text-transform: uppercase;
        }
        .section-header {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: left !important;
            padding-left: 8px !important;
            font-size: 8px;
            text-transform: uppercase;
        }
        .activity-name {
            text-align: left !important;
            padding-left: 6px !important;
            font-weight: 500;
        }
        .check-mark {
            font-weight: bold;
            font-size: 9.5px;
            color: #000;
        }
        .catatan-box {
            border: 1px solid #000;
            border-top: none;
            padding: 10px 12px;
            min-height: 55px;
            font-size: 8px;
            color: #334155;
            background: #fafafa;
        }
    </style>
</head>
<body>

    <!-- Header Box matching media_1790360567629.jpg -->
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
                <div class="title-row">JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE -KIT</div>
                <div class="title-row">LAPORAN PROJECT {{ strtoupper($unit->name) }}</div>
                <div class="title-row">CHECKLIST COMMISSIONING TEST MESIN PEMBANGKIT</div>
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

    <!-- Machine & Date info -->
    <table class="sub-bar">
        <tr>
            <td style="text-align: left;">
                <strong>MESIN:</strong> {{ strtoupper($namaMesin) }}
            </td>
            <td style="text-align: right;">
                <strong>TANGGAL:</strong> {{ $formattedDate }}
            </td>
        </tr>
    </table>

    <!-- Main Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" class="th-orange" style="width: 25px;">NO</th>
                <th rowspan="2" class="th-orange" style="width: 260px; text-align: left; padding-left: 6px;">KEGIATAN</th>
                <th colspan="5" class="th-orange">STATUS KESIAPAN PERALATAN</th>
                <th colspan="2" class="th-orange">PARAF</th>
            </tr>
            <tr>
                <th class="th-orange" style="width: 45px;">ON</th>
                <th class="th-orange" style="width: 45px;">OF</th>
                <th class="th-orange" style="width: 75px;">SIAP OPERASI</th>
                <th class="th-orange" style="width: 65px;">ABNORMAL</th>
                <th class="th-orange" style="width: 85px;">TIDAK SIAP OPERASI</th>
                <th class="th-orange" style="width: 70px;">PIC</th>
                <th class="th-orange" style="width: 50px;">PARAF</th>
            </tr>
        </thead>
        <tbody>
            @php $currentSection = null; @endphp
            @foreach($rows as $r)
                @if(($r['section'] ?? '') !== $currentSection)
                    @php $currentSection = $r['section']; @endphp
                    <tr>
                        <td colspan="9" class="section-header">{{ $currentSection }}</td>
                    </tr>
                @endif

                @php $status = $r['status'] ?? ''; @endphp
                <tr>
                    <td>{{ $r['no'] ?? '' }}</td>
                    <td class="activity-name">{{ $r['kegiatan'] ?? '' }}</td>
                    <td class="check-mark">{{ $status === 'ON' ? '✓' : '' }}</td>
                    <td class="check-mark">{{ $status === 'OF' ? '✓' : '' }}</td>
                    <td class="check-mark">{{ $status === 'SIAP OPERASI' ? '✓' : '' }}</td>
                    <td class="check-mark">{{ $status === 'ABNORMAL' ? '✓' : '' }}</td>
                    <td class="check-mark">{{ $status === 'TIDAK SIAP OPERASI' ? '✓' : '' }}</td>
                    <td>{{ $r['pic'] ?? '' }}</td>
                    <td>{{ $r['paraf'] ?? '' }}</td>
                </tr>
            @endforeach

            <!-- CATATAN Header Row -->
            <tr>
                <td colspan="9" class="section-header" style="border-bottom: none;">CATATAN</td>
            </tr>
        </tbody>
    </table>

    <div class="catatan-box">
        {{ $catatan ?: 'disesuaikan dengan kondisi peralatan unit/sentral kit' }}
    </div>

</body>
</html>
