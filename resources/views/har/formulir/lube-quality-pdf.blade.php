<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Pengukuran Kualitas Pelumas - {{ $data['unit']->name ?? 'Unit' }}</title>
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
            line-height: {{ $data['line_spacing'] ?? '1.15' }};
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        /* Kop Surat & Dokumen Header */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        .kop-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            vertical-align: middle;
        }
        .doc-meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .doc-meta-table td {
            border: none;
            padding: 1.5px 2px;
            font-size: 7.5px;
        }

        /* Subheader Metadata Unit & Mesin */
        .sub-header-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 8px;
        }
        .sub-header-table td {
            border: none;
            padding: 2.5px 5px;
            vertical-align: top;
        }

        /* Parameter Table */
        .section-bar {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
            font-size: 8.5px;
            padding: 2.5px;
        }

        .param-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 7.5px;
            table-layout: fixed;
        }
        .param-table th,
        .param-table td {
            border: 1px solid #000;
            padding: 3px 2px;
            vertical-align: middle;
            text-align: center;
        }
        .param-header {
            font-weight: bold;
            font-size: 7.5px;
            background-color: #ffffff;
        }

        /* Highlight Section Header (Light Blue) */
        .header-blue {
            background-color: #8cd1ec;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            text-align: center;
            font-weight: bold;
            font-size: 8.5px;
            padding: 2.5px 4px;
            letter-spacing: 0.5px;
        }

        .box-section {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 6px;
            font-size: 8px;
            min-height: 22px;
        }

        /* Split 2-Columns Box */
        .split-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            table-layout: fixed;
        }
        .split-table td {
            border: 1px solid #000;
            vertical-align: top;
            padding: 0;
            width: 50%;
        }
        .split-header {
            background-color: #8cd1ec;
            text-align: center;
            font-weight: bold;
            font-size: 8.5px;
            padding: 2.5px 4px;
            border-bottom: 1px solid #000;
            letter-spacing: 0.5px;
        }
        .split-body {
            padding: 4px 6px;
            font-size: 8px;
            min-height: 125px;
        }

        /* Signatures Block */
        .sig-container {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            margin-top: 0;
        }
        .sig-container td {
            border: none;
            padding: 3px 5px;
        }
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .sig-table td {
            border: none;
            padding: 0 4px;
            vertical-align: top;
            text-align: center;
            font-size: 8px;
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
            <td style="width: 18%; text-align: center; padding: 2px;">
                @if(!empty($data['logo_pln']))
                    <img src="{{ $data['logo_pln'] }}" style="max-width: 105px; max-height: 38px;" alt="Logo PLN" />
                @else
                    <div style="font-size: 10px; font-weight: bold; color: #005f9e;">PLN</div>
                    <div style="font-size: 6.5px; color: #555;">Nusantara Power</div>
                @endif
            </td>

            <!-- Judul Tengah -->
            <td style="width: 52%; text-align: center; line-height: 1.25;">
                <div style="font-size: 8.5px; font-weight: bold;">PT. PLN NUSANTARA POWER UPDK KENDARI</div>
                <div style="font-size: 8px; font-weight: bold; margin-top: 1px;">INTEGRATED MANAGEMENT SYSTEM</div>
                <div style="font-size: 9.5px; font-weight: bold; color: #0070c0; margin-top: 2px; letter-spacing: 0.3px;">
                    FORMULIR PENGUKURAN KUALITAS PELUMAS
                </div>
            </td>

            <!-- Metadata Dokumen -->
            <td style="width: 30%; padding: 2px 4px; vertical-align: middle;">
                <table class="doc-meta-table">
                    <tr>
                        <td style="width: 42%; font-weight: bold;">Nomor Dokumen</td>
                        <td style="width: 4%;">:</td>
                        <td style="width: 54%; font-family: monospace;">{{ $data['document_number'] ?? 'FMKD-305-14.3.2.b-A3' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Revisi</td>
                        <td>:</td>
                        <td>{{ $data['revision'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Tanggal Terbit</td>
                        <td>:</td>
                        <td>{{ $data['effective_date'] ?? '31 - 07 - 2024' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Halaman</td>
                        <td>:</td>
                        <td>{{ $data['page_number'] ?? '' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- 2. SUBHEADER METADATA UNIT, MESIN & TITIK SAMPEL -->
    <table class="sub-header-table">
        <tr>
            <td style="width: 14%; font-weight: bold;">UNIT/SENTRAL</td>
            <td style="width: 2%;">:</td>
            <td style="width: 38%;">{{ $data['unit_sentral'] }}</td>
            <td style="width: 15%; font-weight: bold;">MESIN NO.</td>
            <td style="width: 2%;">:</td>
            <td style="width: 29%;">{{ $data['machine_number'] }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">MESIN</td>
            <td>:</td>
            <td>{{ $data['machine_name'] }}</td>
            <td style="font-weight: bold;">TITIK SAMPEL</td>
            <td>:</td>
            <td>{{ $data['sample_point'] }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">NO. SERI</td>
            <td>:</td>
            <td>{{ $data['serial_number'] ?: '-' }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <!-- 3. PARAMETER TITLE BAR -->
    <div class="section-bar">
        PARAMETER
    </div>

    <!-- 4. TABEL PARAMETER PENGUKURAN -->
    <table class="param-table">
        <thead>
            <tr class="param-header">
                <th rowspan="2" style="width: 9%;">TANGGAL</th>
                <th rowspan="2" style="width: 9%;">TBN<br><span style="font-weight: normal; font-size: 6.5px;">(mgKOH/g)</span></th>
                <th rowspan="2" style="width: 11%;">WATER CONTENT<br><span style="font-weight: normal; font-size: 6.5px;">(ppm)</span></th>
                <th colspan="2" style="width: 14%;">VISCOSITY (derajat)</th>
                <th rowspan="2" style="width: 9%;">AW Additive<br><span style="font-weight: normal; font-size: 6.5px;">(%)</span></th>
                <th rowspan="2" style="width: 7%;">Glycol<br><span style="font-weight: normal; font-size: 6.5px;">(%)</span></th>
                <th rowspan="2" style="width: 9%;">Nitration<br><span style="font-weight: normal; font-size: 6px;">(abs/0.1mm)</span></th>
                <th rowspan="2" style="width: 9%;">Oxidation<br><span style="font-weight: normal; font-size: 6px;">(abs/0.1mm)</span></th>
                <th rowspan="2" style="width: 7%;">Soot<br><span style="font-weight: normal; font-size: 6.5px;">(%wt)</span></th>
                <th rowspan="2" style="width: 8%;">Sulfation<br><span style="font-weight: normal; font-size: 6px;">(abs/1m)</span></th>
                <th rowspan="2" style="width: 8%;">KETERANGAN</th>
            </tr>
            <tr class="param-header">
                <th style="width: 7%;">40</th>
                <th style="width: 7%;">100</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['parameters'] as $p)
                <tr>
                    <td>{{ $p['tanggal'] ?? '-' }}</td>
                    <td>{{ $p['tbn'] ?? '-' }}</td>
                    <td>{{ $p['water_content'] ?? '-' }}</td>
                    <td>{{ $p['viscosity_40'] ?? '-' }}</td>
                    <td>{{ $p['viscosity_100'] ?? '-' }}</td>
                    <td>{{ $p['aw_additive'] ?? '-' }}</td>
                    <td>{{ $p['glycol'] ?? '-' }}</td>
                    <td>{{ $p['nitration'] ?? '-' }}</td>
                    <td>{{ $p['oxidation'] ?? '-' }}</td>
                    <td>{{ $p['soot'] ?? '-' }}</td>
                    <td>{{ $p['sulfation'] ?? '-' }}</td>
                    <td>{{ $p['keterangan'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" style="padding: 6px; font-style: italic; color: #777;">
                        Belum ada data parameter pengukuran kualitas pelumas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 5. STATUS SECTION -->
    <div class="header-blue">
        STATUS
    </div>
    <div class="box-section">
        {!! nl2br(e($data['status_text'] ?? '- No Alarm Signal')) !!}
    </div>

    <!-- 6. STANDARD SECTION -->
    <div class="header-blue">
        STANDARD
    </div>
    <div class="box-section">
        {!! nl2br(e($data['standard_text'] ?? '- Water Content : 2000 ppm')) !!}
    </div>

    <!-- 7. SPLIT 1: FOTO SAMPEL PELUMAS & ANALISA -->
    <table class="split-table">
        <tr>
            <!-- Kolom Kiri: Foto Sampel Pelumas -->
            <td>
                <div class="split-header">FOTO SAMPEL PELUMAS</div>
                <div class="split-body" style="text-align: center;">
                    @if(!empty($data['photo_base64']))
                        <div style="padding: 4px;">
                            <img src="{{ $data['photo_base64'] }}" style="max-height: 145px; max-width: 95%; border: 1px solid #aaa; border-radius: 2px;" alt="Foto Sampel" />
                            @if(!empty($data['photo_caption']))
                                <div style="font-size: 6.5px; color: #333; margin-top: 3px; line-height: 1.2;">
                                    {!! nl2br(e($data['photo_caption'])) !!}
                                </div>
                            @endif
                        </div>
                    @else
                        <div style="height: 135px; padding-top: 50px; color: #888; font-style: italic; font-size: 7.5px;">
                            [Foto Sampel Pelumas Belum Diunggah]
                        </div>
                    @endif
                </div>
            </td>

            <!-- Kolom Kanan: Analisa -->
            <td>
                <div class="split-header">ANALISA</div>
                <div class="split-body">
                    {!! nl2br(e($data['analisa_text'] ?? '')) !!}
                </div>
            </td>
        </tr>
    </table>

    <!-- 8. SPLIT 2: CBA & REKOMENDASI -->
    <table class="split-table">
        <tr>
            <!-- Kolom Kiri: CBA -->
            <td>
                <div class="split-header">CBA</div>
                <div class="split-body" style="min-height: 48px;">
                    {!! nl2br(e($data['cba_text'] ?? 'N/A')) !!}
                </div>
            </td>

            <!-- Kolom Kanan: Rekomendasi -->
            <td>
                <div class="split-header">REKOMENDASI</div>
                <div class="split-body" style="min-height: 48px;">
                    {!! nl2br(e($data['rekomendasi_text'] ?? 'N/A')) !!}
                </div>
            </td>
        </tr>
    </table>

    <!-- 9. TANDA TANGAN (SIGNATURES) -->
    <div class="sig-container">
        <!-- Tanggal dan Lokasi Pengesahan -->
        <div style="text-align: right; font-size: 8px; padding: 4px 15px 2px 0;">
            {{ $data['signature_location'] ?? 'Kendari' }}, {{ $data['signature_date'] ?? $data['test_date'] }}
        </div>

        <table class="sig-table">
            <tr>
                <!-- 1. Manager UL -->
                <td style="width: 36%;">
                    <div style="font-weight: bold; min-height: 18px;">
                        {{ $data['manager_ul_title'] }}
                    </div>
                    <div class="sig-space">
                        @if(!empty($data['manager_ul_signature']))
                            <img src="{{ $data['manager_ul_signature'] }}" alt="TTD Manager" />
                        @endif
                    </div>
                    <div style="font-weight: bold; text-decoration: underline;">
                        {{ $data['manager_ul_name'] }}
                    </div>
                </td>

                <!-- 2. Team Leader Har -->
                <td style="width: 32%;">
                    <div style="font-weight: bold; min-height: 18px;">
                        {{ $data['tl_har_title'] }}
                    </div>
                    <div class="sig-space">
                        @if(!empty($data['tl_har_signature']))
                            <img src="{{ $data['tl_har_signature'] }}" alt="TTD TL" />
                        @endif
                    </div>
                    <div style="font-weight: bold; text-decoration: underline;">
                        {{ $data['tl_har_name'] }}
                    </div>
                </td>

                <!-- 3. Staff Har -->
                <td style="width: 32%;">
                    <div style="font-weight: bold; min-height: 18px;">
                        {{ $data['staff_har_title'] }}
                    </div>
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
    </div>

@endif

</body>
</html>
