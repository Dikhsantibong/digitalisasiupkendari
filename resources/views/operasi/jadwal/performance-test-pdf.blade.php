<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pelaksanaan Performance Test Mesin Pembangkit - {{ $unit->name }}</title>
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
        .section-row {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: left !important;
            padding: 2px 5px !important;
        }
        .machine-name {
            text-align: left !important;
            padding-left: 4px !important;
            font-weight: 500;
        }
        .marked {
            font-weight: bold;
            color: #000;
            background-color: #fef08a;
        }
        .grand-total-cell {
            background-color: #93c5fd !important;
            color: #000 !important;
            font-weight: bold;
        }
        .recap-container {
            margin-top: 8px;
            width: 280px;
        }
        .recap-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 6.5px;
        }
        .recap-table th, .recap-table td {
            border: 1px solid #000;
            padding: 2px 2px;
            text-align: center;
            vertical-align: middle;
        }
    </style>
</head>
<body>

    <!-- Header Box matching media_1790360331806.jpg -->
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
                <div class="title-row">JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE - KIT</div>
                <div class="title-row">LAPORAN PROJECT {{ strtoupper($unit->name) }}</div>
                <div class="title-row">JADWAL PELAKSANAAN PERFORMANCE TEST MESIN PEMBANGKIT</div>
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
                <th rowspan="3" class="th-orange" style="width: 20px;">NO</th>
                <th rowspan="3" class="th-orange" style="width: 140px;">MESIN PEMBANGKIT</th>
                <th rowspan="3" class="th-orange" style="width: 36px;">UJI BBN</th>
                <th colspan="48" class="th-orange">BULAN</th>
                <th rowspan="3" class="th-orange" style="width: 30px;">JUMLAH</th>
            </tr>
            <tr>
                @foreach($months as $mNum => $mName)
                    <th colspan="4" class="th-orange">{{ $mName }}</th>
                @endforeach
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
            @php $currentSection = null; @endphp
            @foreach($rows as $idx => $r)
                @php
                    $section = $r['section'] ?? 'A. PEMBUATAN DATA TEKNIKS';
                    $b50 = $r['beban_50'] ?? [];
                    $b75 = $r['beban_75'] ?? [];
                    $b100 = $r['beban_100'] ?? [];
                    $cnt50 = count($b50);
                    $cnt75 = count($b75);
                    $cnt100 = count($b100);
                @endphp

                @if($section !== $currentSection)
                    @php $currentSection = $section; @endphp
                    <tr>
                        <td colspan="52" class="section-row">{{ $section }}</td>
                    </tr>
                @endif

                <!-- Sub-row 1: 50% -->
                <tr>
                    <td rowspan="3">{{ $r['no_urut'] ?: ($idx + 1) }}</td>
                    <td rowspan="3" class="machine-name">{{ $r['nama_mesin'] }}</td>
                    <td>50%</td>
                    @for($m = 1; $m <= 12; $m++)
                        @for($w = 1; $w <= 4; $w++)
                            @php $k = "{$m}-{$w}"; $isMarked = in_array($k, $b50); @endphp
                            <td class="{{ $isMarked ? 'marked' : '' }}">{{ $isMarked ? '1' : '' }}</td>
                        @endfor
                    @endfor
                    <td style="font-weight: bold;">{{ $cnt50 > 0 ? $cnt50 : '' }}</td>
                </tr>
                <!-- Sub-row 2: 75% -->
                <tr>
                    <td>75%</td>
                    @for($m = 1; $m <= 12; $m++)
                        @for($w = 1; $w <= 4; $w++)
                            @php $k = "{$m}-{$w}"; $isMarked = in_array($k, $b75); @endphp
                            <td class="{{ $isMarked ? 'marked' : '' }}">{{ $isMarked ? '1' : '' }}</td>
                        @endfor
                    @endfor
                    <td style="font-weight: bold;">{{ $cnt75 > 0 ? $cnt75 : '' }}</td>
                </tr>
                <!-- Sub-row 3: 100% -->
                <tr>
                    <td>100%</td>
                    @for($m = 1; $m <= 12; $m++)
                        @for($w = 1; $w <= 4; $w++)
                            @php $k = "{$m}-{$w}"; $isMarked = in_array($k, $b100); @endphp
                            <td class="{{ $isMarked ? 'marked' : '' }}">{{ $isMarked ? '1' : '' }}</td>
                        @endfor
                    @endfor
                    <td style="font-weight: bold;">{{ $cnt100 > 0 ? $cnt100 : '' }}</td>
                </tr>
            @endforeach

            <!-- Total Row -->
            <tr style="font-weight: bold; background-color: #f3f4f6;">
                <td colspan="3" style="text-align: center; font-weight: bold;">TOTAL</td>
                @for($m = 1; $m <= 12; $m++)
                    @for($w = 1; $w <= 4; $w++)
                        @php $k = "{$m}-{$w}"; $val = $columnTotals[$k] ?? 0; @endphp
                        <td>{{ $val > 0 ? $val : '' }}</td>
                    @endfor
                @endfor
                <td class="grand-total-cell">{{ $grandTotal > 0 ? $grandTotal : '' }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Table 2: Recap Table Bawah Kiri (matching reference) -->
    <div class="recap-container">
        <table class="recap-table">
            <thead>
                <tr>
                    <th class="th-orange" style="width: 25px;">NO</th>
                    <th class="th-orange" style="width: 140px;">MESIN PEMBANGKIT</th>
                    <th class="th-orange" style="width: 55px;">UJI BBN</th>
                    <th class="th-orange" style="width: 60px;">DATA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $idx => $r)
                    @php
                        $cnt50 = count($r['beban_50'] ?? []);
                        $cnt75 = count($r['beban_75'] ?? []);
                        $cnt100 = count($r['beban_100'] ?? []);
                    @endphp
                    <tr>
                        <td rowspan="3">{{ $r['no_urut'] ?: ($idx + 1) }}</td>
                        <td rowspan="3" style="text-align: left; padding-left: 4px; font-weight: 500;">{{ $r['nama_mesin'] }}</td>
                        <td>50%</td>
                        <td>{{ $cnt50 > 0 ? $cnt50 : '' }}</td>
                    </tr>
                    <tr>
                        <td>75%</td>
                        <td>{{ $cnt75 > 0 ? $cnt75 : '' }}</td>
                    </tr>
                    <tr>
                        <td>100%</td>
                        <td>{{ $cnt100 > 0 ? $cnt100 : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>
</html>
