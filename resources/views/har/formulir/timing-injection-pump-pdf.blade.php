<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Checklist Timing Injection Pump - {{ $data['unit']->name ?? 'Unit' }}</title>
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

        /* Timing Table */
        .timing-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }
        .timing-table td,
        .timing-table th {
            border: 1px solid #000;
            padding: 4px 5px;
            text-align: center;
            vertical-align: middle;
        }
        .timing-table .th-header {
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
            line-height: 1.4;
        }
        .notes-box {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px 6px;
            font-size: 9.5px;
            min-height: 50px;
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
        FORMULIR CHECKLIST TIMING INJECTION PUMP
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

    {{-- TABEL CHECKLIST TIMING INJECTION PUMP --}}
    @php
        $count = max(1, count($data['checklist_items']));
        $notesArr = [];
        foreach ($data['checklist_items'] as $it) {
            if (!empty($it['notes'])) {
                $notesArr[] = 'Cyl ' . $it['cylinder'] . ': ' . $it['notes'];
            }
        }
    @endphp
    <table class="timing-table">
        <tbody>
            {{-- Header Baris 1 --}}
            <tr>
                <td rowspan="2" class="th-header font-bold" style="width: 10%;">CYL.</td>
                <td colspan="2" class="th-header font-bold" style="width: 50%;">TIMING PEMBAKARAN</td>
                <td rowspan="2" class="th-header font-bold" style="width: 40%; text-align: center;">KETERANGAN</td>
            </tr>
            {{-- Header Baris 2 --}}
            <tr>
                <td class="th-header font-bold" style="width: 25%;">SEBELUM</td>
                <td class="th-header font-bold" style="width: 25%;">SESUDAH</td>
            </tr>

            {{-- Baris Data Silinder --}}
            @foreach($data['checklist_items'] as $index => $item)
                <tr>
                    <td class="font-bold">{{ $item['cylinder'] ?? ($index + 1) }}</td>
                    <td class="check-val">
                        @if(($item['timing_before'] ?? '') === 'v')
                            <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>
                        @elseif(($item['timing_before'] ?? '') === 'X')
                            <span style="color: #c00;">X</span>
                        @else
                            {{ $item['timing_before'] ?? '-' }}
                        @endif
                    </td>
                    <td class="check-val">
                        @if(($item['timing_after'] ?? '') === 'v')
                            <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>
                        @elseif(($item['timing_after'] ?? '') === 'X')
                            <span style="color: #c00;">X</span>
                        @else
                            {{ $item['timing_after'] ?? '-' }}
                        @endif
                    </td>
                    {{-- Kolom Keterangan hanya dirender di baris pertama silinder dengan rowspan mencakup seluruh silinder + baris standar --}}
                    @if($loop->first)
                        <td rowspan="{{ $count + 1 }}" style="width: 40%; text-align: left; vertical-align: top; padding: 6px; font-size: 8.5px; line-height: 1.35;">
                            @if(count($notesArr) > 0)
                                {!! implode('<br>', array_map('e', $notesArr)) !!}
                            @else
                                &nbsp;
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach

            {{-- Baris Standar Yang di Izinkan --}}
            <tr>
                <td colspan="3" class="text-left font-bold" style="padding-left: 6px; font-size: 9px;">
                    Standar Yang di Izinkan : {{ $data['standard_allowed'] ?? 'Sesuai petunjuk pabrik / buku manual' }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- LEGENDA SIMBOL --}}
    <div class="legend-box">
        <div><strong>v</strong> : Normal</div>
        <div style="margin-top: 1px;"><strong>X</strong> : Tidak normal</div>
    </div>

    {{-- CATATAN TAMBAHAN --}}
    <div class="notes-box">
        <strong>catatan :</strong>
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
