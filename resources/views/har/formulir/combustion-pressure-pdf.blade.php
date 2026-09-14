<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Pengukuran Tekanan Pembakaran - {{ $data['unit']->name ?? 'Unit' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: {{ $data['page_margin_top'] ?? 12 }}mm {{ $data['page_margin_right'] ?? 15 }}mm {{ $data['page_margin_bottom'] ?? 12 }}mm {{ $data['page_margin_left'] ?? 15 }}mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 10px;
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
            font-size: 9px;
        }

        /* Title Banner */
        .title-banner {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 6px;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        /* Specs table */
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 9.5px;
        }
        .specs-table td {
            padding: 3px 6px;
            border: none;
            border-bottom: 1px solid #000;
        }

        /* Note Bar */
        .note-bar {
            text-align: right;
            font-size: 8.5px;
            font-weight: bold;
            padding: 4px 2px 3px 2px;
        }

        /* Matrix Table */
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        .matrix-table td,
        .matrix-table th {
            border: 1px solid #000;
            padding: 4px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .matrix-table .th-header {
            font-weight: bold;
            background: #fff;
        }
        .matrix-table .param-name {
            text-align: left;
            padding-left: 6px;
            font-weight: bold;
            font-size: 9px;
        }
        .matrix-val {
            font-size: 9px;
            text-align: center;
        }

        /* Inspection & Standard Box */
        .inspection-box {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 9px;
        }
        .inspection-box td {
            padding: 5px 6px;
            vertical-align: top;
        }
        .inspection-box tr:not(:last-child) td {
            border-bottom: 1px solid #000;
        }

        /* Signatures */
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            text-align: center;
            font-size: 9.5px;
        }
        .sign-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
            width: 33.33%;
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
                    <img src="{{ $data['logo_pln'] }}" alt="PLN Nusantara Power" style="height: 32px; vertical-align: middle;">
                @else
                    <strong style="color: #0C7DBB;">PLN Nusantara Power</strong>
                @endif
                <div style="font-size: 8.5px; font-weight: bold; margin-top: 2px;">
                    {{ $data['ul_label'] }}
                </div>
            </td>
            <td style="width: 48%; text-align: center;">
                <div style="font-weight: bold; font-size: 11px; letter-spacing: 0.5px;">PT.PLN NUSANTARA POWER</div>
                <div style="font-weight: bold; font-size: 10px; margin-top: 2px;">UNIT PEMBANGKITAN KENDARI</div>
            </td>
            <td style="width: 8%; text-align: center;">
                @if(!empty($data['logo_k3']))
                    <img src="{{ $data['logo_k3'] }}" alt="K3" style="height: 36px; vertical-align: middle;">
                @endif
            </td>
            <td style="width: 19%; padding: 0;">
                <table class="table-full kop-meta">
                    <tr>
                        <td style="width: 50%;">No. Dokumen</td>
                        <td>: {{ $data['document_number'] }}</td>
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
        FORMULIR PENGUKURAN TEKANAN PEMBAKARAN
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
                <strong>No.Seri</strong> : {{ $data['serial_number'] ?: '—' }}
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

    {{-- TABEL MATRIKS PENGUKURAN TEKANAN PEMBAKARAN --}}
    @php
        $cylindersCount = (int) ($data['cylinders_count'] ?? 8);
        $measMap = collect($data['measurements'] ?? [])->keyBy('cylinder')->all();
    @endphp
    <table class="matrix-table">
        <thead>
            <tr>
                <th class="th-header" style="width: 28%; text-align: left; padding-left: 6px;">Cylinder</th>
                @for($i = 1; $i <= $cylindersCount; $i++)
                    <th class="th-header" style="width: {{ 48 / $cylindersCount }}%;">{{ $i }}</th>
                @endfor
                <th class="th-header" style="width: 24%;">KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            {{-- Baris 1: Tekanan pembakaran(kg / cm²) --}}
            <tr>
                <td class="param-name">Tekanan pembakaran(kg / cm²)</td>
                @for($i = 1; $i <= $cylindersCount; $i++)
                    <td class="matrix-val">
                        {{ $measMap[$i]['combustion_pressure'] ?? '' }}
                    </td>
                @endfor
                {{-- KETERANGAN SPANS 3 ROWS --}}
                <td rowspan="3" style="text-align: left; vertical-align: top; padding: 6px; font-size: 8.5px; line-height: 1.35;">
                    @if(!empty($data['cylinder_notes']))
                        {!! nl2br(e($data['cylinder_notes'])) !!}
                    @else
                        &nbsp;
                    @endif
                </td>
            </tr>

            {{-- Baris 2: Temprature gas buang (°C) --}}
            <tr>
                <td class="param-name">Temprature gas buang (&deg;C)</td>
                @for($i = 1; $i <= $cylindersCount; $i++)
                    <td class="matrix-val">
                        {{ $measMap[$i]['exhaust_temp'] ?? '' }}
                    </td>
                @endfor
            </tr>

            {{-- Baris 3: Rack injection pump --}}
            <tr>
                <td class="param-name">Rack injection pump</td>
                @for($i = 1; $i <= $cylindersCount; $i++)
                    <td class="matrix-val">
                        {{ $measMap[$i]['rack_position'] ?? '' }}
                    </td>
                @endfor
            </tr>
        </tbody>
    </table>

    {{-- KOTAK STANDAR YANG DIIZINKAN & PEMERIKSAAN VISUAL --}}
    <table class="inspection-box">
        <tr>
            <td>
                <strong>Standar Yang di Izinkan :</strong>
                @if(!empty($data['standard_allowed']))
                    <span style="margin-left: 4px;">{{ $data['standard_allowed'] }}</span>
                @else
                    <span style="letter-spacing: 2px;">............................................................................................................................................................</span>
                @endif
            </td>
        </tr>
        <tr>
            <td style="min-height: 52px;">
                <strong>Pemeriksaan visual :</strong>
                @if(!empty($data['visual_inspection']))
                    <div style="margin-top: 4px; line-height: 1.4;">
                        {!! nl2br(e($data['visual_inspection'])) !!}
                    </div>
                @else
                    <div style="margin-top: 4px; line-height: 1.5; letter-spacing: 2px;">
                        ..........................................................................................................................................................................................<br>
                        ..........................................................................................................................................................................................
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- 3 KOLOM TANDA TANGAN --}}
    <table class="sign-table">
        <tr>
            <td>
                <div>Mengetahui,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $data['manager_ul_title'] }}</div>
                <div style="height: 58px; margin: 4px 0; display: flex; align-items: center; justify-content: center;">
                    @if(!empty($data['manager_ul_signature']))
                        <img src="{{ $data['manager_ul_signature'] }}" alt="Ttd Manager UL" style="max-height: 54px; max-width: 140px;">
                    @else
                        <div style="height: 54px;"></div>
                    @endif
                </div>
                <div class="sign-name">{{ $data['manager_ul_name'] }}</div>
            </td>
            <td>
                <div>Diperiksa,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $data['tl_har_title'] }}</div>
                <div style="height: 58px; margin: 4px 0; display: flex; align-items: center; justify-content: center;">
                    @if(!empty($data['tl_har_signature']))
                        <img src="{{ $data['tl_har_signature'] }}" alt="Ttd TL Har" style="max-height: 54px; max-width: 140px;">
                    @else
                        <div style="height: 54px;"></div>
                    @endif
                </div>
                <div class="sign-name">{{ $data['tl_har_name'] }}</div>
            </td>
            <td>
                <div>Pelaksana,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $data['staff_har_title'] }}</div>
                <div style="height: 58px; margin: 4px 0; display: flex; align-items: center; justify-content: center;">
                    @if(!empty($data['staff_har_signature']))
                        <img src="{{ $data['staff_har_signature'] }}" alt="Ttd Staff Har" style="max-height: 54px; max-width: 140px;">
                    @else
                        <div style="height: 54px;"></div>
                    @endif
                </div>
                <div class="sign-name">{{ $data['staff_har_name'] }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
