<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Stand Flow Meter {{ $fuel_name }} - {{ $unit->name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 10mm 12mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        .header-table td {
            vertical-align: top;
        }
        .ba-org {
            font-size: 10px;
            font-weight: bold;
            line-height: 1.3;
        }
        .ba-org small {
            font-size: 8px;
            font-weight: normal;
        }
        .ba-title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            text-decoration: underline;
            margin: 4px 0 2px 0;
        }
        .ba-sub {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            margin: 0 0 8px 0;
        }
        .meter-table {
            border: 1px solid #333;
            width: 100%;
        }
        .meter-table th, .meter-table td {
            border: 1px solid #666;
            padding: 2px 3px;
        }
        .meter-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
            font-size: 8px;
        }
        .meter-table td {
            font-size: 8px;
        }
        .meter-table td.c { text-align: center; }
        .meter-table td.r { text-align: right; }
        .bg-pemakaian { background-color: #fffbeb; font-weight: 600; }
        .bg-total { background-color: #fef3c7; font-weight: bold; }
        .bg-adm { background-color: #e0f2fe; font-weight: bold; }
        .bg-real { background-color: #f8fafc; font-weight: bold; }
        .bg-selisih { background-color: #fee2e2; font-weight: bold; }
        .selisih-neg { color: #b91c1c; font-weight: bold; }
        .sign-table {
            margin-top: 14px;
            width: 100%;
            font-size: 9px;
        }
        .sign-table td {
            vertical-align: top;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $fmt = fn ($v) => ((float) $v) == 0.0 ? '-' : number_format((float) $v, 2, ',', '.');
        $machines = $machine_parameters ?? [];
        $rows = $readings ?? [];
        $tots = $totals ?? [];
    @endphp

    <table class="header-table" style="margin-bottom: 6px;">
        <tr>
            <td style="width: 60px;">
                <img src="data:image/png;base64,{{ base64_encode((string) file_get_contents(public_path('logo/sidebar-logo.png'))) }}" alt="PLN Nusantara Power" style="height: 40px;">
            </td>
            <td>
                <div class="ba-org">
                    PT PLN (PERSERO) UIKL SULAWESI<br>
                    UPDK KENDARI - {{ strtoupper($unit->name) }}<br>
                    <small>SISTEM MANAJEMEN TERINTEGRASI</small>
                </div>
            </td>
            <td style="text-align: right; font-size: 8px; color: #555;">
                Periode: {{ $period_label }}<br>
                Bahan Bakar: {{ $fuel_name }}
            </td>
        </tr>
    </table>

    <hr style="border: none; border-top: 1px solid #000; margin: 2px 0 6px 0;">

    <div class="ba-title">STAND FLOW METER {{ strtoupper($fuel_name) }}</div>
    <div class="ba-sub">BULAN {{ strtoupper($period_label) }}</div>

    <table class="meter-table">
        <thead>
            <tr>
                <th rowspan="4" style="width: 25px;">TGL</th>
                @foreach ($machines as $m)
                    <th colspan="3" style="background: #e2e8f0;">{{ $m['name'] }}</th>
                @endforeach
                <th rowspan="4" style="width: 55px; background: #fef3c7;">TOTAL {{ strtoupper($unit->name) }}</th>
                <th rowspan="4" style="width: 50px; background: #e0f2fe;">ADM</th>
                <th rowspan="4" style="width: 50px; background: #f1f5f9;">Real</th>
                <th rowspan="4" style="width: 55px; background: #fee2e2;">SELISIH</th>
            </tr>
            <tr>
                @foreach ($machines as $m)
                    <th colspan="2" style="font-weight: normal; text-align: left; font-size: 7px;">STAND AWAL BLN LALU</th>
                    <th style="font-size: 7px; text-align: right;">{{ number_format((float)($m['stand_awal_bln_lalu'] ?? 0), 2, ',', '.') }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($machines as $m)
                    <th colspan="2" style="font-weight: normal; text-align: left; font-size: 7px;">FAKTOR KOREKSI</th>
                    <th style="font-size: 7px; text-align: right;">{{ number_format((float)($m['faktor_koreksi'] ?? 1), 7, ',', '.') }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($machines as $m)
                    <th style="font-size: 7px; width: 45px;">AWAL</th>
                    <th style="font-size: 7px; width: 45px;">AKHIR</th>
                    <th class="bg-pemakaian" style="font-size: 7px; width: 45px;">PEMAKAIAN</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                @php
                    $dayMachs = $r['machines'] ?? [];
                    $dailySum = 0.0;
                    foreach ($machines as $m) {
                        $dailySum += (float) ($dayMachs[$m['id']]['pemakaian'] ?? 0);
                    }
                    $adm = (float) ($r['adm'] ?? $dailySum);
                    $real = (float) ($r['real'] ?? 0);
                    $selisih = (float) ($r['selisih'] ?? ($real - $adm));
                @endphp
                <tr>
                    <td class="c" style="font-weight: bold;">{{ $r['tgl'] }}</td>
                    @foreach ($machines as $m)
                        @php
                            $mDay = $dayMachs[$m['id']] ?? ['awal' => 0, 'akhir' => 0, 'pemakaian' => 0];
                        @endphp
                        <td class="r">{{ $fmt($mDay['awal']) }}</td>
                        <td class="r">{{ $fmt($mDay['akhir']) }}</td>
                        <td class="r bg-pemakaian">{{ $fmt($mDay['pemakaian']) }}</td>
                    @endforeach
                    <td class="r bg-total">{{ $fmt($dailySum) }}</td>
                    <td class="r bg-adm">{{ $fmt($adm) }}</td>
                    <td class="r bg-real">{{ $fmt($real) }}</td>
                    <td class="r {{ $selisih < 0 ? 'selisih-neg' : '' }}">
                        {{ $selisih < 0 ? '('.$fmt(abs($selisih)).')' : ($selisih > 0 ? '+'.$fmt($selisih) : '-') }}
                    </td>
                </tr>
            @endforeach

            {{-- Row TOT --}}
            @php
                $totMachs = $tots['machines'] ?? [];
                $totUnitAll = 0.0;
                foreach ($machines as $m) {
                    $totUnitAll += (float) ($totMachs[$m['id']]['pemakaian'] ?? 0);
                }
                $totAdm = (float) ($tots['adm'] ?? $totUnitAll);
                $totReal = (float) ($tots['real'] ?? 0);
                $totSelisih = (float) ($tots['selisih'] ?? ($totReal - $totAdm));
            @endphp
            <tr style="background: #e2e8f0; font-weight: bold;">
                <td class="c">TOT</td>
                @foreach ($machines as $m)
                    @php
                        $mTot = $totMachs[$m['id']] ?? ['awal' => 0, 'akhir' => 0, 'pemakaian' => 0];
                    @endphp
                    <td class="r">{{ $fmt($mTot['awal']) }}</td>
                    <td class="r">{{ $fmt($mTot['akhir']) }}</td>
                    <td class="r bg-pemakaian">{{ $fmt($mTot['pemakaian']) }}</td>
                @endforeach
                <td class="r bg-total">{{ $fmt($totUnitAll) }}</td>
                <td class="r bg-adm">{{ $fmt($totAdm) }}</td>
                <td class="r bg-real">{{ $fmt($totReal) }}</td>
                <td class="r {{ $totSelisih < 0 ? 'selisih-neg' : '' }}">
                    {{ $totSelisih < 0 ? '('.$fmt(abs($totSelisih)).')' : ($totSelisih > 0 ? '+'.$fmt($totSelisih) : '0,00') }}
                </td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 6px; font-size: 7.5px; color: #444;">
        <strong>Keterangan Rumus:</strong>
        (1) Stand Awal = =IF(Akhir=0; 0; Akhir Kemarin) |
        (2) Pemakaian = (Akhir - Awal) &times; Faktor Koreksi &times; Faktor Kali |
        (3) ADM = &Sigma; Pemakaian Seluruh Mesin |
        (4) Selisih = Real - ADM
        @if (!empty($catatan))
            <br><strong>Catatan:</strong> {{ $catatan }}
        @endif
    </div>

    <table class="sign-table">
        <tr>
            <td style="width: 50%;">
                Mengetahui,<br>
                <strong>Team Leader Operasi</strong>
                <div style="height: 40px;"></div>
                ( .................................................. )
            </td>
            <td style="width: 50%;">
                Kendari, {{ date('d') }} {{ $period_label }}<br>
                Diperiksa oleh,<br>
                <strong>Operator / Staf Pengusahaan</strong>
                <div style="height: 40px;"></div>
                ( .................................................. )
            </td>
        </tr>
    </table>
</body>
</html>
