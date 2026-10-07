<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Stand kWh Meter Transfer Pricing - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .doc-info { font-size: 9px; line-height: 1.4; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 13px; line-height: 1.5; margin: 6px 0 4px; }
        .hal { text-align: right; font-family: 'Courier New', monospace; font-weight: bold; font-size: 9px; }
        .tp th, .tp td { border: 0.6px solid #000; padding: 3px 4px; }
        .tp th { font-family: 'Courier New', monospace; font-size: 8.5px; text-align: center; }
        .tp td { font-size: 9px; text-align: right; }
        .tp td.c { text-align: center; }
        .tp td.l { text-align: left; }
        .tp tr.group td { font-family: 'Courier New', monospace; font-weight: bold; text-align: left; }
        .tp tr.sum td { font-weight: bold; border-top: 1.2px solid #000; }
    </style>
</head>
<body>
@php
    // Numbers as typed: no trailing zeros after the comma.
    $plain = fn (float $value, int $decimals): string => rtrim(rtrim(number_format($value, $decimals, ',', '.'), '0'), ',');
    $fmt = fn ($value): string => $value === null ? '-' : $plain((float) $value, 4);
    $kwh = fn ($value): string => (float) $value == 0.0 ? '-' : $plain((float) $value, 2);
    $pages = [
        ['rows' => $produksi, 'total' => $total_produksi, 'heading' => 'STAND KWH METER MESIN PEMBANGKIT', 'hal' => 'hal 1/2', 'jumlah' => 'JUMLAH KWH PRODUKSI '.strtoupper($unit->name)],
        ['rows' => $ps, 'total' => $total_ps, 'heading' => 'STAND KWH METER PEMAKAIAN SENDIRI (PS)', 'hal' => 'hal 2/2', 'jumlah' => 'JUMLAH'],
    ];
@endphp
@foreach ($pages as $page)
    <div class="page">
        <table>
            <tr>
                <td style="width: 70%; vertical-align: top;">@include('operasi.partials.kop-pengusahaan', ['unit' => $unit])</td>
                <td style="vertical-align: top;" class="doc-info">
                    No. Dok. : LK.02.04.46.0904<br>
                    Revisi &nbsp;&nbsp;&nbsp;: 0<br>
                    Tanggal : 01 – 03 – 05
                </td>
            </tr>
        </table>

        <div class="title">{{ $page['heading'] }}<br>UNTUK PERHITUNGAN TRANSFER PRICING (TP)<br>BULAN {{ strtoupper($period_label) }}</div>
        <div class="hal">{{ $page['hal'] }}</div>

        <table class="tp">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 4%;">NO<br>URT</th>
                    <th rowspan="2" style="width: 12%;">LOKASI<br>KWH METER</th>
                    <th rowspan="2" style="width: 5%;">NO<br>UNIT</th>
                    <th colspan="3">P E N G G E R A K</th>
                    <th rowspan="2">STAND<br>AWAL</th>
                    <th rowspan="2">STAND<br>AKHIR</th>
                    <th rowspan="2" style="width: 7%;">FAKTOR<br>KALI</th>
                    <th rowspan="2">HASIL AKHIR<br>(KWH)</th>
                    <th rowspan="2" style="width: 6%;">KET.</th>
                </tr>
                <tr>
                    <th>MERK</th>
                    <th>TYPE</th>
                    <th>NO. SERIE</th>
                </tr>
            </thead>
            <tbody>
                <tr class="group"><td>II</td><td colspan="10">{{ strtoupper($unit->name) }}</td></tr>
                @foreach ($page['rows'] as $index => $row)
                    <tr>
                        <td class="c">{{ $index + 1 }}</td>
                        <td></td>
                        <td class="c">{{ $index + 1 }}</td>
                        <td class="c">{{ $row['merk'] ? strtoupper($row['merk']) : '-' }}</td>
                        <td class="c">{{ $row['type'] ?: '-' }}</td>
                        <td class="c">{{ $row['serial_number'] ?: '-' }}</td>
                        <td>{{ $fmt($row['stand_awal']) }}</td>
                        <td>{{ $fmt($row['stand_akhir']) }}</td>
                        <td>{{ $plain((float) $row['faktor_kali'], 4) }}</td>
                        <td>{{ $kwh($row['hasil']) }}</td>
                        <td></td>
                    </tr>
                @endforeach
                <tr class="sum">
                    <td colspan="9" class="c">{{ $page['jumlah'] }}</td>
                    <td>{{ $plain((float) $page['total'], 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
