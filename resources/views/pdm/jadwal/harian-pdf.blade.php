<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Kegiatan PdM & Matlev - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 6mm 6mm 6mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 7px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-bottom: 4px;
        }
        .header-table td {
            vertical-align: middle;
            border: 1px solid #000;
        }
        .logo-box {
            width: 120px;
            text-align: center;
            padding: 2px 4px;
        }
        .logo-box img {
            max-height: 38px;
            max-width: 110px;
        }
        .title-box {
            text-align: center;
            padding: 2px 4px;
        }
        .title-box .sub1 {
            font-size: 8.5px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .title-box .sub2 {
            font-size: 8px;
            font-weight: bold;
            line-height: 1.2;
            margin-top: 1px;
            text-transform: uppercase;
        }
        .title-box .sub3 {
            font-size: 7.5px;
            font-weight: bold;
            line-height: 1.2;
            margin-top: 1px;
            text-transform: uppercase;
        }
        .title-box .main-title {
            font-size: 9.5px;
            font-weight: bold;
            line-height: 1.25;
            margin-top: 2px;
            text-transform: uppercase;
        }
        .mkp-box {
            width: 110px;
            text-align: center;
            padding: 2px 4px;
        }
        .mkp-box .mkp-text {
            font-size: 14px;
            font-weight: bold;
            color: #005a9c;
            letter-spacing: 1px;
        }
        .mkp-box .mkp-sub {
            font-size: 5.5px;
            letter-spacing: 1.5px;
            color: #e30613;
            font-weight: bold;
            margin-top: 1px;
        }
        .meta-box {
            width: 170px;
            padding: 0;
            font-size: 7px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            border: none;
            border-bottom: 1px solid #000;
            padding: 2px 4px;
        }
        .meta-table tr:last-child td {
            border-bottom: none;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 7px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 2px 1px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table thead th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #000;
        }
        .th-green-accent {
            border-top: 2.5px solid #70ad47 !important;
        }
        .th-day-red {
            background-color: #ff0000 !important;
            color: #ffffff !important;
            font-weight: bold;
        }
        .td-day-red {
            background-color: #ff0000 !important;
            color: #ffffff !important;
            font-weight: bold;
        }
        .row-category {
            background-color: #fafafa;
            font-weight: bold;
        }
        .text-left {
            text-align: left !important;
            padding-left: 4px !important;
        }
        .sign-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
            font-size: 7.5px;
        }
        .sign-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 2px;
        }
        .sign-space {
            height: 48px;
        }
    </style>
