<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Pengukuran Vibrasi - {{ $data['unit']->name ?? 'Unit' }}</title>
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
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-muted { color: #555; }

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

        /* Title Banner with Document Number */
        .title-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        .title-table td {
            border: 1px solid #000;
            padding: 2.5px 5px;
            vertical-align: middle;
        }

        /* Specs Table */
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 7.5px;
        }
        .specs-table td {
            border: none;
            padding: 2px 5px;
            vertical-align: middle;
        }

        /* Diagram Box */
        .diagram-box {
            width: 100%;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 2px;
            text-align: center;
        }

        /* Grid Measurements Table */
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 7.5px;
            table-layout: fixed;
        }
        .grid-table th,
        .grid-table td {
            border: 1px solid #000;
            padding: 2px 2px;
            vertical-align: middle;
        }
        .header-row th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 7.5px;
            text-align: center;
        }
        .header-row.sub th {
            font-size: 7px;
            padding: 1.5px 1px;
        }

        /* Summary Box */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 7.5px;
        }
        .summary-table td {
            border: none;
            padding: 2px 5px;
            vertical-align: top;
        }

        /* Signatures Block */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            table-layout: fixed;
        }
        .sig-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
            text-align: center;
            font-size: 7.5px;
        }
        .sig-space {
            height: 48px;
            vertical-align: middle;
            text-align: center;
        }
        .sig-space img {
            max-height: 44px;
            max-width: 120px;
        }
    </style>
</head>
<body>

@if(($data['format'] ?? 'form') === 'html' && !empty($data['content_html']))
    <div class="custom-html-content">
        {!! $data['content_html'] !!}
    </div>
