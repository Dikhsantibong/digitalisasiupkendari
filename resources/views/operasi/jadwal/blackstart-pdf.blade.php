<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pemeriksaan Instalasi Blackstart - {{ $unit->name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 6mm 6mm 6mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 7px;
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
            max-height: 40px;
            max-width: 130px;
        }
        .title-box {
            text-align: center;
            padding: 0;
        }
        .title-row {
            padding: 2.5px 5px;
            border-bottom: 1px solid #000;
            font-size: 8.5px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .title-row:last-child {
            border-bottom: none;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 6.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 2px 1px;
            text-align: center;
            vertical-align: middle;
        }
        .th-orange {
            background-color: #ed7d31 !important;
            color: #000000 !important;
            font-weight: bold;
            font-size: 6.5px;
        }
        .section-row td {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: left;
            padding-left: 4px;
        }
        .item-name {
            text-align: left !important;
            padding-left: 4px !important;
            font-weight: 500;
        }
        .marked {
            font-weight: bold;
            color: #000;
            background-color: #fef08a;
        }
        .recap-container {
            margin-top: 8px;
            width: 360px;
        }
        .recap-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 6.5px;
        }
        .recap-table th, .recap-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            text-align: center;
            vertical-align: middle;
        }
    </style>
</head>
<body>

    <!-- Header Box matching media_1789542560341.png -->
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
                <div class="title-row">JADWAL PEMERIKSAAN INSTALASI BLACKSTART</div>
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

    <!-- Main Table (Matrix 12 Bulan x 4 Minggu = 48 Kolom) -->
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" class="th-orange" style="width: 20px;">NO</th>
                <th rowspan="2" class="th-orange" style="width: 180px;">URAIAN PEMERIKSAAN</th>
                <th rowspan="2" class="th-orange" style="width: 45px;">REN &amp; REAL</th>
                <th rowspan="2" class="th-orange" style="width: 75px;">PIC PEMBUAT</th>
                @foreach($months as $mNum => $mName)
                    <th colspan="4" class="th-orange">{{ $mName }}</th>
                @endforeach
                <th rowspan="2" class="th-orange" style="width: 30px;">JUMLAH</th>
            </tr>
            <tr>
                @for($m = 1; $m <= 12; $m++)
                    @for($w = 1; $w <= 4; $w++)
                        <th class="th-orange" style="width: 11px;">{{ $w }}</th>
                    @endfor
                @endfor
            </tr>
        </thead>
        <tbody>
            <!-- Section Header Row matching image -->
            <tr class="section-row">
                <td style="text-align: center;">A.</td>
                <td colspan="{{ 4 + 48 }}">PEMBUATAN DATA TEKNIKS</td>
            </tr>

            @foreach($rows as $idx => $r)
                @php
                    $ren = $r['rencana'] ?? [];
                    $real = $r['realisasi'] ?? [];
                    $cntRen = count($ren);
                    $cntReal = count($real);
                @endphp
                <!-- Sub-row 1: REN -->
                <tr>
                    <td rowspan="2">{{ $r['no_urut'] ?: ($idx + 1) }}</td>
                    <td rowspan="2" class="item-name">{{ $r['uraian'] }}</td>
                    <td>REN</td>
                    <td rowspan="2">{{ $r['pic'] }}</td>
                    @for($m = 1; $m <= 12; $m++)
                        @for($w = 1; $w <= 4; $w++)
                            @php $k = "{$m}-{$w}"; $isMarked = in_array($k, $ren); @endphp
                            <td class="{{ $isMarked ? 'marked' : '' }}">{{ $isMarked ? '1' : '' }}</td>
                        @endfor
                    @endfor
                    <td style="font-weight: bold;">{{ $cntRen > 0 ? $cntRen : '0' }}</td>
                </tr>
                <!-- Sub-row 2: REAL -->
                <tr>
                    <td>REAL</td>
                    @for($m = 1; $m <= 12; $m++)
                        @for($w = 1; $w <= 4; $w++)
                            @php $k = "{$m}-{$w}"; $isMarked = in_array($k, $real); @endphp
                            <td class="{{ $isMarked ? 'marked' : '' }}">{{ $isMarked ? '1' : '' }}</td>
                        @endfor
                    @endfor
                    <td style="font-weight: bold;">{{ $cntReal > 0 ? $cntReal : '0' }}</td>
                </tr>
            @endforeach

            <!-- Total Row -->
            <tr style="font-weight: bold; background-color: #f3f4f6;">
                <td colspan="4" class="th-orange" style="text-align: center;">TOTAL</td>
                @for($m = 1; $m <= 12; $m++)
                    @for($w = 1; $w <= 4; $w++)
                        @php $val = $columnTotals["{$m}-{$w}"] ?? 0; @endphp
                        <td class="th-orange">{{ $val > 0 ? $val : '' }}</td>
                    @endfor
                @endfor
                <td class="th-orange">{{ $grandTotal > 0 ? $grandTotal : '0' }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Table 2: Recap Kinerja Bawah Kiri (matching reference) -->
    <div class="recap-container">
        <table class="recap-table">
            <thead>
                <tr>
                    <th class="th-orange" style="width: 25px;">NO</th>
                    <th class="th-orange" style="width: 170px;">URAIAN</th>
                    <th class="th-orange" style="width: 55px;">RENCANA</th>
                    <th class="th-orange" style="width: 55px;">REALISASI</th>
                    <th class="th-orange" style="width: 55px;">A. KINERJA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recap as $rc)
                    <tr>
                        <td>{{ $rc['no_urut'] }}</td>
                        <td style="text-align: left; padding-left: 4px; font-weight: 500;">{{ $rc['uraian'] }}</td>
                        <td style="font-weight: bold;">{{ $rc['rencana'] }}</td>
                        <td style="font-weight: bold;">{{ $rc['realisasi'] }}</td>
                        <td style="font-weight: bold;">{{ $rc['kinerja'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>
</html>
