<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pelaksanaan Commissioning Test Mesin Pembangkit - {{ $unit->name }}</title>
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
            max-height: 44px;
            max-width: 130px;
        }
        .title-box {
            text-align: center;
            padding: 0;
        }
        .title-row {
            padding: 3px 6px;
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
            font-size: 8px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }
        .th-orange {
            background-color: #ed7d31 !important;
            color: #000000 !important;
            font-weight: bold;
        }
        .section-header td {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: left !important;
            padding: 3.5px 6px !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .activity-cell {
            text-align: left !important;
            padding-left: 6px !important;
            font-weight: normal;
        }
        .pic-cell {
            text-align: center;
            font-size: 7.5px;
        }
        .check-mark {
            font-size: 10px;
            font-weight: bold;
            color: #000;
        }
        .notes-container {
            margin-top: 6px;
            border: 1.5px solid #000;
        }
        .notes-header {
            background-color: #f3f4f6;
            font-weight: bold;
            padding: 3px 6px;
            border-bottom: 1px solid #000;
            text-transform: uppercase;
        }
        .notes-body {
            padding: 8px 12px;
            min-height: 36px;
            font-size: 8.5px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Header Box matching media_1789474426713.png -->
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
                <div class="title-row">JADWAL PELAKSANAAN COMMISSIONING TEST MESIN PEMBANGKIT</div>
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
                <th rowspan="2" class="th-orange" style="width: 28px;">NO</th>
                <th rowspan="2" class="th-orange" style="width: 320px;">KEGIATAN</th>
                <th colspan="5" class="th-orange">STATUS KESIAPAN PERALATAN</th>
                <th colspan="2" class="th-orange">PARAF</th>
            </tr>
            <tr>
                <th class="th-orange" style="width: 36px;">ON</th>
                <th class="th-orange" style="width: 36px;">OF</th>
                <th class="th-orange" style="width: 65px;">SIAP OPERASI</th>
                <th class="th-orange" style="width: 60px;">ABNORMAL</th>
                <th class="th-orange" style="width: 75px;">TIDAK SIAP OPERASI</th>
                <th class="th-orange" style="width: 65px;">PIC</th>
                <th class="th-orange" style="width: 50px;">PARAF</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sections as $sec)
                <!-- Section Header Row -->
                <tr class="section-header">
                    <td colspan="9">{{ $sec['section'] }}</td>
                </tr>

                <!-- Item Rows -->
                @foreach($sec['items'] as $item)
                    @php
                        $st = strtoupper(trim((string) ($item['status'] ?? '')));
                    @endphp
                    <tr>
                        <td>{{ $item['no_urut'] }}</td>
                        <td class="activity-cell">{{ $item['kegiatan'] }}</td>
                        <td>
                            @if($st === 'ON')
                                <span class="check-mark">&#10003;</span>
                            @endif
                        </td>
                        <td>
                            @if($st === 'OF' || $st === 'OFF')
                                <span class="check-mark">&#10003;</span>
                            @endif
                        </td>
                        <td>
                            @if($st === 'SIAP OPERASI')
                                <span class="check-mark">&#10003;</span>
                            @endif
                        </td>
                        <td>
                            @if($st === 'ABNORMAL')
                                <span class="check-mark">&#10003;</span>
                            @endif
                        </td>
                        <td>
                            @if($st === 'TIDAK SIAP OPERASI')
                                <span class="check-mark">&#10003;</span>
                            @endif
                        </td>
                        <td class="pic-cell">{{ $item['pic'] }}</td>
                        <td class="pic-cell">
                            @if(!empty($item['paraf']))
                                {{ $item['paraf'] }}
                            @elseif(!empty($item['pic']))
                                <span class="check-mark">&#10003;</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <!-- Catatan Box matching media_1789474426713.png -->
    <div class="notes-container">
        <div class="notes-header">CATATAN</div>
        <div class="notes-body">
            {{ $catatan ?: 'disesuaikan dengan kondisi peralatan unit/sentral kit' }}
        </div>
    </div>

</body>
</html>
