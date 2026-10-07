<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Stand kWh Meter - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 7.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-weight: bold; font-size: 11px; line-height: 1.4; margin: 2px 0 6px; }
        .sheet { margin-bottom: 8px; page-break-inside: avoid; }
        .sheet th, .sheet td { border: 0.5px solid #000; padding: 1px 2.5px; }
        .sheet th { font-size: 7px; text-align: center; background: #eef3f7; }
        .sheet th.mak { font-size: 10px; color: #1f4e79; background: #fff; }
        .sheet td { text-align: right; }
        .sheet td.d { text-align: center; font-weight: bold; }
        .sheet td.nett, .sheet th.nett { background: #e2f0d9; font-weight: bold; }
        .sheet td.neg { color: #b42318; }
        .sheet tr.tot td { font-weight: bold; font-style: italic; border-top: 1.1px solid #000; }
        .param { font-size: 6.5px; text-align: left; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])
    @php
        $fmt = fn ($value): string => $value === null ? '' : ($value < 0 ? '('.number_format(abs($value), 0, ',', '.').')' : number_format($value, 0, ',', '.'));
        $stand = fn ($value): string => $value === null ? '' : number_format($value, 0, ',', '.');
    @endphp

    <div class="title">STAND KWH METER<br>BULAN {{ strtoupper($period_label) }}</div>

    @foreach (array_chunk($sheets, 2) as $chunk)
        <table class="sheet">
            <thead>
                <tr>
                    <th rowspan="4" style="width: 3%;">TGL</th>
                    @foreach ($chunk as $sheet)
                        <th colspan="7" class="mak">{{ $sheet['machine']['name'] }}{{ $sheet['machine']['serial_number'] ? ' — SN '.$sheet['machine']['serial_number'] : '' }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($chunk as $sheet)
                        <th colspan="2" class="param">Stand awal bln lalu: {{ $stand($sheet['stand_awal_produksi']) }}<br>F. koreksi: {{ $sheet['faktor_koreksi'] }} · F. kali: {{ $sheet['faktor_kali_produksi'] }}</th>
                        <th rowspan="3">PRODUKSI</th>
                        <th colspan="2" class="param">Stand awal bln lalu: {{ $stand($sheet['stand_awal_ps']) }}<br>F. koreksi: {{ $sheet['faktor_koreksi'] }} · F. kali: {{ $sheet['faktor_kali_ps'] }}</th>
                        <th rowspan="3">PEMAKAIAN SENDIRI</th>
                        <th rowspan="3" class="nett">KWH NETT</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($chunk as $sheet)
                        <th colspan="2">STAND KWH PROD</th>
                        <th colspan="2">STAND KWH PS</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($chunk as $sheet)
                        <th>AWAL</th><th>AKHIR</th><th>AWAL</th><th>AKHIR</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @for ($i = 0; $i < $days_in_month; $i++)
                    <tr>
                        <td class="d">{{ $i + 1 }}</td>
                        @foreach ($chunk as $sheet)
                            @php $row = $sheet['rows'][$i]; @endphp
                            <td>{{ $stand($row['produksi_awal']) }}</td>
                            <td>{{ $stand($row['produksi_akhir']) }}</td>
                            <td>{{ $fmt($row['produksi']) }}</td>
                            <td>{{ $stand($row['ps_awal']) }}</td>
                            <td>{{ $stand($row['ps_akhir']) }}</td>
                            <td>{{ $fmt($row['ps']) }}</td>
                            <td class="nett {{ ($row['nett'] ?? 0) < 0 ? 'neg' : '' }}">{{ $fmt($row['nett']) }}</td>
                        @endforeach
                    </tr>
                @endfor
                <tr class="tot">
                    <td class="d">TOT</td>
                    @foreach ($chunk as $sheet)
                        <td>{{ $stand($sheet['stand_awal_produksi']) }}</td>
                        <td>{{ $stand($sheet['stand_akhir_produksi']) }}</td>
                        <td>{{ $fmt($sheet['totals']['produksi']) }}</td>
                        <td>{{ $stand($sheet['stand_awal_ps']) }}</td>
                        <td>{{ $stand($sheet['stand_akhir_ps']) }}</td>
                        <td>{{ $fmt($sheet['totals']['ps']) }}</td>
                        <td class="nett">{{ $fmt($sheet['totals']['nett']) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endforeach

    @if ($sheets === [])
        <p style="text-align: center; padding: 20px;">Belum ada mesin aktif di Master Mesin.</p>
    @endif
</body>
</html>
