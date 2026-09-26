<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Patrol Check Mesin - {{ $unit->name }} - {{ $namaMesin }} - {{ $periodLabel }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm 5mm 5mm 5mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 6.5px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-bottom: 4px;
        }
        .header-table td {
            vertical-align: middle;
            border: 1px solid #000;
        }
        .logo-box {
            width: 130px;
            text-align: center;
            padding: 2px;
        }
        .logo-box img {
            max-height: 38px;
            max-width: 120px;
        }
        .title-box {
            text-align: center;
            padding: 0;
        }
        .title-row {
            padding: 2px 4px;
            border-bottom: 1px solid #000;
            font-size: 8px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .title-row:last-child {
            border-bottom: none;
        }
        .machine-badge-table {
            width: 100%;
            margin-bottom: 3px;
        }
        .machine-box {
            font-weight: bold;
            font-size: 7.5px;
            text-transform: uppercase;
            border: 1px solid #000;
            padding: 2px 6px;
            display: inline-block;
            background: #f8fafc;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 5.8px;
        }
        .data-table th, .data-table td {
            border: 0.5px solid #000;
            padding: 1.5px 0.5px;
            text-align: center;
            vertical-align: middle;
        }
        .th-title {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 6px;
        }
        .day-red {
            background-color: #fca5a5 !important;
            color: #000 !important;
            font-weight: bold;
        }
        .day-green {
            background-color: #bbf7d0 !important;
            color: #000 !important;
            font-weight: bold;
        }
        .th-sub {
            font-size: 5.5px;
            font-weight: bold;
        }
        .sub-n {
            background-color: #fef08a;
        }
        .sub-t {
            background-color: #fee2e2;
        }
        .category-header {
            background-color: #fef08a !important;
            font-weight: bold;
            text-align: left !important;
            padding: 1.5px 4px !important;
            font-size: 6px;
            text-transform: uppercase;
        }
        .shift-category {
            background-color: #e2e8f0 !important;
            font-weight: bold;
            text-align: left !important;
            padding: 1.5px 4px !important;
            font-size: 6px;
            text-transform: uppercase;
        }
        .equipment-name {
            text-align: left !important;
            padding-left: 3px !important;
            font-weight: 500;
            white-space: nowrap;
        }
        .shift-label {
            text-align: left !important;
            padding-left: 3px !important;
            font-weight: bold;
        }
        .marked {
            font-weight: bold;
            color: #000;
            font-size: 6.5px;
        }
        .marked-trouble {
            font-weight: bold;
            color: #dc2626;
            font-size: 6.5px;
        }
        .footer-note {
            margin-top: 4px;
            font-size: 6.5px;
        }
        .footer-note strong {
            font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- Header Box matching media_1790360501717.jpg -->
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
                <div class="title-row">JASA PENDUKUNG TEKNIS 6 KIT</div>
                <div class="title-row">LAPORAN PROJECT {{ strtoupper($unit->name) }}</div>
                <div class="title-row">PATROL CHECK MESIN</div>
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

    <table class="machine-badge-table">
        <tr>
            <td>
                <span class="machine-box">MESIN: {{ $namaMesin }}</span>
            </td>
        </tr>
    </table>

    <!-- Main Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="3" class="th-title" style="width: 14px;">NO</th>
                <th rowspan="3" class="th-title" style="width: 110px; text-align: left; padding-left: 4px;">PERALATAN</th>
                <th colspan="{{ $daysInMonth * 2 }}" class="th-title">{{ $periodLabel }}</th>
            </tr>
            <tr>
                @foreach($daysInfo as $d)
                    <th colspan="2" class="{{ $d['is_weekend'] ? 'day-red' : 'day-green' }}">
                        {{ $d['day'] }}
                    </th>
                @endforeach
            </tr>
            <tr>
                @foreach($daysInfo as $d)
                    <th class="th-sub sub-n" style="width: 8px;">N</th>
                    <th class="th-sub sub-t" style="width: 8px;">T</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php $currentSystem = null; @endphp
            @foreach($items as $item)
                @if(($item['system'] ?? '') !== $currentSystem)
                    @php $currentSystem = $item['system']; @endphp
                    <tr>
                        <td colspan="{{ 2 + ($daysInMonth * 2) }}" class="category-header">{{ $currentSystem }}</td>
                    </tr>
                @endif

                @php $checks = $item['checks'] ?? []; @endphp
                <tr>
                    <td>{{ $item['no'] }}</td>
                    <td class="equipment-name">{{ $item['peralatan'] }}</td>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $val = $checks[(string)$d] ?? ($checks[$d] ?? '');
                        @endphp
                        <td class="{{ $val === 'N' ? 'marked' : '' }}">
                            {{ $val === 'N' ? '✓' : '' }}
                        </td>
                        <td class="{{ $val === 'T' ? 'marked-trouble' : '' }}">
                            {{ $val === 'T' ? '✓' : '' }}
                        </td>
                    @endfor
                </tr>
            @endforeach

            <!-- PELAKSANA SHIFT -->
            <tr>
                <td colspan="{{ 2 + ($daysInMonth * 2) }}" class="shift-category">PELAKSANA SHIFT</td>
            </tr>
            <tr>
                <td colspan="2" class="shift-label">Shift Pagi : 08.00 - 16.00</td>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php $sp = $shiftPagi[(string)$d] ?? ($shiftPagi[$d] ?? ''); @endphp
                    <td colspan="2" style="font-weight: bold;">{{ $sp }}</td>
                @endfor
            </tr>
            <tr>
                <td colspan="2" class="shift-label">Shift Sore : 16.00 - 24.00</td>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php $ss = $shiftSore[(string)$d] ?? ($shiftSore[$d] ?? ''); @endphp
                    <td colspan="2" style="font-weight: bold;">{{ $ss }}</td>
                @endfor
            </tr>
            <tr>
                <td colspan="2" class="shift-label">Shift Malam : 00.00 - 08.00</td>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php $sm = $shiftMalam[(string)$d] ?? ($shiftMalam[$d] ?? ''); @endphp
                    <td colspan="2" style="font-weight: bold;">{{ $sm }}</td>
                @endfor
            </tr>
        </tbody>
    </table>

    <div class="footer-note">
        <strong>NOTE :</strong> Patrol Check Dilaksanakan Pada Saat Shift Pagi<br>
        <strong>CATATAN :</strong> {{ $catatan ?: '-' }}
    </div>

</body>
</html>
