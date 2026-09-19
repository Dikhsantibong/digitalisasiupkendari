<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Piket Patrol Check PdM KIT - {{ $unit->name }} - {{ $month_name }} {{ $year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 8mm 8mm 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 7.5px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .logo-box-left {
            width: 140px;
            text-align: left;
        }
        .logo-box-left img {
            max-height: 42px;
            max-width: 130px;
        }
        .logo-box-right {
            width: 140px;
            text-align: right;
        }
        .logo-box-right img {
            max-height: 42px;
            max-width: 130px;
        }
        .title-box {
            text-align: center;
            padding: 0 8px;
        }
        .title-box .sub1 {
            font-size: 9.5px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .title-box .sub2 {
            font-size: 8.5px;
            font-weight: bold;
            line-height: 1.25;
            margin-top: 1px;
            text-transform: uppercase;
        }
        .title-box .sub3 {
            font-size: 9px;
            font-weight: bold;
            line-height: 1.25;
            margin-top: 2px;
            text-transform: uppercase;
        }
        .title-box .sub4 {
            font-size: 8px;
            font-weight: bold;
            line-height: 1.25;
            margin-top: 1px;
            text-transform: uppercase;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-top: 6px;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #000;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table thead th {
            font-size: 7px;
            font-weight: bold;
            background-color: #f8fafc;
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
        .row-category td {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 8px;
            text-align: left;
            padding-left: 6px;
        }
        .row-category td.cat-no {
            text-align: center;
            padding-left: 0;
        }
        .text-left {
            text-align: left !important;
        }
        .text-center {
            text-align: center !important;
        }
        .fw-bold {
            font-weight: bold;
        }

        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        .sign-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 4px 10px;
            font-size: 8px;
            border: none;
        }
        .sign-space {
            height: 48px;
            margin: 4px 0;
            display: block;
        }
        .sign-img {
            max-height: 48px;
            max-width: 130px;
            margin: 0 auto;
            display: block;
        }
    </style>
</head>
<body>

    @php
        $daysInMonth = count($days);
    @endphp

    {{-- KOP SURAT / HEADER RESMI --}}
    <table class="header-table">
        <tr>
            <td class="logo-box-left">
                @if (!empty($logoLeft))
                    <img src="{{ $logoLeft }}" alt="PLN Nusantara Power">
                @else
                    <div style="font-size: 13px; font-weight: bold; color: #008080;">PLN</div>
                    <div style="font-size: 8px; font-weight: bold; color: #005a9c;">Nusantara Power</div>
                @endif
            </td>
            <td class="title-box">
                <div class="sub1">JASA PENDUKUNG TEKNIS 11 SITE</div>
                <div class="sub2">PLN NP UP KENDARI - ULPLTD/PLTG/PLTM {{ strtoupper($unit->name) }}</div>
                <div class="sub3">JADWAL PIKET PATROL CHEK PdM KIT BULAN {{ strtoupper($month_name) }} {{ $year }}</div>
                <div class="sub4">ULPLTD/PLTG/PLTM {{ strtoupper($unit->name) }}</div>
            </td>
            <td class="logo-box-right">
                @if (!empty($logoRight))
                    <img src="{{ $logoRight }}" alt="MKP Mitra Karya Prima">
                @else
                    <div style="font-size: 13px; font-weight: bold; color: #005a9c;">MKP</div>
                    <div style="font-size: 6.5px; font-weight: bold; color: #e30613;">MITRA KARYA PRIMA</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- DATA TABLE JADWAL PIKET PATROL CHECK --}}
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;">No.</th>
                <th rowspan="2" style="width: 150px;" class="text-left">&nbsp;Nama</th>
                <th rowspan="2" style="width: 75px;">No. Hp</th>
                <th colspan="{{ $daysInMonth }}" style="text-transform: uppercase;">
                    {{ $month_name }} {{ $year }}
                </th>
                <th rowspan="2" style="width: 32px;">TARGET</th>
                <th rowspan="2" style="width: 34px;">REALISASI</th>
                <th rowspan="2" style="width: 36px;">A. KINERJA</th>
            </tr>
            <tr>
                @foreach ($days as $d)
                    <th style="width: 16px;" class="{{ $d['is_red'] ? 'th-day-red' : '' }}">
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

                @if ($isCategory)
                    <tr class="row-category">
                        <td class="cat-no">{{ $r->no_urut }}</td>
                        <td colspan="{{ 2 + $daysInMonth + 3 }}" class="text-left fw-bold">
                            {{ $r->nama }}
                        </td>
                    </tr>
                @else
                    <tr>
                        <td class="text-center">{{ $r->no_urut }}</td>
                        <td class="text-left fw-bold" style="padding-left: 6px;">
                            {{ $r->nama ?: '-' }}
                        </td>
                        <td class="text-center" style="font-size: 7px;">
                            {{ $r->no_hp ?: '-' }}
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

                        <td class="text-center fw-bold">{{ $target }}</td>
                        <td class="text-center fw-bold">{{ $realisasi }}</td>
                        <td class="text-center fw-bold">{{ $kinerja }}%</td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="{{ 3 + $daysInMonth + 3 }}" style="padding: 14px; text-align: center;">
                        Belum ada data jadwal piket patrol check untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- LEMBAR PENGESAHAN (3 KOLOM TANDA TANGAN DARI EMPLOYEE) --}}
    <table class="sign-table">
        <tr>
            {{-- KOLOM KIRI: MENGETAHUI --}}
            <td>
                <div>Mengetahui,</div>
                <div style="font-weight: bold; margin-top: 1px;">
                    {{ $meta?->mengetahui_jabatan ?: 'Team Leader Pemeliharaan' }}
                </div>
                <div class="sign-space">
                    @if (!empty($signatureMengetahui))
                        <img src="{{ $signatureMengetahui }}" class="sign-img" alt="TTD Mengetahui">
                    @else
                        &nbsp;
                    @endif
                </div>
                <div style="font-weight: bold; text-decoration: underline;">
                    {{ $meta?->mengetahui_nama ?: '..........................................' }}
                </div>
            </td>

            {{-- KOLOM TENGAH: MENYETUJUI --}}
            <td>
                <div>Menyetujui,</div>
                <div style="font-weight: bold; margin-top: 1px;">
                    {{ $meta?->disetujui_jabatan ?: 'Project Leader' }}
                </div>
                <div class="sign-space">
                    @if (!empty($signatureDisetujui))
                        <img src="{{ $signatureDisetujui }}" class="sign-img" alt="TTD Menyetujui">
                    @else
                        &nbsp;
                    @endif
                </div>
                <div style="font-weight: bold; text-decoration: underline;">
                    {{ $meta?->disetujui_nama ?: '..........................................' }}
                </div>
            </td>

            {{-- KOLOM KANAN: DIBUAT OLEH --}}
            <td>
                <div>Dibuat oleh,</div>
                <div style="font-weight: bold; margin-top: 1px;">
                    {{ $meta?->dibuat_jabatan ?: 'Koordinator Pemeliharaan' }}
                </div>
                <div class="sign-space">
                    @if (!empty($signatureDibuat))
                        <img src="{{ $signatureDibuat }}" class="sign-img" alt="TTD Dibuat">
                    @else
                        &nbsp;
                    @endif
                </div>
                <div style="font-weight: bold; text-decoration: underline;">
                    {{ $meta?->dibuat_nama ?: '..........................................' }}
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