</head>
<body>

    {{-- HEADER KOP DOKUMEN --}}
    <table class="header-table">
        <tr>
            <td class="logo-box">
                @if (!empty($logoLeft))
                    <img src="{{ $logoLeft }}" alt="PLN Logo" style="max-height: 40px; max-width: 120px;">
                @elseif (file_exists(public_path('logo/sidebar-logo.png')))
                    <img src="{{ public_path('logo/sidebar-logo.png') }}" alt="PLN Logo" style="max-height: 40px; max-width: 120px;">
                @else
                    <strong>PLN</strong><br><small>Nusantara Power</small>
                @endif
            </td>
            <td class="title-box">
                <div class="sub1">JASA PENDUKUNG TEKNIS - 11 SITE</div>
                <div class="sub2">PLN NP UP KENDARI - {{ $unit->serviceUnit ? strtoupper($unit->serviceUnit->name) : 'ULPLTD/PLTM/PLTG '.strtoupper($unit->name) }}</div>
                <div class="sub3">BAGIAN PdM &amp; MATLEV</div>
                <div class="main-title">JADWAL KEGIATAN PdM &amp; MATLEV</div>
            </td>
            <td class="mkp-box">
                @if (!empty($logoRight))
                    <img src="{{ $logoRight }}" alt="MKP Logo" style="max-height: 38px; max-width: 100px;">
                @elseif (file_exists(public_path('logo/mkp.jpg')))
                    <img src="{{ public_path('logo/mkp.jpg') }}" alt="MKP Logo" style="max-height: 38px; max-width: 100px;">
                @else
                    <div class="mkp-text">MKP</div>
                    <div class="mkp-sub">MITRA KARYA PRIMA</div>
                @endif
            </td>
            <td class="meta-box">
                <table class="meta-table">
                    <tr>
                        <td style="width: 48%;">Nomor Dokumen</td>
                        <td style="width: 4%;">:</td>
                        <td>{{ $meta?->doc_number ?: 'PLN-NP-UPKDR/JADWAL-PDM' }}</td>
                    </tr>
                    <tr>
                        <td>Revisi</td>
                        <td>:</td>
                        <td>{{ $meta?->revision ?: '00' }}</td>
                    </tr>
                    <tr>
                        <td>Tanggal Terbit</td>
                        <td>:</td>
                        <td>{{ $meta?->effective_date ?: '01 '.ucfirst($monthName).' '.$year }}</td>
                    </tr>
                    <tr>
                        <td>Halaman</td>
                        <td>:</td>
                        <td>{{ $meta?->page_number ?: '1 dari 1' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- TABEL JADWAL KEGIATAN --}}
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;" class="th-green-accent">No</th>
                <th rowspan="2" style="width: 230px;" class="th-green-accent text-left">Nama Peralatan</th>
                <th colspan="{{ $daysInMonth }}" class="th-green-accent" style="text-transform: uppercase;">
                    {{ $monthName }}
                </th>
                <th rowspan="2" style="width: 28px;" class="th-green-accent">TARGET</th>
                <th rowspan="2" style="width: 30px;" class="th-green-accent">REALISASI</th>
                <th rowspan="2" style="width: 32px;" class="th-green-accent">A. KINERJA</th>
                <th rowspan="2" style="width: 90px;" class="th-green-accent">Keterangan</th>
            </tr>
            <tr>
                @foreach ($days as $d)
                    <th style="width: 14px;" class="{{ $d['is_red'] ? 'th-day-red' : '' }}">
                        {{ $d['day'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                @php
                    $isCategory = !empty($r->is_category_header);
                    $jadwalArr = is_array($r->jadwal) ? $r->jadwal : [];
                    $target = (int) $r->target;
                    $realisasi = count($jadwalArr);
                    $kinerja = $target > 0 ? round(($realisasi / $target) * 100) : 0;
                @endphp
                <tr class="{{ $isCategory ? 'row-category' : '' }}">
                    <td style="font-weight: {{ $isCategory ? 'bold' : 'normal' }};">
                        {{ $r->no_urut }}
                    </td>
                    <td class="text-left" style="font-weight: {{ $isCategory ? 'bold' : 'normal' }}; {{ !$isCategory ? 'padding-left: 10px !important;' : '' }}">
                        {{ $r->kegiatan }}
                    </td>

                    @foreach ($days as $d)
                        @php
                            $isMarked = in_array($d['day'], $jadwalArr, true);
                            $isRed = $d['is_red'];
                        @endphp
                        <td class="{{ $isRed ? 'td-day-red' : '' }}" style="{{ $isMarked ? 'font-weight: bold;' : '' }}">
                            @if ($isMarked)
                                1
                            @else
                                &nbsp;
                            @endif
                        </td>
                    @endforeach

                    <td style="font-weight: {{ $isCategory ? 'bold' : 'normal' }};">{{ $target }}</td>
                    <td style="font-weight: {{ $isCategory ? 'bold' : 'normal' }};">{{ $realisasi }}</td>
                    <td style="font-weight: {{ $isCategory ? 'bold' : 'normal' }};">{{ $kinerja }}%</td>
                    <td class="text-left" style="font-size: 6.5px;">{{ $r->keterangan }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 6 + $daysInMonth }}" style="padding: 12px; text-align: center;">
                        Belum ada data jadwal kegiatan untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- TANDA TANGAN / PENGESAHAN --}}
    <table class="sign-table">
        <tr>
            <td>
                <div>Menyetujui,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $meta?->disetujui_jabatan ?: 'Team Leader PdM & Matlev' }}</div>
                <div class="sign-space">&nbsp;</div>
                <div style="font-weight: bold; text-decoration: underline;">{{ $meta?->disetujui_nama ?: 'TL PdM & MATLEV' }}</div>
            </td>
            <td>
                <div>Dibuat,</div>
                <div style="font-weight: bold; margin-top: 1px;">{{ $meta?->dibuat_jabatan ?: 'Junior Engineer PdM' }}</div>
                <div class="sign-space">&nbsp;</div>
                <div style="font-weight: bold; text-decoration: underline;">{{ $meta?->dibuat_nama ?: 'Pelaksana PdM' }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
