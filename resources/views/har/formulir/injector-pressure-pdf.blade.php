<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Pengukuran Tekanan Pengabutan Injektor - {{ $data['unit']->name ?? 'Unit' }}</title>
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
            font-size: 8.5px;
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

        /* Outer Frame & Kop Surat */
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
            padding: 4px 6px;
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

        /* Diagram Box */
        .diagram-box {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 2px 6px;
            text-align: center;
        }
        .diagram-caption {
            text-align: left;
            font-size: 8px;
            font-weight: normal;
        }
        .diagram-note {
            text-align: right;
            font-size: 8px;
            font-style: italic;
            font-weight: bold;
            padding-bottom: 2px;
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
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .matrix-header th {
            font-weight: bold;
            font-size: 8.5px;
            line-height: 1.15;
            padding: 4px 2px;
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
        FORMULIR PENGUKURAN TEKANAN PENGABUTAN INJEKTOR
    </div>

    {{-- SPESIFIKASI TEKNIS MESIN --}}
    <table class="specs-table">
        <tr>
            <td style="width: 24%;">
                <strong>Merek</strong> : {{ $data['brand'] ?: '—' }}
            </td>
            <td style="width: 26%;">
                <strong>Tipe</strong> : {{ $data['model_type'] ?: '—' }}
            </td>
            <td style="width: 26%;">
                <strong>Daya Terpasang</strong> : {{ $data['installed_power'] ? $data['installed_power'] : '—' }}
            </td>
            <td style="width: 24%;">
                <strong>Daya mampu</strong> : {{ $data['capable_power'] ? $data['capable_power'] : '—' }}
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

    {{-- KOTAK DIAGRAM / CONTOH INJEKTOR --}}
    <div class="diagram-box">
        <div class="diagram-caption">contoh :</div>
        <div style="margin: 2px 0 0 0;">
            @if(!empty($data['injector_diagram']))
                <img src="{{ $data['injector_diagram'] }}" alt="Diagram Injektor" style="max-height: 85px; max-width: 80%; vertical-align: middle;">
            @else
                <div style="height: 80px;"></div>
            @endif
        </div>
        <div class="diagram-note">
            Note:Lihat Buku Petunjuk Pabrik Untuk Lebih Detail
        </div>
    </div>

    {{-- TABEL PENGUKURAN TEKANAN PENGABUTAN INJEKTOR --}}
    @php
        $measList = $data['measurements'] ?? [];
    @endphp
    <table class="matrix-table">
        <tr class="matrix-header">
            <th style="width: 8%;">CYL.</th>
            <th style="width: 26%;">TEK. PENGABUTAN<br>SEBELUM (kg/cm²)</th>
            <th style="width: 26%;">TEK. PENGABUTAN<br>SESUDAH (kg/cm2)</th>
            <th style="width: 16%;">LUBANG NOZZLE (BH)</th>
            <th style="width: 24%;">KETERANGAN</th>
        </tr>

        @foreach($measList as $row)
            <tr>
                <td style="font-weight: bold;">{{ $row['cylinder'] }}</td>
                <td>{{ $row['pressure_before'] ?? '' }}</td>
                <td>{{ $row['pressure_after'] ?? '' }}</td>
                <td>{{ $row['nozzle_holes'] ?? '' }}</td>
                <td style="text-align: left; padding-left: 6px;">{{ $row['notes'] ?? '' }}</td>
            </tr>
        @endforeach

        {{-- STANDAR YANG DIIZINKAN --}}
        <tr>
            <td colspan="2" style="text-align: left; padding: 4px 6px; font-weight: bold;">
                Standar Yang di Izinkan :
            </td>
            <td style="text-align: center; font-weight: bold;">
                {{ $data['standard_allowed'] ?? '270 kg/cm²' }}
            </td>
            <td></td>
            <td></td>
        </tr>

        {{-- PEMERIKSAAN VISUAL --}}
        <tr>
            <td colspan="5" style="text-align: left; padding: 4px 6px; vertical-align: top; min-height: 32px;">
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
