<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Pengukuran Defleksi Crankshaft - {{ $data['unit']->name ?? 'Unit' }}</title>
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
            font-size: 9.5px;
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
            font-size: 8.5px;
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
            font-size: 9px;
        }
        .specs-table td {
            padding: 3px 6px;
            border: none;
            border-bottom: 1px solid #000;
        }

        /* Diagram Table */
        .diagram-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        .diagram-table td {
            border: none;
            padding: 4px 8px;
            text-align: center;
            vertical-align: middle;
        }

        /* Note Bar */
        .note-bar {
            text-align: right;
            font-size: 8px;
            font-weight: bold;
            padding: 3px 2px 2px 2px;
        }

        /* Matrix Table */
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }
        .matrix-table td,
        .matrix-table th {
            border: 1px solid #000;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }
        .matrix-table .pos-col {
            font-weight: bold;
            font-size: 9px;
        }
        .matrix-table .notes-col {
            vertical-align: top;
            text-align: left;
            padding: 5px;
            font-size: 8.5px;
        }

        /* Inspection Row */
        .inspection-row td {
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #000;
            font-size: 8.5px;
        }

        /* Signatures */
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            text-align: center;
            font-size: 9px;
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
                    <img src="{{ $data['logo_pln'] }}" alt="PLN Nusantara Power" style="height: 30px; vertical-align: middle;">
                @else
                    <strong style="color: #0C7DBB;">PLN Nusantara Power</strong>
                @endif
                <div style="font-size: 8px; font-weight: bold; margin-top: 2px;">
                    {{ $data['ul_label'] }}
                </div>
            </td>
            <td style="width: 48%; text-align: center;">
                <div style="font-weight: bold; font-size: 11px; letter-spacing: 0.5px;">PT. PLN NUSANTARA POWER</div>
                <div style="font-weight: bold; font-size: 9.5px; margin-top: 2px;">UNIT PEMBANGKITAN KENDARI</div>
            </td>
            <td style="width: 8%; text-align: center;">
                @if(!empty($data['logo_k3']))
                    <img src="{{ $data['logo_k3'] }}" alt="K3" style="height: 34px; vertical-align: middle;">
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
        FORMULIR PENGUKURAN DEFLEKSI CRANKSHAFT
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

    {{-- DIAGRAM SKETSA & LINGKARAN POSISI --}}
    <table class="diagram-table">
        <tr>
            <td>
                @if(!empty($data['diagram_image']))
                    <img src="{{ $data['diagram_image'] }}" alt="Diagram Sketsa Defleksi Poros Engkol" style="width: 95%; max-height: 78px; display: block; margin: 0 auto;">
                @endif
            </td>
        </tr>
    </table>

    {{-- NOTE ATAS TABEL --}}
    <div class="note-bar">
        Note:Lihat Buku Petunjuk Pabrik Untuk Lebih Detail
    </div>

    {{-- TABEL MATRIKS PENGUKURAN DEFLEKSI CRANKSHAFT --}}
    @php
        $cylindersCount = (int) ($data['cylinders_count'] ?? 8);
        $measMap = collect($data['measurements'] ?? [])->keyBy('cylinder')->all();
        $posColWidth = 14;
        $notesColWidth = 32;
        $cylColWidth = (100 - $posColWidth - $notesColWidth) / $cylindersCount;
    @endphp
    <table class="matrix-table">
        <thead>
            <tr>
                <th style="width: {{ $posColWidth }}%; font-weight: bold; padding: 2px 4px;">
                    <div style="text-align: left; font-size: 8px;">Cyl</div>
                    <div style="border-top: 1px solid #000; margin: 1px 0;"></div>
                    <div style="text-align: right; font-size: 8px;">Posisi</div>
                </th>
                @for($i = 1; $i <= $cylindersCount; $i++)
                    <th style="width: {{ $cylColWidth }}%; font-weight: bold;">{{ $i }}</th>
                @endfor
                <th style="width: {{ $notesColWidth }}%; font-weight: bold;">KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            {{-- ROW A: includes KETERANGAN spanning across all 6 rows of tbody --}}
            <tr>
                <td class="pos-col">A</td>
                @for($i = 1; $i <= $cylindersCount; $i++)
                    <td>{{ $measMap[$i]['pos_a'] ?? '0' }}</td>
                @endfor
                <td rowspan="6" class="notes-col">
                    <div>
                        {!! !empty($data['cylinder_notes']) ? nl2br(e($data['cylinder_notes'])) : '&nbsp;' !!}
                    </div>
                </td>
            </tr>

            {{-- ROW B, C, D, E --}}
            @foreach(['B' => 'pos_b', 'C' => 'pos_c', 'D' => 'pos_d', 'E' => 'pos_e'] as $posLabel => $posKey)
                <tr>
                    <td class="pos-col">{{ $posLabel }}</td>
                    @for($i = 1; $i <= $cylindersCount; $i++)
                        <td>{{ $measMap[$i][$posKey] ?? '0' }}</td>
                    @endfor
                </tr>
            @endforeach

            {{-- ROW STANDAR YANG DIIZINKAN --}}
            <tr>
                <td style="font-size: 7.5px; text-align: left; font-weight: bold; padding: 2px 3px;">
                    Standar Yang di Izinkan :
                </td>
                <td style="font-size: 7px; text-align: left; padding: 1px 2px;">
                    <div>Min : {{ $data['standard_min'] }}</div>
                    <div>Max : {{ $data['standard_max'] }}</div>
                </td>
                @for($i = 2; $i <= $cylindersCount; $i++)
                    <td></td>
                @endfor
            </tr>

            {{-- ROW PEMERIKSAAN VISUAL --}}
            <tr class="inspection-row">
                <td colspan="{{ $cylindersCount + 2 }}">
                    <strong>Pemeriksaan visual :</strong> {{ $data['visual_inspection'] ?: '—' }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- TANDA TANGAN (3 KOLOM RESMI) --}}
    <table class="sign-table">
        <tr>
            <td>
                <div>Mengetahui,</div>
                <div style="font-size: 8.5px; margin-bottom: 4px;">{{ $data['manager_ul_title'] }}</div>
                <div style="height: 44px; display: flex; align-items: center; justify-content: center; margin: 3px 0;">
                    @if(!empty($data['manager_ul_signature']))
                        <img src="{{ $data['manager_ul_signature'] }}" alt="TTD Manager" style="max-height: 44px; max-width: 120px;">
                    @else
                        <div style="height: 44px;"></div>
                    @endif
                </div>
                <div class="sign-name">{{ $data['manager_ul_name'] }}</div>
            </td>
            <td>
                <div>Diperiksa,</div>
                <div style="font-size: 8.5px; margin-bottom: 4px;">{{ $data['tl_har_title'] }}</div>
                <div style="height: 44px; display: flex; align-items: center; justify-content: center; margin: 3px 0;">
                    @if(!empty($data['tl_har_signature']))
                        <img src="{{ $data['tl_har_signature'] }}" alt="TTD TL" style="max-height: 44px; max-width: 120px;">
                    @else
                        <div style="height: 44px;"></div>
                    @endif
                </div>
                <div class="sign-name">{{ $data['tl_har_name'] }}</div>
            </td>
            <td>
                <div>Pelaksana,</div>
                <div style="font-size: 8.5px; margin-bottom: 4px;">{{ $data['staff_har_title'] }}</div>
                <div style="height: 44px; display: flex; align-items: center; justify-content: center; margin: 3px 0;">
                    @if(!empty($data['staff_har_signature']))
                        <img src="{{ $data['staff_har_signature'] }}" alt="TTD Staff" style="max-height: 44px; max-width: 120px;">
                    @else
                        <div style="height: 44px;"></div>
                    @endif
                </div>
                <div class="sign-name">{{ $data['staff_har_name'] }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
