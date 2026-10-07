<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Data Kinerja Pembangkit Termal - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12.5px; line-height: 1.5; margin: 4px 0 8px; }
        .t th, .t td { border: 0.6px solid #000; padding: 3px 3px; }
        .t th { font-family: 'Courier New', monospace; font-size: 8px; text-align: center; }
        .t td { text-align: right; font-size: 8.5px; }
        .t td.c { text-align: center; }
        .t td.l { text-align: left; }
        .t tr.group td { font-weight: bold; text-align: left; }
        .t tr.avg td { font-weight: bold; border-top: 1.2px solid #000; }
        .legend { margin-top: 8px; width: 58%; border: 0.6px solid #000; padding: 4px 6px; font-family: 'Courier New', monospace; font-size: 8px; line-height: 1.5; background: #fffbe6; }
        .sign { margin-top: 8px; text-align: center; font-size: 9px; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])
    @php
        $pct = fn (float $value): string => $value == 0.0 ? '-' : number_format($value, 2, ',', '.');
        $fields = ['cf' => 'C F', 'of' => 'O F', 'sf' => 'S F', 'eaf' => 'EAF', 'pof' => 'P O F', 'fof' => 'F O F', 'for' => 'FOR', 'sof' => 'SOF', 'efor' => 'EFOR', 'sdof' => 'SdOF (Kali)', 'eff' => 'EFF.'];
    @endphp

    <div class="title">DATA KINERJA PEMBANGKIT TERMAL<br>BULAN {{ strtoupper($period_label) }}</div>

    <table class="t">
        <thead>
            <tr>
                <th rowspan="2" style="width: 3%;">NO.<br>URT</th>
                <th rowspan="2" style="width: 9%;">KIT/SENTRAL</th>
                <th rowspan="2" style="width: 3.5%;">JMH<br>UNIT</th>
                <th rowspan="2" style="width: 8%;">MERK</th>
                <th rowspan="2" style="width: 8%;">TYPE</th>
                <th rowspan="2" style="width: 8%;">NO. SERI</th>
                <th colspan="11">PERSEN ( % )</th>
            </tr>
            <tr>
                @foreach ($fields as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr class="group"><td>II</td><td colspan="16">{{ strtoupper($unit->name) }}</td></tr>
            @foreach ($rows as $index => $row)
                <tr>
                    <td class="c">{{ $index + 1 }}</td>
                    <td></td>
                    <td class="c">{{ $index + 1 }}</td>
                    <td class="c">{{ $row['machine']['merk'] ? strtoupper($row['machine']['merk']) : '-' }}</td>
                    <td class="c">{{ $row['machine']['type'] ?: '-' }}</td>
                    <td class="c">{{ $row['machine']['serial_number'] ?: '-' }}</td>
                    @if ($row['operating'])
                        @foreach (array_keys($fields) as $field)
                            <td>{{ $field === 'sdof' ? ($row['values'][$field] == 0 ? '-' : number_format($row['values'][$field], 0)) : $pct($row['values'][$field]) }}</td>
                        @endforeach
                    @else
                        <td colspan="11" class="c">ATTB HAR</td>
                    @endif
                </tr>
            @endforeach
            <tr class="avg">
                <td colspan="6" class="c">RATA-RATA</td>
                @foreach (array_keys($fields) as $field)
                    <td>{{ number_format($averages[$field], 2, ',', '.') }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <table style="margin-top: 8px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="legend" style="width: auto;">
                    Keterangan (jam periode = {{ number_format($jam_periode, 0, ',', '.') }} jam):<br>
                    CF &nbsp;= Capacity Factor = kWh kit / (daya pasang × jam periode) × 100%<br>
                    OF &nbsp;= Operating Factor = kWh kit / (daya pasang × jam operasi) × 100%<br>
                    SF &nbsp;= Service Factor = jam operasi / jam periode × 100%<br>
                    EAF = Equivalent Availability Factor = 100% − FOF − POF<br>
                    POF = Planned Outage Factor = jam pemeliharaan / jam periode × 100%<br>
                    FOF = Forced Outage Factor = jam gangguan / jam periode × 100%<br>
                    FOR = Forced Outage Rate = jam gangguan / (jam gangguan + jam operasi) × 100%<br>
                    EFF = (kWh × {{ $constants['kcal_per_kwh'] }}) / (bahan bakar × {{ number_format($constants['kcal_per_liter'], 0, ',', '.') }} × {{ number_format($constants['density'], 3, ',', '.') }}) × 100%
                </div>
            </td>
            <td class="sign">
                Kendari, {{ $signed_on->locale('id')->translatedFormat('d F Y') }}<br><br>
                <strong>MANAJER</strong><br><br><br><br>
                <strong>( {{ $manager ? strtoupper($manager) : '................................' }} )</strong>
            </td>
        </tr>
    </table>
</body>
</html>
