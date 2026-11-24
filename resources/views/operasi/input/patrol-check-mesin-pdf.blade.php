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
        /* Checklist format (PLTD Poasia POA-OPR-CHK-01) */
        .chk-sheet {
            width: 100%;
        }
        .chk-entry-break {
            page-break-after: always;
        }
        .chk-meta-strip {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
            font-size: 7px;
        }
        .chk-meta-strip td {
            border: 1px solid #000;
            padding: 2.5px 5px;
            background: #f8fafc;
        }
        .chk-form-code {
            text-align: right;
            font-weight: bold;
            font-size: 6.5px;
            background: #eef6f0 !important;
            color: #14532d;
            white-space: nowrap;
        }
        .chk-instruction {
            font-size: 6.5px;
            font-style: italic;
            margin: 0 0 3px 0;
            color: #1e293b;
        }
        .chk-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 6.8px;
        }
        .chk-table th {
            background-color: #1f5c3a;
            color: #ffffff;
            border: 0.75px solid #000;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
            font-size: 6.8px;
        }
        .chk-table td {
            border: 0.5px solid #475569;
            padding: 2px 3px;
            vertical-align: middle;
            text-align: center;
        }
        .chk-area-mesin {
            background-color: #eef6f0;
            font-weight: bold;
            font-size: 6.3px;
            color: #14532d;
        }
        .chk-area-alat {
            background-color: #eff6ff;
            font-weight: bold;
            font-size: 6.3px;
            color: #1e3a8a;
        }
        .chk-area-other {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 6.3px;
        }
        .chk-comp {
            text-align: left !important;
            font-weight: 500;
            padding-left: 5px !important;
            white-space: nowrap;
        }
        .chk-note-cell {
            text-align: left !important;
            padding-left: 4px !important;
        }
        .chk-box {
            display: inline-block;
            width: 9px;
            height: 9px;
            border: 1px solid #334155;
            background: #ffffff;
            line-height: 8px;
            font-size: 7px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }
        .chk-box-on {
            background: #dcfce7;
            border-color: #15803d;
            color: #14532d;
        }
        .chk-box-tn {
            background: #fee2e2;
            border-color: #b91c1c;
            color: #991b1b;
        }
        .chk-row-tn td {
            background-color: #fff5f5;
        }
        .chk-dash {
            color: #64748b;
            font-weight: bold;
        }
        .chk-footer-box {
            margin-top: 4px;
            border: 1px solid #000;
        }
        .chk-footer-title {
            background: #f1f5f9;
            border-bottom: 0.75px solid #000;
            padding: 2px 5px;
            font-weight: bold;
            font-size: 7px;
        }
        .chk-footer-body {
            padding: 4px 5px;
            min-height: 22px;
            font-size: 7px;
        }
        .chk-eviden-img {
            max-width: 64px;
            max-height: 42px;
            border: 0.5px solid #64748b;
            display: block;
            margin: 1px auto;
        }
        .monthly-findings-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-top: 5px;
            font-size: 6.5px;
        }
        .monthly-findings-table th {
            background-color: #fee2e2;
            color: #991b1b;
            border: 0.5px solid #000;
            padding: 2px 4px;
            font-weight: bold;
            text-align: left;
        }
        .monthly-findings-table td {
            border: 0.5px solid #000;
            padding: 2px 4px;
            vertical-align: middle;
        }
    </style>
</head>
<body>

