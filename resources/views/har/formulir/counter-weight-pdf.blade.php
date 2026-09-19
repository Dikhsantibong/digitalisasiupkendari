<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Pemeriksaan Kondisi Kekencangan Baut Counter Weight - {{ $data['unit']->name ?? 'Unit' }}</title>
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

        /* Note Bar */
        .note-bar {
            text-align: right;
            font-size: 8px;
            font-style: italic;
            font-weight: bold;
            padding: 3px 2px 2px 2px;
        }

        /* Matrix Table */
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1.5px solid #000;
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
            vertical-align: middle;
        }
        .matrix-header th {
            font-weight: bold;
            font-size: 8.5px;
            line-height: 1.15;
            padding: 4px 2px;
            text-align: center;
        }

        /* Checkbox styling */
        .chk-box {
            display: inline-block;
            width: 9px;
            height: 9px;
            border: 1.2px solid #000;
            vertical-align: -1px;
            margin-right: 5px;
            background-color: #fff;
        }
        .chk-checked {
            background-color: #111;
        }

        /* Notes Box */
        .notes-box {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px 8px;
            min-height: 48px;
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
        FORMULIR PEMERIKSAAN KONDISI KEKENCANGAN BAUT COUNTER WEIGHT
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
                <strong>Daya Terpasang</strong> : {{ $data['installed_power'] ? $data['installed_power'] : '—' }}
            </td>
            <td style="width: 24%;">
                <strong>Daya mampu</strong> : {{ $data['capable_power'] ? $data['capable_power'] : '—' }}
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

    {{-- TABEL PEMERIKSAAN KONDISI KEKENCANGAN BAUT COUNTER WEIGHT --}}
    @php
        $measList = $data['measurements'] ?? [];
    @endphp
    <table class="matrix-table">
        <tr class="matrix-header">
            <th style="width: 10%;">CYL NO.</th>
            <th colspan="2" style="width: 45%;">KONDISI BAUT COUNTER WEIGHT</th>
            <th style="width: 45%;">KETERANGAN</th>
        </tr>

        @foreach($measList as $row)
            @php
                $isBaik = ($row['condition'] ?? 'baik') === 'baik';
            @endphp
            {{-- Sub-baris 1: Option 1 Baik --}}
            <tr>
                <td rowspan="2" style="text-align: center; font-weight: bold; width: 10%;">
                    {{ $row['cylinder'] }}
                </td>
                <td style="width: 6%; text-align: center; font-weight: bold;">
                    1
                </td>
                <td style="width: 39%; text-align: left; padding-left: 10px;">
                    <span class="chk-box {{ $isBaik ? 'chk-checked' : '' }}"></span>
                    <span style="{{ $isBaik ? 'font-weight: bold;' : '' }}">Baik</span>
                </td>
                <td rowspan="2" style="width: 45%; text-align: left; padding: 4px 6px; vertical-align: top;">
                    {{ $row['notes'] ?? '' }}
                </td>
            </tr>
            {{-- Sub-baris 2: Option 2 Tidak Baik --}}
            <tr>
                <td style="width: 6%; text-align: center; font-weight: bold;">
                    2
                </td>
                <td style="width: 39%; text-align: left; padding-left: 10px;">
                    <span class="chk-box {{ ! $isBaik ? 'chk-checked' : '' }}"></span>
                    <span style="{{ ! $isBaik ? 'font-weight: bold;' : '' }}">Tidak Baik</span>
                </td>
            </tr>
        @endforeach

        {{-- STANDAR YANG DIIZINKAN --}}
        <tr>
            <td colspan="2" style="text-align: left; padding: 4px 6px; font-weight: bold; width: 16%;">
                Standar Yang di Izinkan :
            </td>
            <td colspan="2" style="text-align: left; padding: 4px 8px; width: 84%;">
                {{ $data['standard_allowed'] ?? 'Torsi Pengencangan Sesuai Manual Book / Kondisi Baik' }}
            </td>
        </tr>
    </table>

    {{-- CATATAN UMUM --}}
    <div class="notes-box">
        <strong>catatan :</strong>
        <div style="margin-top: 4px; line-height: 1.4;">
            @if(!empty($data['notes']))
                {!! nl2br(e($data['notes'])) !!}
            @else
                <div style="border-bottom: 1px dotted #888; height: 16px; margin-top: 4px;"></div>
                <div style="border-bottom: 1px dotted #888; height: 16px; margin-top: 4px;"></div>
            @endif
        </div>
    </div>

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
