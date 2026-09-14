<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Checklist Prelube Test - {{ $data['unit']->name ?? 'Unit' }}</title>
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
        .table-bordered {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        .table-bordered th,
        .table-bordered td {
            border: 1px solid #000;
            padding: 4px 5px;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        /* Header kop */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-bottom: 0px;
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

        /* Document Title */
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

        /* Checklist table */
        .checklist-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-top: -1px;
            font-size: 9.5px;
        }
        .checklist-table th {
            border: 1px solid #000;
            background: #fff;
            padding: 4px 2px;
            font-weight: bold;
            text-align: center;
            font-size: 9px;
        }
        .checklist-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
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
            font-weight: bold;
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
                <div style="font-weight: bold; font-size: 11px; letter-spacing: 0.5px;">PT. PLN NUSANTARA POWER</div>
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
        FORMULIR CHECKLIST PRELUBE TEST
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

    {{-- TABEL CHECKLIST PRELUBE TEST --}}
    <table class="checklist-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 32px;">NO</th>
                <th colspan="4" style="letter-spacing: 0.5px;">KONDISI PELUMASAN</th>
                <th rowspan="2" style="width: 32%;">KETERANGAN</th>
            </tr>
            <tr>
                <th style="width: 15%;">CAM SHAFT</th>
                <th style="width: 15%;">CRANK PIN BEARING<br><small>(CONROD)</small></th>
                <th style="width: 15%;">CRANK PIN BEARING<br><small>(PISTON)</small></th>
                <th style="width: 15%;">ROCKER ARM</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['checklist_items'] as $item)
                <tr>
                    <td class="text-center font-bold">{{ $item['cylinder'] ?? $loop->iteration }}</td>
                    <td class="check-val">
                        @if(($item['camshaft'] ?? '') === 'v')
                            <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>
                        @elseif(($item['camshaft'] ?? '') === 'X')
                            <span style="color: #c00;">X</span>
                        @else
                            {{ $item['camshaft'] ?? '-' }}
                        @endif
                    </td>
                    <td class="check-val">
                        @if(($item['conrod'] ?? '') === 'v')
                            <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>
                        @elseif(($item['conrod'] ?? '') === 'X')
                            <span style="color: #c00;">X</span>
                        @else
                            {{ $item['conrod'] ?? '-' }}
                        @endif
                    </td>
                    <td class="check-val">
                        @if(($item['piston'] ?? '') === 'v')
                            <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>
                        @elseif(($item['piston'] ?? '') === 'X')
                            <span style="color: #c00;">X</span>
                        @else
                            {{ $item['piston'] ?? '-' }}
                        @endif
                    </td>
                    <td class="check-val">
                        @if(($item['rocker_arm'] ?? '') === 'v')
                            <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>
                        @elseif(($item['rocker_arm'] ?? '') === 'X')
                            <span style="color: #c00;">X</span>
                        @else
                            {{ $item['rocker_arm'] ?? '-' }}
                        @endif
                    </td>
                    <td style="font-size: 9px;">
                        {{ $item['notes'] ?? '' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 10px;">Tidak ada data silinder.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- KETERANGAN / LEGENDA --}}
    <div class="legend-box">
        KET : &radic; / v = KONDISI ADA / KELUAR OLI &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; X = KONDISI TIDAK ADA / TIDAK KELUAR OLI
    </div>

    {{-- CATATAN TAMBAHAN --}}
    <div class="notes-box">
        <strong>Catatan :</strong>
        <div style="margin-top: 3px; min-height: 28px; line-height: 1.4;">
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