@if(($patrolFormat ?? 'monthly') === 'checklist')
    @foreach($entries as $entry)
        <div class="chk-sheet {{ !$loop->last ? 'chk-entry-break' : '' }}">
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
                        <div class="title-row">{{ strtoupper($patrolMeta['title'] ?? 'FORM PATROL CHECK MESIN & ALAT BANTU') }}</div>
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

            <table class="chk-meta-strip">
                <tr>
                    <td style="width: 15%;"><strong>Tanggal:</strong> {{ $entry['tanggal'] }}</td>
                    <td style="width: 12%;"><strong>Shift:</strong> {{ $entry['shift'] }}</td>
                    <td style="width: 11%;"><strong>Jam:</strong> {{ $entry['jam'] }}</td>
                    <td style="width: 22%;"><strong>Unit/Mesin:</strong> {{ $namaMesin }}</td>
                    <td style="width: 22%;"><strong>Operator:</strong> {{ $entry['operator_name'] }}</td>
                    <td class="chk-form-code" style="width: 18%;">
                        {{ $patrolMeta['form_number'] ?? 'POA-OPR-CHK-01' }} · {{ $patrolMeta['revision'] ?? 'Rev. 00' }} | Hal. {{ $loop->iteration }}/{{ count($entries) }}
                    </td>
                </tr>
            </table>

            @if(!empty($patrolMeta['instruction']))
                <p class="chk-instruction">
                    <strong>Petunjuk:</strong> {{ $patrolMeta['instruction'] }}
                </p>
            @endif

            <table class="chk-table">
                <thead>
                    <tr>
                        <th style="width: 20px;">No</th>
                        <th style="width: 66px;">Area</th>
                        <th style="width: 155px; text-align: left; padding-left: 5px;">Komponen / Peralatan</th>
                        @foreach($checklistParams as $param)
                            <th style="width: 40px;">{{ $param['label'] }}</th>
                        @endforeach
                        <th style="width: 24px;">N</th>
                        <th style="width: 24px;">TN</th>
                        <th style="text-align: left; padding-left: 5px;">Uraian Alasan / Tindakan Awal</th>
                        <th style="width: 74px;">Eviden Foto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entry['items'] as $item)
                        @php
                            $areaUpper = strtoupper((string) ($item['system'] ?? ''));
                            $areaClass = match ($areaUpper) {
                                'MESIN' => 'chk-area-mesin',
                                'ALAT BANTU' => 'chk-area-alat',
                                default => 'chk-area-other',
                            };
                            $applicable = $item['applicable'] ?? [];
                            $checked = $item['checked'] ?? [];
                            $status = (string) ($item['status'] ?? '');
                        @endphp
                        <tr class="{{ $status === 'TN' ? 'chk-row-tn' : '' }}">
                            <td>{{ $item['no'] }}</td>
                            <td class="{{ $areaClass }}">{{ $item['system'] }}</td>
                            <td class="chk-comp">{{ $item['peralatan'] }}</td>
                            @foreach($checklistParams as $param)
                                @php
                                    $pKey = $param['key'];
                                    $isApp = (bool) ($applicable[$pKey] ?? false);
                                    $isChk = $isApp && (bool) ($checked[$pKey] ?? false);
                                @endphp
                                <td>
                                    @if($isApp)
                                        <span class="chk-box {{ $isChk ? 'chk-box-on' : '' }}">{!! $isChk ? '&#10003;' : '' !!}</span>
                                    @else
                                        <span class="chk-dash">&mdash;</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <span class="chk-box {{ $status === 'N' ? 'chk-box-on' : '' }}">{!! $status === 'N' ? '&#10003;' : '' !!}</span>
                            </td>
                            <td>
                                <span class="chk-box {{ $status === 'TN' ? 'chk-box-tn' : '' }}">{!! $status === 'TN' ? '&#10003;' : '' !!}</span>
                            </td>
                            <td class="chk-note-cell">
                                @if($status === 'TN' && ($item['catatan'] ?? '') !== '')
                                    {{ $item['catatan'] }}
                                @else
                                    <span class="chk-dash">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                @if($status === 'TN' && !empty($item['eviden_url']))
                                    <img src="{{ $item['eviden_url'] }}" alt="Eviden" class="chk-eviden-img">
                                @else
                                    <span class="chk-dash">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="chk-footer-box">
                <div class="chk-footer-title">Catatan penting / tindak lanjut:</div>
                <div class="chk-footer-body">{{ $entry['catatan'] !== '' ? $entry['catatan'] : '-' }}</div>
            </div>
        </div>
    @endforeach
@else

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

    @php
        $monthlyFindings = [];
        foreach ($items as $item) {
            $checks = $item['checks'] ?? [];
            $findings = $item['findings'] ?? [];
            foreach ($checks as $dKey => $st) {
                if ($st === 'T' && isset($findings[(string) $dKey])) {
                    $f = $findings[(string) $dKey];
                    if (($f['catatan'] ?? '') !== '' || !empty($f['eviden_url'])) {
                        $monthlyFindings[] = [
                            'day' => (int) $dKey,
                            'system' => $item['system'] ?? '',
                            'peralatan' => $item['peralatan'] ?? '',
                            'catatan' => $f['catatan'] ?? '',
                            'eviden_url' => $f['eviden_url'] ?? null,
                        ];
                    }
                }
            }
        }
    @endphp

    @if($monthlyFindings !== [])
        <table class="monthly-findings-table">
            <thead>
                <tr>
                    <th style="width: 55px;">TANGGAL</th>
                    <th style="width: 130px;">SISTEM</th>
                    <th style="width: 150px;">PERALATAN</th>
                    <th>URAIAN ALASAN / TINDAKAN AWAL (TIDAK NORMAL)</th>
                    <th style="width: 80px; text-align: center;">EVIDEN FOTO</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthlyFindings as $mf)
                    <tr>
                        <td>Tanggal {{ $mf['day'] }}</td>
                        <td>{{ $mf['system'] }}</td>
                        <td>{{ $mf['peralatan'] }}</td>
                        <td>{{ $mf['catatan'] !== '' ? $mf['catatan'] : '-' }}</td>
                        <td style="text-align: center;">
                            @if(!empty($mf['eviden_url']))
                                <img src="{{ $mf['eviden_url'] }}" alt="Eviden" class="chk-eviden-img">
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

</body>
</html>
