<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Pengukuran Clearance Valve - {{ $data['unit']->name ?? 'Unit' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: {{ $data['page_margin_top'] ?? 10 }}mm {{ $data['page_margin_right'] ?? 12 }}mm {{ $data['page_margin_bottom'] ?? 10 }}mm {{ $data['page_margin_left'] ?? 12 }}mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 9px;
            line-height: {{ $data['line_spacing'] ?? '1.15' }};
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
            padding: 4px 6px;
            vertical-align: middle;
        }
        .kop-meta td {
            border: 1px solid #000;
            padding: 2px 4px;
            font-size: 8px;
        }

        /* Title Banner */
        .title-banner {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        /* Specs table */
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 8.5px;
        }
        .specs-table td {
            padding: 3px 6px;
            border: none;
            border-bottom: 1px solid #000;
        }

        /* Note Bar */
        .note-bar {
            text-align: right;
            font-size: 8px;
            font-style: italic;
            font-weight: bold;
            padding: 4px 2px 3px 2px;
        }

        /* Matrix Table */
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1.5px solid #000;
            font-size: 8.5px;
        }
        .matrix-table td,
        .matrix-table th {
            border: 1px solid #000;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }
        .keterangan-cell {
            vertical-align: top;
            text-align: left;
            padding: 5px 6px;
            font-size: 8.5px;
            line-height: 1.35;
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
            font-size: 8.5px;
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
                    <img src="{{ $data['logo_pln'] }}" alt="PLN Nusantara Power" style="height: 30px; vertical-align: middle;">
                @else
                    <strong style="color: #0C7DBB;">PLN Nusantara Power</strong>
                @endif
                <div style="font-size: 8px; font-weight: bold; margin-top: 2px;">
                    {{ $data['ul_label'] }}
                </div>
            </td>
            <td style="width: 44%; text-align: center;">
                <div style="font-weight: bold; font-size: 11px; letter-spacing: 0.5px;">PT. PLN NUSANTARA POWER</div>
                <div style="font-weight: bold; font-size: 9.5px; margin-top: 2px;">UNIT PEMBANGKITAN KENDARI</div>
            </td>
            <td style="width: 8%; text-align: center;">
                @if(!empty($data['logo_k3']))
                    <img src="{{ $data['logo_k3'] }}" alt="K3" style="height: 34px; vertical-align: middle;">
                @endif
            </td>
            <td style="width: 23%; padding: 0;">
                <table class="table-full kop-meta">
                    <tr>
                        <td style="width: 38%; white-space: nowrap;">No. Dokumen</td>
                        <td style="width: 62%; white-space: nowrap;">: {{ $data['document_number'] }}</td>
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
        FORMULIR PENGUKURAN CLEARANCE VALVE
    </div>

    {{-- SPESIFIKASI TEKNIS MESIN --}}
    <table class="specs-table">
        <tr>
            <td style="width: 24%;">
                <strong>Merek</strong> : {{ $data['brand'] ?: '—' }}
            </td>
            <td style="width: 26%;">
                <strong>Type</strong> : {{ $data['model_type'] ?: '—' }}
            </td>
            <td style="width: 26%;">
                <strong>Daya Terpasang</strong> : {{ $data['installed_power'] ? $data['installed_power'] . ' kW' : '—' }}
            </td>
            <td style="width: 24%;">
                <strong>Daya mampu</strong> : {{ $data['capable_power'] ? $data['capable_power'] . ' KW' : '—' }}
            </td>
        </tr>
        <tr>
            <td>
                <strong>No. Seri</strong> : {{ $data['serial_number'] ?: '—' }}
            </td>
            <td>
                <strong>Mesin No</strong> : {{ $data['machine_number'] ?: '—' }}
            </td>
            <td>
                <strong>RPM</strong> : {{ $data['rpm'] ?: '—' }}
            </td>
            <td>
                <strong>TGL</strong> : {{ $data['test_date'] }}
            </td>
        </tr>
    </table>

    {{-- NOTE ATAS TABEL --}}
    <div class="note-bar">
        Note:Lihat Buku Petunjuk Pabrik Untuk Lebih Detail
    </div>

    {{-- TABEL PENGUKURAN CLEARANCE VALVE --}}
    @php
        $measList = $data['measurements'] ?? [];
        $cylindersCount = count($measList);
    @endphp
    <table class="matrix-table">
        <tr>
            <td rowspan="4" style="width: 7%; font-weight: bold;">CYL.</td>
            <td colspan="8" style="width: 54%; font-weight: bold; font-size: 9px; letter-spacing: 0.5px;">CLEREANCE VALVE</td>
            <td rowspan="4" style="width: 39%; font-weight: bold;">KETERANGAN</td>
        </tr>
        <tr>
            <td colspan="4" style="font-weight: bold;">Exhaust</td>
            <td colspan="4" style="font-weight: bold;">Intake</td>
        </tr>
        <tr>
            <td colspan="2" style="font-size: 8px; font-weight: bold;">sebelum</td>
            <td colspan="2" style="font-size: 8px; font-weight: bold;">sesudah</td>
            <td colspan="2" style="font-size: 8px; font-weight: bold;">Sebelum</td>
            <td colspan="2" style="font-size: 8px; font-weight: bold;">sesudah</td>
        </tr>
        <tr>
            <td style="width: 6.75%; font-weight: bold;">R</td>
            <td style="width: 6.75%; font-weight: bold;">L</td>
            <td style="width: 6.75%; font-weight: bold;">R</td>
            <td style="width: 6.75%; font-weight: bold;">L</td>
            <td style="width: 6.75%; font-weight: bold;">R</td>
            <td style="width: 6.75%; font-weight: bold;">L</td>
            <td style="width: 6.75%; font-weight: bold;">R</td>
            <td style="width: 6.75%; font-weight: bold;">L</td>
        </tr>

        @foreach($measList as $idx => $row)
            <tr>
                <td style="font-weight: bold;">{{ $row['cylinder'] }}</td>
                <td>{{ $row['ex_before_r'] ?? '' }}</td>
                <td>{{ $row['ex_before_l'] ?? '' }}</td>
                <td>{{ $row['ex_after_r'] ?? '' }}</td>
                <td>{{ $row['ex_after_l'] ?? '' }}</td>
                <td>{{ $row['in_before_r'] ?? '' }}</td>
                <td>{{ $row['in_before_l'] ?? '' }}</td>
                <td>{{ $row['in_after_r'] ?? '' }}</td>
                <td>{{ $row['in_after_l'] ?? '' }}</td>
                @if($idx === 0)
                    <td rowspan="{{ $cylindersCount + 1 }}" class="keterangan-cell">
                        @if(!empty($data['cylinder_notes']))
                            {!! nl2br(e($data['cylinder_notes'])) !!}
                        @else
                            &nbsp;
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach

        {{-- STANDAR YANG DIIZINKAN --}}
        <tr>
            <td colspan="5" style="text-align: left; padding: 4px 6px; font-weight: bold;">
                Standar Yang di Izinkan :
            </td>
            <td colspan="4" style="text-align: center; font-weight: bold; font-size: 8px;">
                @if(!empty($data['standard_allowed']))
                    {{ $data['standard_allowed'] }}
                @else
                    EX : {{ $data['standard_ex'] ?? '0.60 mm' }} &nbsp;&nbsp;&nbsp;&nbsp; IN : {{ $data['standard_in'] ?? '0.30 mm' }}
                @endif
            </td>
        </tr>

        {{-- PEMERIKSAAN VISUAL --}}
        <tr>
            <td colspan="10" style="text-align: left; padding: 4px 6px; vertical-align: top; min-height: 38px;">
                <strong>Pemeriksaan visual :</strong>
                <div style="margin-top: 3px; line-height: 1.35;">
                    @if(!empty($data['visual_inspection']))
                        {!! nl2br(e($data['visual_inspection'])) !!}
                    @else
                        <span style="letter-spacing: 2px;">............................................................................................................................................................</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- TANDA TANGAN (3 KOLOM RESMI) --}}
    <table class="sign-table">
        <tr>
            <td>
                <div>Mengetahui,</div>
                <div style="font-size: 8.5px; font-weight: bold; margin-top: 1px;">{{ $data['manager_ul_title'] }}</div>
                <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin: 3px 0;">
                    @if(!empty($data['manager_ul_signature']))
                        <img src="{{ $data['manager_ul_signature'] }}" alt="TTD Manager" style="max-height: 46px; max-width: 120px;">
                    @else
                        <div style="height: 46px;"></div>
                    @endif
                </div>
                <div class="sign-name">{{ $data['manager_ul_name'] }}</div>
            </td>
            <td>
                <div>Diperiksa,</div>
                <div style="font-size: 8.5px; font-weight: bold; margin-top: 1px;">{{ $data['tl_har_title'] }}</div>
                <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin: 3px 0;">
                    @if(!empty($data['tl_har_signature']))
                        <img src="{{ $data['tl_har_signature'] }}" alt="TTD TL" style="max-height: 46px; max-width: 120px;">
                    @else
                        <div style="height: 46px;"></div>
                    @endif
                </div>
                <div class="sign-name">{{ $data['tl_har_name'] }}</div>
            </td>
            <td>
                <div>Dibuat,</div>
                <div style="font-size: 8.5px; font-weight: bold; margin-top: 1px;">{{ $data['staff_har_title'] }}</div>
                <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin: 3px 0;">
                    @if(!empty($data['staff_har_signature']))
                        <img src="{{ $data['staff_har_signature'] }}" alt="TTD Staff" style="max-height: 46px; max-width: 120px;">
                    @else
                        <div style="height: 46px;"></div>
                    @endif
                </div>
                <div class="sign-name">{{ $data['staff_har_name'] }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
