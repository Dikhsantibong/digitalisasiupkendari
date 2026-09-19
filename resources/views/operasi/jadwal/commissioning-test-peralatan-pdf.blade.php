<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pelaksanaan Commissioning Test Peralatan Pembangkit - {{ $unit->name }}</title>
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
        .equipment-name {
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
            width: 320px;
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

    <!-- Header Box matching media_1789482601520.png -->
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
                <div class="title-row">JADWAL PELAKSANAAN COMMISSIONING TEST PERALATAN PEMBANGKIT PEMBANGKIT</div>
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
                <th rowspan="2" class="th-orange" style="width: 140px;">PERALATAN PEMBANGKIT</th>
                <th rowspan="2" class="th-orange" style="width: 40px;">UJI BEBAN</th>
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
            @foreach($rows as $idx => $r)
                @php
                    $b50 = $r['beban_50'] ?? [];
                    $b75 = $r['beban_75'] ?? [];
                    $b100 = $r['beban_100'] ?? [];
                    $cnt50 = count($b50);
                    $cnt75 = count($b75);
                    $cnt100 = count($b100);
                @endphp
                <!-- Sub-row 1: 50% -->
                <tr>
                    <td rowspan="3">{{ $r['no_urut'] ?: ($idx + 1) }}</td>
                    <td rowspan="3" class="equipment-name">{{ $r['nama_peralatan'] }}</td>
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
                <td colspan="3" class="th-orange" style="text-align: center;">TOTAL</td>
                @for($m = 1; $m <= 12; $m++)
                    @for($w = 1; $w <= 4; $w++)
                        @php $val = $columnTotals["{$m}-{$w}"] ?? 0; @endphp
                        <td class="th-orange">{{ $val > 0 ? $val : '' }}</td>
                    @endfor
                @endfor
                <td class="th-orange">{{ $grandTotal > 0 ? $grandTotal : '' }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Table 2: Recap Table Bawah Kiri (matching reference) -->
    <div class="recap-container">
        <table class="recap-table">
            <thead>
                <tr>
                    <th class="th-orange" style="width: 25px;">NO</th>
                    <th class="th-orange" style="width: 140px;">NAMA MESIN PERALATAN</th>
                    <th class="th-orange" style="width: 55px;">UJI BEBAN</th>
                    <th class="th-orange" style="width: 100px;">DATA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($machines as $mIdx => $m)
                    <tr>
                        <td rowspan="3">{{ $mIdx + 1 }}</td>
                        <td rowspan="3" style="text-align: left; padding-left: 4px; font-weight: 500;">{{ $m['name'] }}</td>
                        <td>50%</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>75%</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>100%</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>
</html>
