<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>kWh Rekap - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 6mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 6.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12px; line-height: 1.45; margin: 4px 0 6px; }
        .unit { font-family: 'Courier New', monospace; font-weight: bold; font-size: 9px; margin-bottom: 2px; }
        .sheet th, .sheet td { border: 0.5px solid #000; padding: 1px 2px; }
        .sheet th { font-size: 6px; text-align: center; }
        .sheet th.k { text-align: left; }
        .sheet th.block { font-size: 8px; }
        .sheet th.produksi { background: #8ecae6; }
        .sheet th.ps { background: #95d26b; }
        .sheet th.nett { background: #ffe94d; }
        .sheet td { text-align: right; }
        .sheet td.d { text-align: center; font-weight: bold; }
        .sheet td.sum { font-weight: bold; }
        .sheet .first { border-left: 1.4px solid #000; }
        .sheet tr.jmh td { font-weight: bold; border-top: 1.2px solid #000; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $fmt = fn (float $value): string => $value == 0.0 ? '0' : number_format($value, 0, ',', '.');
        $machines = collect($groups)->flatMap(fn (array $group): array => $group['machines'])->values()->all();
        $count = count($machines);
    @endphp

    <div class="title">REKAP KWH, KWH PEMAKAIAN SENDIRI &amp; KWH NETTO<br>BULAN {{ strtoupper($period_label) }}</div>
    <div class="unit">{{ strtoupper($unit->name) }}</div>

    <table class="sheet">
        <thead>
            <tr>
                <th class="k">MERK</th>
                @foreach ($blocks as $block)
                    @foreach ($groups as $index => $group)
                        <th colspan="{{ count($group['machines']) }}" @class(['first' => $index === 0])>{{ $group['merk'] }}</th>
                    @endforeach
                    <th rowspan="4" class="sum">JUMLAH</th>
                @endforeach
            </tr>
            <tr>
                <th class="k">TYPE</th>
                @foreach ($blocks as $block)
                    @foreach ($machines as $index => $machine)
                        <th @class(['first' => $index === 0])>{{ $machine['type'] ?: '-' }}</th>
                    @endforeach
                @endforeach
            </tr>
            <tr>
                <th class="k">NO. SERI</th>
                @foreach ($blocks as $block)
                    @foreach ($machines as $index => $machine)
                        <th @class(['first' => $index === 0])>{{ $machine['serial_number'] ?: '-' }}</th>
                    @endforeach
                @endforeach
            </tr>
            <tr>
                <th class="k">UNIT</th>
                @foreach ($blocks as $block)
                    @foreach ($machines as $index => $machine)
                        <th @class(['first' => $index === 0])>{{ $machine['name'] }}</th>
                    @endforeach
                @endforeach
            </tr>
            <tr>
                <th class="k">TGL</th>
                @foreach ($blocks as $block)
                    <th colspan="{{ $count + 1 }}" class="block first {{ $block['key'] }}">{{ strtoupper($block['title']) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @for ($day = 1; $day <= $days_in_month; $day++)
                <tr>
                    <td class="d">{{ $day }}</td>
                    @foreach ($blocks as $block)
                        @foreach ($machines as $index => $machine)
                            <td @class(['first' => $index === 0])>{{ $fmt((float) ($block['values'][$machine['id']][$day] ?? 0)) }}</td>
                        @endforeach
                        <td class="sum">{{ $fmt((float) ($block['totals_by_day'][$day] ?? 0)) }}</td>
                    @endforeach
                </tr>
            @endfor
            <tr class="jmh">
                <td class="d">JMH</td>
                @foreach ($blocks as $block)
                    @foreach ($machines as $index => $machine)
                        <td @class(['first' => $index === 0])>{{ $fmt((float) ($block['totals_by_machine'][$machine['id']] ?? 0)) }}</td>
                    @endforeach
                    <td>{{ $fmt((float) $block['grand_total']) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
</body>
</html>
