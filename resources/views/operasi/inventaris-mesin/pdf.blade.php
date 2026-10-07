<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Inventarisasi Mesin - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 7.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12px; line-height: 1.5; margin: 4px 0 6px; }
        .inv th, .inv td { border: 0.6px solid #000; padding: 2.5px 2.5px; }
        .inv th { font-family: 'Courier New', monospace; font-size: 7px; text-align: center; }
        .inv td { text-align: center; font-size: 7.5px; }
        .inv td.r { text-align: right; }
        .inv td.l { text-align: left; }
        .inv tr.group td { font-weight: bold; text-align: left; }
        .inv tr.sum td { font-weight: bold; border-top: 1.2px solid #000; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])
    @php
        $num = fn ($value, int $decimals = 0): string => $value === null || $value === '' ? '' : number_format((float) $value, $decimals, ',', '.');
    @endphp

    <div class="title">DAFTAR INVENTARISASI MESIN<br>PEMBANGKIT TENAGA LISTRIK {{ strtoupper($unit->name) }}<br>BULAN {{ strtoupper($period_label) }}</div>

    <table class="inv">
        <thead>
            <tr>
                <th rowspan="2" style="width: 3%;">NO<br>URT</th>
                <th rowspan="2" style="width: 8%;">KIT/SENTRAL</th>
                <th rowspan="2" style="width: 3.5%;">JMH<br>UNIT</th>
                <th colspan="6">P E N G G E R A K</th>
                <th colspan="6">GENERATOR</th>
                <th>TERPSG</th>
                <th>MAMPU</th>
                <th>BEBAN</th>
                <th rowspan="2" style="width: 12%;">K E T.</th>
            </tr>
            <tr>
                <th>MERK</th><th>TYPE</th><th>NO. SERIE</th><th>HP</th><th>RPM</th><th>THN</th>
                <th>MERK</th><th>TYPE</th><th>NO. SERIE</th><th>VOLT</th><th>KVA</th><th>COS φ</th>
                <th>(KW)</th><th>(KW)</th><th>TERTINGGI (KW)</th>
            </tr>
        </thead>
        <tbody>
            <tr class="group"><td>II</td><td colspan="18">PLTD</td></tr>
            @foreach ($rows as $row)
                @php $m = $row['machine']; @endphp
                <tr>
                    <td>{{ $row['no'] }}</td>
                    <td class="l" style="font-weight: bold;">{{ $loop->first ? strtoupper($unit->name) : '' }}</td>
                    <td>{{ $row['no'] }}</td>
                    <td>{{ $m['merk'] ? strtoupper($m['merk']) : '' }}</td>
                    <td>{{ $m['type'] }}</td>
                    <td>{{ $m['serial_number'] }}</td>
                    <td>{{ $m['engine_hp'] }}</td>
                    <td>{{ $m['engine_rpm'] }}</td>
                    <td>{{ $m['tahun_pembuatan'] }}</td>
                    <td>{{ $m['generator_merk'] ? strtoupper($m['generator_merk']) : '' }}</td>
                    <td>{{ $m['generator_type'] }}</td>
                    <td>{{ $m['generator_serial_number'] }}</td>
                    <td>{{ $m['generator_volt'] }}</td>
                    <td>{{ $num($m['generator_kva']) }}</td>
                    <td>{{ $num($m['generator_cos_phi'], 1) }}</td>
                    <td class="r">{{ $num($m['terpasang']) }}</td>
                    <td class="r">{{ $num($row['mampu']) }}</td>
                    <td class="r">{{ $num($row['beban']) }}</td>
                    <td class="l">{{ $row['ket'] }}</td>
                </tr>
            @endforeach
            <tr class="sum">
                <td colspan="15" class="r">JUMLAH {{ strtoupper($unit->name) }} :</td>
                <td class="r">{{ $num($totals['terpasang']) }}</td>
                <td class="r">{{ $num($totals['mampu']) }}</td>
                <td class="r">{{ $num($totals['beban']) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
