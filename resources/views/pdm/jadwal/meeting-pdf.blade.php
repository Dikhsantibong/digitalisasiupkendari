<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Meeting PdM Pembangkit - {{ $unit->name }} - {{ $month_name }} {{ $year }}</title>
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
            margin-bottom: 6px;
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

        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-top: 6px;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #000;
            padding: 2.5px 2px;
            text-align: center;
            vertical-align: middle;
        }
        .th-orange {
            background-color: #ed7d31 !important;
            color: #ffffff !important;
            font-weight: bold;
            font-size: 7px;
        }
        .th-day {
            font-size: 6.5px;
            font-weight: bold;
        }
        .td-day-red {
            background-color: #fee2e2 !important;
        }
        .text-left {
            text-align: left !important;
        }
        .text-center {
            text-align: center !important;
        }
        .text-red {
            color: #dc2626 !important;
            font-weight: bold;
        }
        .fw-bold {
            font-weight: bold;
        }

        .footer-container {
            width: 100%;
            margin-top: 12px;
        }
        .notes-table {
            width: 48%;
            float: left;
            border: 1px dashed #666;
            padding: 5px;
            font-size: 7px;
            line-height: 1.35;
        }
        .sign-table {
            width: 50%;
            float: right;
            border-collapse: collapse;
        }
        .sign-table td {
            text-align: center;
            vertical-align: top;
            padding: 2px 8px;
            font-size: 8px;
            border: none;
        }
        .sign-space {
            height: 44px;
            margin: 4px 0;
            display: block;
        }
        .sign-img {
            max-height: 44px;
            max-width: 120px;
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
                <div class="sub1">JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE -KIT</div>
                <div class="sub2">LAPORAN PROJECT SENTRAL PLTD/PLTG/PLTM {{ strtoupper($unit->name) }}</div>
                <div class="sub3">JADWAL MEETING PdM Pembangkit</div>
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

    {{-- DATA TABLE JADWAL MEETING PDM --}}
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" class="th-orange" style="width: 140px;">URAIAN</th>
                <th rowspan="2" class="th-orange" style="width: 70px;">
                    {{ strtoupper($month_name) }} {{ $year }}
                </th>
                @foreach ($days as $d)
                    <th class="th-orange th-day" style="width: 15px;">
                        {{ $d['dow'] }}
                    </th>
                @endforeach
                <th rowspan="2" class="th-orange" style="width: 32px;">RENCANA</th>
                <th rowspan="2" class="th-orange" style="width: 30px;">TARGET</th>
                <th rowspan="2" class="th-orange" style="width: 32px;">REALISASI</th>
                <th rowspan="2" class="th-orange" style="width: 36px;">A. KINERJA</th>
            </tr>
            <tr>
                @foreach ($days as $d)
                    <th class="th-orange th-day" style="width: 15px;">
                        {{ $d['day'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                @php
                    $rencanaArr = is_array($r->rencana) ? $r->rencana : [];
                    $realisasiArr = is_array($r->realisasi) ? $r->realisasi : [];
                    $totalRencana = count($rencanaArr);
                    $totalRealisasi = count($realisasiArr);
                    $target = (int) ($r->target ?? 4);
                    $kinerja = $target > 0 ? round(($totalRealisasi / $target) * 100) : 0;
                @endphp

                {{-- SUB-ROW 1: RENCANA --}}
                <tr>
                    {{-- URAIAN (Rowspan 2) --}}
                    <td rowspan="2" class="text-left fw-bold" style="padding-left: 6px;">
                        {{ $r->uraian }}
                    </td>

                    {{-- Label RENCANA --}}
                    <td class="text-center fw-bold" style="font-size: 7px; background-color: #fafafa;">
                        RENCANA
                    </td>

                    {{-- Days for RENCANA --}}
                    @foreach ($days as $d)
                        @php
                            $isMarked = in_array($d['day'], $rencanaArr, true);
                        @endphp
                        <td class="{{ $d['is_red'] ? 'td-day-red' : '' }}" style="{{ $isMarked ? 'font-weight: bold; background-color: #fef3c7;' : '' }}">
                            {{ $isMarked ? '1' : '' }}
                        </td>
                    @endforeach

                    {{-- Summary Columns (Rowspan 2) --}}
                    <td rowspan="2" class="text-center fw-bold">
                        {{ $totalRencana }}
                    </td>
                    <td rowspan="2" class="text-center text-red">
                        {{ $target }}
                    </td>
                    <td rowspan="2" class="text-center fw-bold">
                        {{ $totalRealisasi }}
                    </td>
                    <td rowspan="2" class="text-center fw-bold">
                        {{ $kinerja }}%
                    </td>
                </tr>

                {{-- SUB-ROW 2: REALISASI --}}
                <tr>
                    {{-- Label REALISASI --}}
                    <td class="text-center fw-bold" style="font-size: 7px; background-color: #fafafa;">
                        REALISASI
                    </td>

                    {{-- Days for REALISASI --}}
                    @foreach ($days as $d)
                        @php
                            $isMarked = in_array($d['day'], $realisasiArr, true);
                        @endphp
                        <td class="{{ $d['is_red'] ? 'td-day-red' : '' }}" style="{{ $isMarked ? 'font-weight: bold; background-color: #d1fae5;' : '' }}">
                            {{ $isMarked ? '1' : '' }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 2 + $daysInMonth + 4 }}" style="padding: 14px; text-align: center;">
                        Belum ada data jadwal meeting untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- FOOTER: CATATAN & LEMBAR PENGESAHAN --}}
    <div class="footer-container">
        {{-- SISI KIRI: CATATAN --}}
        <div class="notes-table">
            <strong style="text-decoration: underline;">Catatan :</strong><br>
            {!! nl2br(e($catatan)) !!}
        </div>

        {{-- SISI KANAN: TANDA TANGAN --}}
        <table class="sign-table">
            <tr>
                <td colspan="2" style="text-align: right; padding-bottom: 4px; font-size: 7.5px;">
                    {{ $unit->name }}, 1 {{ strtoupper($month_name) }} {{ $year }}
                </td>
            </tr>
            <tr>
                {{-- PROJECT LEADER --}}
                <td style="width: 50%;">
                    <div>{{ $meta?->disetujui_jabatan ?: 'Project Leader' }}</div>
                    <div class="sign-space">
                        @if (!empty($signatureDisetujui))
                            <img src="{{ $signatureDisetujui }}" class="sign-img" alt="TTD Project Leader">
                        @else
                            &nbsp;
                        @endif
                    </div>
                    <div style="font-weight: bold; text-decoration: underline;">
                        {{ $meta?->disetujui_nama ?: '..........................................' }}
                    </div>
                </td>

                {{-- KOORDINATOR --}}
                <td style="width: 50%;">
                    <div>{{ $meta?->dibuat_jabatan ?: 'Koordinator Pemeliharaan' }}</div>
                    <div class="sign-space">
                        @if (!empty($signatureDibuat))
                            <img src="{{ $signatureDibuat }}" class="sign-img" alt="TTD Koordinator">
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
    </div>

</body>
</html>
