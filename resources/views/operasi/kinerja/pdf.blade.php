<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kinerja Unit Mesin - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-weight: bold; font-size: 13px; line-height: 1.5; margin: 6px 0 8px; }
        .title small { font-weight: normal; font-size: 11px; }
        .k th, .k td { border: 0.6px solid #000; padding: 3px 4px; }
        .k th { font-size: 8.5px; text-align: center; }
        .k th.no { font-weight: normal; font-size: 8px; }
        .k td { font-size: 9px; text-align: right; }
        .k td.l { text-align: left; }
        .k td.c { text-align: center; }
        .k tr.group td { font-weight: bold; text-align: left; }
        .k tr.sum td { font-weight: bold; border-top: 1.2px solid #000; }
        .notes { margin-top: 8px; font-size: 8.5px; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])
    @php
        $num = fn (float $value, int $decimals = 0): string => number_format($value, $decimals, ',', '.');
    @endphp

    <div class="title">PENGUSAHAAN PEMBANGKITAN<br><small>BULAN {{ strtoupper($period_label) }}</small></div>

    <table class="k">
        <thead>
            <tr>
                <th rowspan="2" style="width: 9%;">SENTRAL</th>
                <th rowspan="2" style="width: 11%;">MESIN</th>
                <th rowspan="2" style="width: 9%;">MERK</th>
                <th rowspan="2" style="width: 9%;">TYPE</th>
                <th rowspan="2" style="width: 9%;">SERI</th>
                <th colspan="8">KINERJA UNIT MESIN</th>
            </tr>
            <tr>
                <th>Daya Terpasang</th>
                <th>Daya Mampu</th>
                <th>Kondisi</th>
                <th>Kode Kondisi</th>
                <th>Jam Operasi (Jam)</th>
                <th>Jam Har (Jam)</th>
                <th>Jam Gangguan (Jam)</th>
                <th>Ratio Daya Pembangkit (%)</th>
            </tr>
            <tr>
                @foreach (range(1, 13) as $no)
                    <th class="no">{{ $no }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr class="group"><td colspan="13">PLN PEMBANGKITAN KENDARI</td></tr>
            <tr class="group"><td colspan="13" style="font-weight: normal;">{{ strtoupper($unit->name) }}</td></tr>
            @foreach ($rows as $row)
                <tr>
                    <td></td>
                    <td class="l">{{ $row['machine']['name'] }}</td>
                    <td class="c">{{ $row['machine']['merk'] ?: '-' }}</td>
                    <td class="c">{{ $row['machine']['type'] ?: '-' }}</td>
                    <td class="c">{{ $row['machine']['serial_number'] ?: '-' }}</td>
                    <td>{{ $num($row['values']['daya_terpasang']) }}</td>
                    <td>{{ $num($row['values']['daya_mampu']) }}</td>
                    <td class="l">{{ $row['values']['kondisi'] }}</td>
                    <td class="c">{{ $row['values']['kode_kondisi'] }}</td>
                    <td>{{ $num($row['values']['jam_operasi'], 2) }}</td>
                    <td>{{ $num($row['values']['jam_har'], 2) }}</td>
                    <td>{{ $num($row['values']['jam_gangguan'], 2) }}</td>
                    <td>{{ $num($row['ratio'], 3) }}</td>
                </tr>
            @endforeach
            <tr class="sum">
                <td colspan="5"></td>
                <td>{{ $num($totals['daya_terpasang']) }}</td>
                <td>{{ $num($totals['daya_mampu']) }}</td>
                <td colspan="2"></td>
                <td>{{ $num($totals['jam_operasi'], 2) }}</td>
                <td>{{ $num($totals['jam_har'], 2) }}</td>
                <td>{{ $num($totals['jam_gangguan'], 2) }}</td>
                <td>{{ $num($totals['ratio'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if (filled($catatan))
        <div class="notes"><strong>Catatan:</strong><br>{!! nl2br(e($catatan)) !!}</div>
    @endif
</body>
</html>
