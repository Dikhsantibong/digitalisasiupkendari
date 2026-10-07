<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Ikhtisar Sentral - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12.5px; line-height: 1.45; margin: 4px 0 6px; }
        .sub { font-weight: bold; font-size: 9px; margin: 8px 0 3px; }
        .sheet th, .sheet td { border: 0.6px solid #000; padding: 2px 3px; }
        .sheet th { background: #eeeeee; font-size: 7.5px; text-align: center; }
        .sheet td { text-align: right; }
        .sheet td.l { text-align: left; }
        .sheet tr.sum td { font-weight: bold; border-top: 1.1px solid #000; }
        .summary td { padding: 2px 6px; font-size: 8.5px; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $fmt = fn ($value, int $decimals = 0): string => (float) $value == 0.0 ? '-' : number_format((float) $value, $decimals, ',', '.');
        $mesins = $ikhtisar['mesins'];
        $value = fn (int $id, string $field) => $mesins[$id][$field] ?? 0;
        $bucket = fn (int $id, string $field, string|int $key): float => (float) ($mesins[$id][$field][$key] ?? 0);
        $codes = array_column($fuels, 'code');
        $rowLabels = [
            'persediaan_awal' => 'Persediaan awal',
            'penerimaan' => 'Penerimaan',
            'penerimaan_sewa_smp' => 'Penerimaan sewa SMP',
            'pemakaian_non_operasi' => 'Pemakaian non operasi',
            'pengiriman' => 'Pengiriman',
        ];
    @endphp

    <div class="title">IKHTISAR SENTRAL<br>BULAN {{ strtoupper($period_label) }}</div>

    <table class="summary">
        <tr>
            <td>kWh pemakaian sendiri: <b>{{ $fmt($ikhtisar['summary']['kwh_pemakaian_sendiri'] ?? 0) }}</b></td>
            <td>Beban puncak pagi: <b>{{ $fmt($ikhtisar['summary']['beban_puncak_pagi_kw'] ?? 0) }} kW</b></td>
            <td>Beban puncak malam: <b>{{ $fmt($ikhtisar['summary']['beban_puncak_malam_kw'] ?? 0) }} kW</b></td>
            <td>Jam jalan per hari: <b>{{ $fmt($ikhtisar['summary']['jam_jalan_perhari'] ?? 0, 1) }}</b></td>
        </tr>
    </table>

    <div class="sub">Produksi, bahan bakar &amp; pelumas per mesin</div>
    <table class="sheet">
        <thead>
            <tr>
                <th rowspan="2" style="width: 12%;">MESIN</th>
                <th rowspan="2">KWH DIBANGKIT</th>
                <th rowspan="2">JAM JALAN</th>
                @if ($codes !== [])
                    <th colspan="{{ count($codes) }}">BAHAN BAKAR (LITER)</th>
                @endif
                <th rowspan="2">T. KALOR</th>
                @if (count($lubricants) > 0)
                    <th colspan="{{ count($lubricants) }}">PELUMAS</th>
                @endif
            </tr>
            <tr>
                @foreach ($fuels as $fuel)
                    <th>{{ $fuel['code'] }}</th>
                @endforeach
                @foreach ($lubricants as $lubricant)
                    <th>{{ $lubricant->name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($machines as $machine)
                <tr>
                    <td class="l">{{ $machine->name }}</td>
                    <td>{{ $fmt($value($machine->id, 'kwh_dibangkit')) }}</td>
                    <td>{{ $fmt($value($machine->id, 'jam_jalan'), 1) }}</td>
                    @foreach ($codes as $code)
                        <td>{{ $fmt($bucket($machine->id, 'bbm', $code)) }}</td>
                    @endforeach
                    <td>{{ $fmt($value($machine->id, 't_kalor'), 2) }}</td>
                    @foreach ($lubricants as $lubricant)
                        <td>{{ $fmt($bucket($machine->id, 'pemakaian_pelumas', $lubricant->id)) }}</td>
                    @endforeach
                </tr>
            @endforeach
            <tr class="sum">
                <td class="l">JUMLAH</td>
                <td>{{ $fmt($machines->sum(fn ($m) => $value($m->id, 'kwh_dibangkit'))) }}</td>
                <td>{{ $fmt($machines->sum(fn ($m) => $value($m->id, 'jam_jalan')), 1) }}</td>
                @foreach ($codes as $code)
                    <td>{{ $fmt($machines->sum(fn ($m) => $bucket($m->id, 'bbm', $code))) }}</td>
                @endforeach
                <td></td>
                @foreach ($lubricants as $lubricant)
                    <td>{{ $fmt($machines->sum(fn ($m) => $bucket($m->id, 'pemakaian_pelumas', $lubricant->id))) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <div class="sub">Persediaan bahan bakar &amp; pelumas</div>
    <table class="sheet">
        <thead>
            <tr>
                <th style="width: 18%;">URAIAN</th>
                @foreach ($fuels as $fuel)
                    <th>{{ $fuel['code'] }} (LITER)</th>
                @endforeach
                @foreach ($lubricants as $lubricant)
                    <th>{{ $lubricant->name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rowLabels as $row => $label)
                <tr>
                    <td class="l">{{ $label }}</td>
                    @foreach ($codes as $code)
                        <td>{{ $fmt($ikhtisar['inventory'][$row]['bbm'][$code] ?? 0) }}</td>
                    @endforeach
                    @foreach ($lubricants as $lubricant)
                        <td>{{ $fmt($ikhtisar['inventory'][$row]['lubricants'][$lubricant->id] ?? 0) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
