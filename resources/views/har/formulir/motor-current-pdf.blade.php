<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Pengukuran Arus Kerja Elektro Motor - {{ $data['unit']->name ?? 'Unit' }}</title>
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
            font-size: 8.5px;
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
            padding: 5px 6px;
            text-align: center;
            font-weight: bold;
            font-size: 10.5px;
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

        /* Matrix Table */
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 8.5px;
            table-layout: fixed;
        }
        .matrix-table td,
        .matrix-table th {
            border: 1px solid #000;
            padding: 4px 4px;
            vertical-align: middle;
        }
        .matrix-header th {
            font-weight: bold;
            font-size: 8.5px;
            line-height: 1.15;
            padding: 5px 2px;
            text-align: center;
        }

        /* Notes Box */
        .notes-box {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 6px 8px;
            min-height: 55px;
            font-size: 8.5px;
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
            padding: 5px 6px;
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
                    <strong style="color: #0C7DBB; font-size: 11px;">PLN Nusantara Power</strong>
                @endif
                <div style="font-size: 8px; font-weight: bold; margin-top: 2px;">
                    {{ $data['ul_label'] }}
                </div>
            </td>
            <td style="width: 44%; text-align: center;">
                <div style="font-weight: bold; font-size: 10.5px; letter-spacing: 0.5px;">PT. PLN NUSANTARA POWER</div>
                <div style="font-weight: bold; font-size: 9px; margin-top: 1px;">UNIT PEMBANGKITAN KENDARI</div>
                <div style="font-weight: bold; font-size: 8px; margin-top: 1px;">{{ strtoupper($data['ul_label']) }}</div>
            </td>
            <td style="width: 8%; text-align: center;">
                @if(!empty($data['logo_k3']))
                    <img src="{{ $data['logo_k3'] }}" alt="K3" style="height: 32px; vertical-align: middle;">
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
        DATA PENGUKURAN ARUS KERJA ELEKTRO MOTOR
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

    {{-- TABEL PENGUKURAN ARUS ELEKTRO MOTOR --}}
    <table class="matrix-table">
        <thead>
            <tr class="matrix-header" style="background-color: #f2f2f2;">
                <th rowspan="2" style="width: 6%;">NO</th>
                <th rowspan="2" style="width: 40%;">NAMA ELEKTRO-MOTOR</th>
                <th colspan="3" style="width: 30%;">ARUS PER PHASA (AMPERE)</th>
                <th rowspan="2" style="width: 24%;">KETERANGAN</th>
            </tr>
            <tr class="matrix-header" style="background-color: #f2f2f2;">
                <th style="width: 10%;">R</th>
                <th style="width: 10%;">S</th>
                <th style="width: 10%;">T</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['items'] as $item)
                <tr>
                    <td class="text-center font-bold">{{ $item['no'] }}</td>
                    <td class="text-left" style="padding-left: 6px;">{{ $item['motor_name'] }}</td>
                    <td class="text-center font-bold">{{ $item['current_r'] !== '' && $item['current_r'] !== null ? $item['current_r'] : '—' }}</td>
                    <td class="text-center font-bold">{{ $item['current_s'] !== '' && $item['current_s'] !== null ? $item['current_s'] : '—' }}</td>
                    <td class="text-center font-bold">{{ $item['current_t'] !== '' && $item['current_t'] !== null ? $item['current_t'] : '—' }}</td>
                    <td class="text-left" style="padding-left: 5px;">{{ $item['notes'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- CATATAN --}}
    <div class="notes-box">
        <div style="font-weight: bold; margin-bottom: 2px;">Catatan :</div>
        @if(!empty($data['notes']))
            <div style="font-style: italic; white-space: pre-line; line-height: 1.3;">
                {{ $data['notes'] }}
            </div>
        @else
            <div style="margin-top: 10px; border-bottom: 1px dotted #888; width: 90%;"></div>
            <div style="margin-top: 8px; border-bottom: 1px dotted #888; width: 90%;"></div>
        @endif
    </div>

    {{-- TANDA TANGAN (3 KOLOM) --}}
    <table class="sign-table">
        <tr>
            <td>
                <div>Mengetahui,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $data['manager_ul_title'] }}</div>
                <div style="height: 44px; margin-top: 4px;">
                    @if(!empty($data['manager_ul_signature']))
                        <img src="{{ $data['manager_ul_signature'] }}" alt="Tanda Tangan" style="max-height: 42px; max-width: 120px;">
                    @endif
                </div>
                <div class="sign-name" style="margin-top: 4px;">{{ $data['manager_ul_name'] }}</div>
            </td>
            <td>
                <div>Diperiksa,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $data['tl_har_title'] }}</div>
                <div style="height: 44px; margin-top: 4px;">
                    @if(!empty($data['tl_har_signature']))
                        <img src="{{ $data['tl_har_signature'] }}" alt="Tanda Tangan" style="max-height: 42px; max-width: 120px;">
                    @endif
                </div>
                <div class="sign-name" style="margin-top: 4px;">{{ $data['tl_har_name'] }}</div>
            </td>
            <td>
                <div>Dibuat,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $data['staff_har_title'] }}</div>
                <div style="height: 44px; margin-top: 4px;">
                    @if(!empty($data['staff_har_signature']))
                        <img src="{{ $data['staff_har_signature'] }}" alt="Tanda Tangan" style="max-height: 42px; max-width: 120px;">
                    @endif
                </div>
                <div class="sign-name" style="margin-top: 4px;">{{ $data['staff_har_name'] }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
