<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Checklist Hydrotest - {{ $data['unit']->name ?? 'Unit' }}</title>
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

        /* Note banner */
        .note-banner {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 2px 6px;
            text-align: right;
            font-size: 8.5px;
            font-style: italic;
        }

        /* Hydrotest Table */
        .hydro-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 9px;
        }
        .hydro-table th,
        .hydro-table td {
            border: 1px solid #000;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }
        .hydro-table .th-header {
            font-weight: bold;
            background: #fff;
        }
        .check-val {
            font-weight: bold;
            font-size: 11px;
            text-align: center;
        }

        /* Legend & Notes */
        .legend-box {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
            line-height: 1.35;
        }
        .notes-box {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px 6px;
            font-size: 9.5px;
            min-height: 48px;
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
        FORMULIR CHECKLIST HYDROTES
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
                <strong>Daya mampu</strong> : {{ $data['capable_power'] ? $data['capable_power'] . ' kW' : '—' }}
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

    {{-- NOTE BANNER --}}
    <div class="note-banner">
        Note:Lihat Buku Petunjuk Pabrik Untuk Lebih Detail
    </div>

    {{-- TABEL CHECKLIST HYDROTEST --}}
    @php
        $count = max(1, count($data['checklist_items']));
        $col1Width = $count > 12 ? '13%' : ($count > 8 ? '14%' : '15%');
        $ketWidth  = $count > 12 ? '18%' : ($count > 8 ? '20%' : '22%');
        $checkFontSize = $count > 12 ? '9px' : '10.5px';
        $headerFontSize = $count > 12 ? '7.5px' : '8.5px';
    @endphp
    <table class="hydro-table">
        <tbody>
            {{-- Baris 1: Header Cylinder dan Keterangan --}}
            <tr>
                <td class="th-header font-bold" style="width: {{ $col1Width }}; font-size: {{ $headerFontSize }};">Cylinder</td>
                @foreach($data['checklist_items'] as $item)
                    <td colspan="2" class="th-header font-bold" style="font-size: {{ $headerFontSize }};">
                        {{ $item['cylinder'] ?? $loop->iteration }}
                    </td>
                @endforeach
                <td class="th-header font-bold" style="width: {{ $ketWidth }}; font-size: {{ $headerFontSize }}; text-align: center; padding: 4px 6px;">
                    KETERANGAN
                </td>
            </tr>

            {{-- Baris 2: Sub-header Kebocoran & Oring Liner (OL) / Liner (L), serta Cell Keterangan yang menyatu (rowspan=2) --}}
            <tr>
                <td class="th-header font-bold" style="font-size: {{ $headerFontSize }};">KEBOCORAN</td>
                @foreach($data['checklist_items'] as $item)
                    <td class="th-header font-bold" style="font-size: {{ $headerFontSize }};">OL</td>
                    <td class="th-header font-bold" style="font-size: {{ $headerFontSize }};">L</td>
                @endforeach
                @php
                    $notesArr = [];
                    foreach ($data['checklist_items'] as $it) {
                        if (!empty($it['notes'])) {
                            $notesArr[] = 'Cyl ' . $it['cylinder'] . ': ' . $it['notes'];
                        }
                    }
                @endphp
                <td rowspan="2" style="width: {{ $ketWidth }}; font-size: 8px; text-align: left; vertical-align: top; padding: 4px; line-height: 1.25;">
                    @if(count($notesArr) > 0)
                        {!! implode('<br>', array_map('e', $notesArr)) !!}
                    @else
                        &nbsp;
                    @endif
                </td>
            </tr>

            {{-- Baris 3: Tanda Ceklis (v/x) pada masing-masing silinder --}}
            <tr>
                <td class="font-bold" style="font-size: {{ $headerFontSize }};">(v/x)</td>
                @foreach($data['checklist_items'] as $item)
                    <td class="check-val" style="font-size: {{ $checkFontSize }};">
                        @if(($item['oring_liner'] ?? '') === 'v')
                            <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>
                        @elseif(($item['oring_liner'] ?? '') === 'X')
                            <span style="color: #c00;">X</span>
                        @else
                            {{ $item['oring_liner'] ?? '-' }}
                        @endif
                    </td>
                    <td class="check-val" style="font-size: {{ $checkFontSize }};">
                        @if(($item['liner'] ?? '') === 'v')
                            <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>
                        @elseif(($item['liner'] ?? '') === 'X')
                            <span style="color: #c00;">X</span>
                        @else
                            {{ $item['liner'] ?? '-' }}
                        @endif
                    </td>
                @endforeach
            </tr>

            {{-- Baris 4: Standar Yang di Izinkan (merentang hingga seluruh sisa tabel termasuk kolom keterangan) --}}
            <tr>
                <td class="text-left font-bold" style="padding-left: 4px; font-size: {{ $headerFontSize }};">
                    Standar Yang di Izinkan :
                </td>
                <td colspan="{{ ($count * 2) + 1 }}" class="text-left font-bold" style="padding-left: 6px; font-size: {{ $headerFontSize }};">
                    {{ $data['standard_allowed'] ?? 'Tidak ada kebocoran' }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- KETERANGAN / LEGENDA --}}
    <div class="legend-box">
        <strong>KET :</strong><br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>OL</strong> : Oring liner<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>L</strong> &nbsp;: Liner<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>&radic; / v</strong> : Kondisi tidak ada kebocoran<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>X</strong> &nbsp;: Kondisi ada kebocoran
    </div>

    {{-- CATATAN TAMBAHAN --}}
    <div class="notes-box">
        <strong>Catatan :</strong>
        <div style="margin-top: 4px; min-height: 30px; line-height: 1.4;">
            @if(!empty($data['notes']))
                {!! nl2br(e($data['notes'])) !!}
            @else
                ..........................................................................................................................................................................................<br>
                ..........................................................................................................................................................................................
            @endif
        </div>
    </div>

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
                <div>Dibuat,</div>
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
