<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal On Call K3L - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page { size: A4 landscape; margin: 6mm 8mm; }
        * { box-sizing: border-box; }
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
            margin-bottom: 6px;
        }
        .header-table td {
            vertical-align: middle;
            border: 1px solid #000;
        }
        .logo-box {
            width: 120px;
            text-align: center;
            padding: 3px;
        }
        .logo-box img {
            max-height: 38px;
            max-width: 110px;
        }
        .title-box {
            text-align: center;
            padding: 3px 6px;
        }
        .title-box h1 {
            margin: 0;
            font-size: 9.5px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .title-box h2 {
            margin: 1.5px 0 0 0;
            font-size: 8.5px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .title-box h3 {
            margin: 1.5px 0 0 0;
            font-size: 8.5px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 6.8px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 2px 1px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table thead th {
            background-color: #ffffff;
            font-weight: bold;
            color: #000;
        }
        .th-weekend {
            background-color: #fef08a !important;
        }
        .th-holiday {
            background-color: #fecaca !important;
            color: #b91c1c !important;
        }
        .td-weekend {
            background-color: #fef9c3 !important;
        }
        .td-holiday {
            background-color: #fee2e2 !important;
        }
        .td-libur {
            color: #dc2626 !important;
            font-weight: bold;
        }
        .td-shift {
            font-weight: bold;
            color: #1e3a8a;
        }
        .th-total {
            background-color: #facc15 !important;
        }
        .td-total {
            background-color: #fef08a !important;
            font-weight: bold;
        }
        .th-nilai {
            background-color: #fb923c !important;
            color: #000 !important;
        }
        .name-cell {
            text-align: left !important;
            padding-left: 4px !important;
            font-weight: 600;
        }
        .pos-cell {
            text-align: left !important;
            padding-left: 4px !important;
            font-size: 6.5px;
        }
        .footer-note-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 6.5px;
        }
        .footer-note-table td {
            vertical-align: top;
            padding: 2px 4px;
        }
        .legend-box {
            font-size: 6.2px;
            line-height: 1.35;
        }
        .legend-box .title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="logo-box">
                @if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN">@else<strong>PLN Nusantara Power</strong>@endif
            </td>
            <td class="title-box">
                <h1>ONCALL K3L PT MITRA KARYA PRIMA</h1>
                <h2>PERIODE 16 {{ $prevMonthName }} {{ $prevYear }} - 15 {{ $monthName }} {{ $year }}</h2>
                <h3>{{ strtoupper($unit->name) }}</h3>
            </td>
            <td class="logo-box">
                @if($logoRight)<img src="{{ $logoRight }}" alt="MKP">@else<strong>Mitra Karya Prima</strong>@endif
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 16px;">NO</th>
                <th style="width: 110px;">NAMA</th>
                <th style="width: 45px;">KODE PRK</th>
                <th style="width: 75px;">JABATAN</th>
                @foreach($days as $d)
                    <th class="{{ $d['is_holiday'] ? 'th-holiday' : ($d['is_weekend'] ? 'th-weekend' : '') }}" style="width: 13px; font-size: 6.5px;">
                        {{ $d['day'] }}
                    </th>
                @endforeach
                <th class="th-total" style="width: 25px;">TOTAL</th>
                <th class="th-nilai" style="width: 45px;">NILAI</th>
                <th style="width: 50px;">RUPIAH</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['no_urut'] }}</td>
                    <td class="name-cell">{{ $row['nama'] }}</td>
                    <td>{{ $row['kode_prk'] }}</td>
                    <td class="pos-cell">{{ $row['jabatan'] }}</td>
                    @foreach($days as $d)
                        @php
                            $val = $row['schedule'][$d['date']] ?? '';
                            $isL = strtoupper(trim((string)$val)) === 'L';
                            $bgClass = $d['is_holiday'] ? 'td-holiday' : ($d['is_weekend'] ? 'td-weekend' : '');
                        @endphp
                        <td class="{{ $bgClass }} {{ $isL ? 'td-libur' : ($val ? 'td-shift' : '') }}">
                            {{ $val }}
                        </td>
                    @endforeach
                    <td class="td-total">{{ $row['total'] }}</td>
                    <td style="text-align: right; padding-right: 3px;">
                        {{ number_format($row['nilai'], 0, ',', '.') }}
                    </td>
                    <td style="text-align: right; padding-right: 3px; font-weight: bold;">
                        {{ $row['rupiah'] > 0 ? number_format($row['rupiah'], 0, ',', '.') : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 7 + count($days) }}" style="padding: 12px; font-style: italic; color: #666;">
                        Belum ada personil atau jadwal on call pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(count($rows) > 0)
            <tfoot>
                <tr style="font-weight: bold; background-color: #f3f4f6;">
                    <td colspan="{{ 4 + count($days) }}" style="text-align: right; padding-right: 6px;">TOTAL</td>
                    <td class="td-total">{{ array_sum(array_column($rows, 'total')) }}</td>
                    <td style="text-align: right; padding-right: 3px;">{{ number_format($totalNilai, 0, ',', '.') }}</td>
                    <td style="text-align: right; padding-right: 3px;">{{ $totalRupiah > 0 ? number_format($totalRupiah, 0, ',', '.') : '-' }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <table class="footer-note-table">
        <tr>
            <td style="width: 45%;">
                <div class="legend-box">
                    <div class="title">KETERANGAN:</div>
                    <div>- Periode cut-off: 16 {{ $prevMonthName }} s/d 15 {{ $monthName }}</div>
                    <div>- Pada jadwal libur pastikan ditulis <strong>L</strong>, jangan dikosongkan</div>
                    <div>- <strong>DT</strong> : (DAY TIME) 08:00 - 16:00</div>
                    <div>- <strong>P</strong> : (SHIFT PAGI) 07:30 - 15:30 &nbsp;|&nbsp; <strong>S</strong> : (SHIFT SORE) 15:00 - 22:30</div>
                    <div>- <strong>M</strong> : (SHIFT MALAM) 22:30 - 07:30 &nbsp;|&nbsp; <strong>L</strong> : LIBUR KERJA</div>
                    <div>- <strong>CT</strong> : CUTI &nbsp;|&nbsp; <strong>SD</strong> : SURAT DOKTER / SAKIT &nbsp;|&nbsp; <strong>I</strong> : IZIN</div>
                    <div>- <strong>A</strong> : ALFA &nbsp;|&nbsp; <strong>D</strong> : DISPENSASI &nbsp;|&nbsp; <strong>DL</strong> : DINAS LUAR</div>
                    <div style="margin-top: 2px;">
                        <span style="background-color: #fecaca; padding: 0 4px; border: 1px solid #dc2626;">Libur Nasional</span>
                        &nbsp;&nbsp;
                        <span style="background-color: #fef08a; padding: 0 4px; border: 1px solid #ca8a04;">Sabtu / Minggu</span>
                    </div>
                </div>
            </td>
            <td style="width: 25%; text-align: center;">
                <div>Dibuat Oleh:</div>
                <div style="font-weight: bold; margin-top: 1px;">Pelaksana K3L</div>
                <div style="height: 38px;"></div>
                <div style="text-decoration: underline; font-weight: bold;">( ........................................ )</div>
                <div>Staff K3L &amp; Keamanan</div>
            </td>
            <td style="width: 30%; text-align: center;">
                <div>Mengetahui:</div>
                <div style="font-weight: bold; margin-top: 1px;">Team Leader / Manager Unit</div>
                <div style="height: 38px;"></div>
                <div style="text-decoration: underline; font-weight: bold;">( ........................................ )</div>
                <div>Team Leader {{ $unit->name }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