@else

    <!-- 1. KOP SURAT / DOKUMEN HEADER -->
    <table class="kop-table">
        <tr>
            <!-- Logo PLN -->
            <td style="width: 22%; text-align: left; padding: 2px 5px;">
                @if(!empty($data['logo_pln']))
                    <img src="{{ $data['logo_pln'] }}" style="max-width: 110px; max-height: 36px;" alt="Logo PLN" />
                @else
                    <div style="font-size: 11px; font-weight: bold; color: #005f9e;">PLN</div>
                    <div style="font-size: 6.5px; color: #555;">Nusantara Power</div>
                @endif
                <div style="font-size: 7px; font-weight: bold; color: #0070c0; margin-top: 1px;">
                    {{ $data['ul_label'] ?? 'ULPLTD WUA-WUA' }}
                </div>
            </td>

            <!-- Judul Tengah -->
            <td style="width: 66%; text-align: center; line-height: 1.25;">
                <div style="font-size: 9.5px; font-weight: bold; letter-spacing: 0.5px;">PT. PLN NUSANTARA POWER</div>
                <div style="font-size: 8.5px; font-weight: bold; letter-spacing: 0.3px; margin-top: 1px;">UNIT PEMBANGKITAN KENDARI</div>
            </td>

            <!-- Logo K3 -->
            <td style="width: 12%; text-align: center; padding: 2px;">
                @if(!empty($data['logo_k3']))
                    <img src="{{ $data['logo_k3'] }}" style="max-width: 38px; max-height: 38px;" alt="Logo K3" />
                @endif
            </td>
        </tr>
    </table>

    <!-- 2. TITLE BANNER & NO DOKUMEN -->
    <table class="title-table">
        <tr>
            <!-- Judul Formulir -->
            <td style="width: 70%; text-align: center; font-size: 9.5px; font-weight: bold; letter-spacing: 0.5px;">
                FORMULIR PENGUKURAN VIBRASI
            </td>

            <!-- Metadata Dokumen -->
            <td style="width: 30%; padding: 0;">
                <table style="width: 100%; border-collapse: collapse; font-size: 7.5px;">
                    <tr>
                        <td style="width: 42%; font-weight: bold; border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 1.5px 4px;">No. Dokumen</td>
                        <td style="width: 58%; font-family: monospace; border-bottom: 1px solid #000; padding: 1.5px 4px;">: {{ $data['document_number'] ?? 'FMKD-314-10.3.3.a-B10' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 1.5px 4px;">Revisi</td>
                        <td style="border-bottom: 1px solid #000; padding: 1.5px 4px;">: {{ $data['revision'] ?? '03' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; border-right: 1px solid #000; padding: 1.5px 4px;">Tanggal</td>
                        <td style="padding: 1.5px 4px;">: {{ $data['effective_date'] ?? '31 Juli 2024' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- 3. TECHNICAL SPECIFICATIONS -->
    <table class="specs-table">
        <tr>
            <td style="width: 8%; font-weight: bold;">Merek</td>
            <td style="width: 2%;">:</td>
            <td style="width: 15%;">{{ $data['brand'] }}</td>

            <td style="width: 8%; font-weight: bold;">Type</td>
            <td style="width: 2%;">:</td>
            <td style="width: 15%;">{{ $data['model_type'] }}</td>

            <td style="width: 14%; font-weight: bold;">Daya Terpasang</td>
            <td style="width: 2%;">:</td>
            <td style="width: 12%;">{{ $data['installed_power'] }}</td>

            <td style="width: 12%; font-weight: bold;">Daya mampu</td>
            <td style="width: 2%;">:</td>
            <td style="width: 13%;">{{ $data['capable_power'] }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">No.Seri</td>
            <td>:</td>
            <td>{{ $data['serial_number'] ?: '-' }}</td>

            <td style="font-weight: bold;">Mesin No</td>
            <td>:</td>
            <td>{{ $data['machine_number'] }}</td>

            <td style="font-weight: bold;">RPM</td>
            <td>:</td>
            <td>{{ $data['rpm'] }}</td>

            <td style="font-weight: bold;">TGL</td>
            <td>:</td>
            <td>{{ $data['test_date'] }}</td>
        </tr>
    </table>

    <!-- 4. DIAGRAM MESIN & GENERATOR -->
    <div class="diagram-box">
        @if(!empty($data['diagram_image']))
            <img src="{{ $data['diagram_image'] }}" style="width: 96%; max-height: 85px; display: block; margin: 0 auto;" alt="Diagram Penempatan Sensor Vibrasi" />
        @endif
    </div>

    <!-- 5. TABEL HASIL PENGUKURAN VIBRASI -->
    <table class="grid-table">
        <thead>
            <tr class="header-row">
                <th rowspan="3" style="width: 4%;">POS.</th>
                <th rowspan="3" style="width: 25%;">TITIK PENGUKURAN</th>
                <th colspan="8" style="width: 53%;">HASIL<br><span style="font-weight: normal; font-size: 6.5px;">(mm/s)</span></th>
                <th rowspan="3" style="width: 18%;">KETERANGAN</th>
            </tr>
            <tr class="header-row">
                <th colspan="4">VERTIKAL</th>
                <th colspan="4">HORIZONTAL</th>
            </tr>
            <tr class="header-row sub">
                <th style="width: 8%;">Max</th>
                <th style="width: 2%;">/</th>
                <th style="width: 8%;">Min</th>
                <th style="width: 8.5%;">Avg</th>
                <th style="width: 8%;">Max</th>
                <th style="width: 2%;">/</th>
                <th style="width: 8%;">Min</th>
                <th style="width: 8.5%;">Avg</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['measurements'] as $m)
                <tr>
                    <td class="text-center">{{ $m['pos'] }}</td>
                    <td class="text-center font-bold">{{ $m['point'] }}</td>

                    <!-- Vertikal: Max / Min Avg -->
                    <td class="text-center">{{ $m['v_max'] ?: '' }}</td>
                    <td class="text-center text-muted">/</td>
                    <td class="text-center">{{ $m['v_min'] ?: '' }}</td>
                    <td class="text-center">{{ $m['v_avg'] ?: '' }}</td>

                    <!-- Horizontal: Max / Min Avg -->
                    <td class="text-center">{{ $m['h_max'] ?: '' }}</td>
                    <td class="text-center text-muted">/</td>
                    <td class="text-center">{{ $m['h_min'] ?: '' }}</td>
                    <td class="text-center">{{ $m['h_avg'] ?: '' }}</td>

                    <td class="text-left" style="padding-left: 4px;">{{ $m['notes'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- 6. STANDAR, MAX & KESIMPULAN -->
    <table class="summary-table">
        <tr>
            <td style="width: 14%; font-weight: bold;">STANDAR</td>
            <td style="width: 2%;">:</td>
            <td style="width: 84%;">{{ $data['standard_text'] }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">MAX.</td>
            <td>:</td>
            <td>{{ $data['max_text'] }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">KESIMPULAN:</td>
            <td></td>
            <td>{{ $data['conclusion_text'] }}</td>
        </tr>
    </table>

    <!-- 7. TANDA TANGAN (SIGNATURES) -->
    <table class="sig-table">
        <tr>
            <!-- 1. Mengetahui: Manager UL -->
            <td style="width: 34%;">
                <div style="font-weight: bold; min-height: 14px;">Mengetahui,</div>
                <div style="font-size: 7px; color: #333; min-height: 14px;">{{ $data['manager_ul_title'] }}</div>
                <div class="sig-space">
                    @if(!empty($data['manager_ul_signature']))
                        <img src="{{ $data['manager_ul_signature'] }}" alt="TTD Manager" />
                    @endif
                </div>
                <div style="font-weight: bold; text-decoration: underline;">
                    {{ $data['manager_ul_name'] }}
                </div>
            </td>

            <!-- 2. Diperiksa: TL Har -->
            <td style="width: 33%;">
                <div style="font-weight: bold; min-height: 14px;">Diperiksa,</div>
                <div style="font-size: 7px; color: #333; min-height: 14px;">{{ $data['tl_har_title'] }}</div>
                <div class="sig-space">
                    @if(!empty($data['tl_har_signature']))
                        <img src="{{ $data['tl_har_signature'] }}" alt="TTD TL" />
                    @endif
                </div>
                <div style="font-weight: bold; text-decoration: underline;">
                    {{ $data['tl_har_name'] }}
                </div>
            </td>

            <!-- 3. Dibuat: Staff Har -->
            <td style="width: 33%;">
                <div style="font-weight: bold; min-height: 14px;">Dibuat,</div>
                <div style="font-size: 7px; color: #333; min-height: 14px;">{{ $data['staff_har_title'] }}</div>
                <div class="sig-space">
                    @if(!empty($data['staff_har_signature']))
                        <img src="{{ $data['staff_har_signature'] }}" alt="TTD Staff" />
                    @endif
                </div>
                <div style="font-weight: bold; text-decoration: underline;">
                    {{ $data['staff_har_name'] }}
                </div>
            </td>
        </tr>
    </table>

@endif

</body>
</html>
