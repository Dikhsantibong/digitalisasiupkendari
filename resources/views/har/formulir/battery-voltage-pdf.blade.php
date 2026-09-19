<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Pengukuran Tegangan Battery - {{ $data['unit']->name ?? 'Unit' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: {{ $data['page_margin_top'] ?? 8 }}mm {{ $data['page_margin_right'] ?? 10 }}mm {{ $data['page_margin_bottom'] ?? 8 }}mm {{ $data['page_margin_left'] ?? 10 }}mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 8px;
            line-height: {{ $data['line_spacing'] ?? '1.1' }};
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .table-full {
            width: 100%;
            border-collapse: collapse;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        /* Kop Surat */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        .kop-table td {
            border: 1px solid #000;
            padding: 3px 6px;
            vertical-align: middle;
        }
        .kop-meta td {
            border: 1px solid #000;
            padding: 2px 4px;
            font-size: 7.5px;
        }

        /* Title Banner */
        .title-banner {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 6px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            letter-spacing: 0.5px;
        }

        /* Specs table */
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 8px;
        }
        .specs-table td {
            padding: 2.5px 6px;
            border: none;
            border-bottom: 1px solid #000;
        }

        /* Grid Table for 24V and 110V */
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 8px;
            table-layout: fixed;
        }
        .grid-table td,
        .grid-table th {
            border: 1px solid #000;
            padding: 2.8px 2px;
            vertical-align: middle;
            text-align: center;
        }
        .grid-header {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 8.5px;
            padding: 3.5px 2px;
        }

        /* Charging Conditions Table */
        .charge-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 8px;
            table-layout: fixed;
        }
        .charge-table td,
        .charge-table th {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
        }
        .charge-header {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 8.5px;
            text-align: center;
            padding: 4px 2px;
        }

        /* Notes Box */
        .notes-box {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px 6px;
            min-height: 42px;
            font-size: 8px;
        }

        /* Signatures */
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            border-top: none;
            text-align: center;
            font-size: 8px;
            margin-top: -1px;
        }
        .sign-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
            width: 33.333%;
        }
        .sign-name {
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    {{-- KOP SURAT RESMI --}}
    <table class="kop-table">
        <tr>
            <td style="width: 25%; text-align: left;">
                @if(!empty($data['logo_pln']))
                    <img src="{{ $data['logo_pln'] }}" alt="PLN Nusantara Power" style="height: 28px; vertical-align: middle;">
                @else
                    <strong style="color: #0C7DBB; font-size: 10px;">PLN Nusantara Power</strong>
                @endif
                <div style="font-size: 7.5px; font-weight: bold; margin-top: 1px;">
                    {{ $data['ul_label'] }}
                </div>
            </td>
            <td style="width: 44%; text-align: center;">
                <div style="font-weight: bold; font-size: 10px; letter-spacing: 0.5px;">PT. PLN NUSANTARA POWER</div>
                <div style="font-weight: bold; font-size: 8.5px; margin-top: 1px;">UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI</div>
                <div style="font-weight: bold; font-size: 7.5px; margin-top: 1px;">{{ strtoupper($data['ul_label']) }}</div>
            </td>
            <td style="width: 8%; text-align: center;">
                @if(!empty($data['logo_k3']))
                    <img src="{{ $data['logo_k3'] }}" alt="K3" style="height: 30px; vertical-align: middle;">
                @endif
            </td>
            <td style="width: 23%; padding: 0;">
                <table class="table-full kop-meta">
                    <tr>
                        <td style="width: 40%; white-space: nowrap;">No. Dokumen</td>
                        <td style="width: 60%; white-space: nowrap;">: {{ $data['document_number'] }}</td>
                    </tr>
                    <tr>
                        <td>Revisi</td>
                        <td>: {{ $data['revision'] }}</td>
                    </tr>
                    <tr>
                        <td>Tanggal</td>
                        <td>: {{ $data['effective_date'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- JUDUL DOKUMEN --}}
    <div class="title-banner">
        FORMULIR PENGUKURAN TEGANGAN BATTERY
    </div>

    {{-- SPESIFIKASI TEKNIS MESIN --}}
    <table class="specs-table">
        <tr>
            <td style="width: 25%;">
                <strong>Merek</strong> : {{ $data['brand'] ?: '—' }}
            </td>
            <td style="width: 25%;">
                <strong>Type</strong> : {{ $data['model_type'] ?: '—' }}
            </td>
            <td style="width: 25%;">
                <strong>Daya Terpasang</strong> : {{ $data['installed_power'] ? $data['installed_power'] : '—' }}
            </td>
            <td style="width: 25%;">
                <strong>Daya mampu</strong> : {{ $data['capable_power'] ? $data['capable_power'] : '—' }}
            </td>
        </tr>
        <tr>
            <td style="border-bottom: none;">
                <strong>No. Seri</strong> : {{ $data['serial_number'] ?: '—' }}
            </td>
            <td style="border-bottom: none;">
                <strong>Mesin No</strong> : {{ $data['machine_number'] ?: '—' }}
            </td>
            <td style="border-bottom: none;">
                <strong>RPM</strong> : {{ $data['rpm'] ?: '—' }}
            </td>
            <td style="border-bottom: none;">
                <strong>TGL</strong> : {{ $data['test_date'] }}
            </td>
        </tr>
    </table>

    {{-- PENGUKURAN 24 VOLT (12 CELLS) --}}
    @php
        $map24v = collect($data['cells_24v'])->keyBy('cell')->all();
    @endphp
    <table class="grid-table">
        <tr>
            <th colspan="12" class="grid-header" style="width: 80%;">24 VOLT</th>
            <td style="width: 20%; text-align: left; padding-left: 6px; font-weight: bold;">
                Tertinggi : {{ $data['summary_24v']['max'] ?? '' }}
            </td>
        </tr>
        <tr>
            @for($c = 1; $c <= 11; $c += 2)
                <td style="width: 3%; font-weight: bold; background-color: #fafafa;">{{ $c }}</td>
                <td style="width: 10.33%; font-weight: bold;">{{ $map24v[$c]['voltage'] ?? '—' }}</td>
            @endfor
            <td style="text-align: left; padding-left: 6px; font-weight: bold;">
                Terendah : {{ $data['summary_24v']['min'] ?? '' }}
            </td>
        </tr>
        <tr>
            @for($c = 2; $c <= 12; $c += 2)
                <td style="width: 3%; font-weight: bold; background-color: #fafafa;">{{ $c }}</td>
                <td style="width: 10.33%; font-weight: bold;">{{ $map24v[$c]['voltage'] ?? '—' }}</td>
            @endfor
            <td style="text-align: left; padding-left: 6px; font-weight: bold;">
                Total : {{ $data['summary_24v']['total'] ?? '' }}
            </td>
        </tr>
    </table>

    {{-- PENGUKURAN 110 VOLT (55 CELLS) --}}
    @php
        $map110v = collect($data['cells_110v'])->keyBy('cell')->all();
    @endphp
    <table class="grid-table" style="margin-top: 2px;">
        <thead>
            <tr>
                <th colspan="16" class="grid-header">110 VOLT</th>
            </tr>
        </thead>
        <tbody>
            @for($r = 0; $r < 7; $r++)
                <tr>
                    @for($g = 0; $g < 8; $g++)
                        @php
                            $cellNum = ($g * 7) + $r + 1;
                        @endphp
                        @if($cellNum <= 55)
                            <td style="width: 3.2%; font-weight: bold; background-color: #fafafa;">{{ $cellNum }}</td>
                            <td style="width: 9.3%; font-weight: bold;">{{ $map110v[$cellNum]['voltage'] ?? '—' }}</td>
                        @else
                            <td style="width: 3.2%; background-color: #fafafa;"></td>
                            <td style="width: 9.3%;"></td>
                        @endif
                    @endfor
                </tr>
            @endfor
            {{-- Summary 110V --}}
            <tr>
                <td colspan="7" style="text-align: left; padding-left: 6px; font-weight: bold;">
                    Tertinggi : {{ $data['summary_110v']['max'] ?? '' }}
                </td>
                <td colspan="9" style="text-align: left; padding-left: 10px; font-weight: bold;">
                    Total : {{ $data['summary_110v']['total'] ?? '' }}
                </td>
            </tr>
            <tr>
                <td colspan="16" style="text-align: left; padding-left: 6px; font-weight: bold;">
                    Terendah : {{ $data['summary_110v']['min'] ?? '' }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- TABEL KONDISI CHARGING / RECTIFIER --}}
    <table class="charge-table" style="margin-top: 2px;">
        <thead>
            <tr class="charge-header">
                <th rowspan="2" style="width: 14%;" colspan="2">ITEM</th>
                <th style="width: 23%;">24 VOLT</th>
                <th style="width: 23%;">110 VOLT</th>
                <th rowspan="2" style="width: 40%;">KETERANGAN</th>
            </tr>
            <tr class="charge-header">
                <th>KONDISI</th>
                <th>KONDISI</th>
            </tr>
        </thead>
        <tbody>
            @php
                $modes = ['FLOATING', 'EQUILIZING', 'BOOSTING'];
                $itemsByMode = collect($data['charging_conditions'])->groupBy('mode');
            @endphp
            @foreach($modes as $mode)
                @php
                    $group = $itemsByMode->get($mode, collect());
                @endphp
                @foreach($group as $idx => $row)
                    <tr>
                        @if($idx === 0)
                            <td rowspan="3" class="text-center font-bold" style="width: 8%; background-color: #fafafa;">
                                {{ $mode }}
                            </td>
                        @endif
                        <td style="width: 8%; font-weight: 600; padding-left: 4px;">{{ $row['item'] }}</td>
                        <td class="text-center font-bold">{{ $row['cond_24v'] ?? '' }}</td>
                        <td class="text-center font-bold">{{ $row['cond_110v'] ?? '' }}</td>
                        <td style="padding-left: 4px;">{{ $row['notes'] ?? '' }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    {{-- CATATAN --}}
    <div class="notes-box">
        <div style="font-weight: bold; margin-bottom: 2px;">Catatan :</div>
        @if(!empty($data['notes']))
            <div style="font-style: italic; white-space: pre-line; line-height: 1.2;">
                {{ $data['notes'] }}
            </div>
        @else
            <div style="margin-top: 8px; border-bottom: 1px dotted #888; width: 90%;"></div>
        @endif
    </div>

    {{-- TANDA TANGAN (3 KOLOM) --}}
    <table class="sign-table">
        <tr>
            <td>
                <div>Mengetahui,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $data['manager_ul_title'] }}</div>
                <div style="height: 38px; margin-top: 3px;">
                    @if(!empty($data['manager_ul_signature']))
                        <img src="{{ $data['manager_ul_signature'] }}" alt="Tanda Tangan" style="max-height: 36px; max-width: 110px;">
                    @endif
                </div>
                <div class="sign-name" style="margin-top: 3px;">{{ $data['manager_ul_name'] }}</div>
            </td>
            <td>
                <div>Diperiksa,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $data['tl_har_title'] }}</div>
                <div style="height: 38px; margin-top: 3px;">
                    @if(!empty($data['tl_har_signature']))
                        <img src="{{ $data['tl_har_signature'] }}" alt="Tanda Tangan" style="max-height: 36px; max-width: 110px;">
                    @endif
                </div>
                <div class="sign-name" style="margin-top: 3px;">{{ $data['tl_har_name'] }}</div>
            </td>
            <td>
                <div>Dibuat,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $data['staff_har_title'] }}</div>
                <div style="height: 38px; margin-top: 3px;">
                    @if(!empty($data['staff_har_signature']))
                        <img src="{{ $data['staff_har_signature'] }}" alt="Tanda Tangan" style="max-height: 36px; max-width: 110px;">
                    @endif
                </div>
                <div class="sign-name" style="margin-top: 3px;">{{ $data['staff_har_name'] }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
