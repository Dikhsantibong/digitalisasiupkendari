<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12.5px; line-height: 1.45; margin: 4px 0 6px; }
        .unit { font-family: 'Courier New', monospace; font-weight: bold; font-size: 9px; margin-bottom: 2px; }
        .sheet th, .sheet td { border: 0.6px solid #000; padding: 1.5px 2.5px; }
        .sheet th { font-family: 'Courier New', monospace; font-size: 7.5px; text-align: center; }
        .sheet th.k { text-align: left; width: 8%; }
        .sheet td { text-align: right; }
        .sheet td.d { text-align: center; font-weight: bold; }
        .sheet td.sum { font-weight: bold; }
        .sheet tr.jmh td { font-weight: bold; border-top: 1.2px solid #000; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $fmt = fn (float $value): string => $value == 0.0 ? '0' : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
        $machines = collect($groups)->flatMap(fn (array $group): array => $group['machines'])->values()->all();
    @endphp

    <div class="title">{{ $title }}<br>BULAN {{ strtoupper($period_label) }}</div>
    <div class="unit">{{ strtoupper($unit->name) }}</div>

    <table class="sheet">
        <thead>
            <tr>
                <th class="k">MERK</th>
                @foreach ($groups as $group)
                    <th colspan="{{ count($group['machines']) }}">{{ implode(' ', str_split($group['merk'])) }}</th>
                @endforeach
                <th rowspan="5" style="width: 10%;">TOTAL</th>
            </tr>
            <tr>
                <th class="k">TYPE</th>
                @foreach ($machines as $machine)
                    <th>{{ $machine['type'] ?: '-' }}</th>
                @endforeach
            </tr>
            <tr>
                <th class="k">NO. SERI</th>
                @foreach ($machines as $machine)
                    <th>{{ $machine['serial_number'] ?: '-' }}</th>
                @endforeach
            </tr>
            <tr>
                <th class="k">UNIT</th>
                @foreach ($machines as $machine)
                    <th>{{ $machine['name'] }}</th>
                @endforeach
            </tr>
            <tr>
                <th class="k">NO. UNIT</th>
                @foreach ($machines as $index => $machine)
                    <th>{{ $index + 1 }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @for ($day = 1; $day <= $days_in_month; $day++)
                <tr>
                    <td class="d">{{ $day }}</td>
                    @foreach ($machines as $machine)
                        <td>{{ $fmt($values[$machine['id']][$day] ?? 0) }}</td>
                    @endforeach
                    <td class="sum">{{ $fmt(array_sum(array_map(fn (array $m): float => $values[$m['id']][$day] ?? 0, $machines))) }}</td>
                </tr>
            @endfor
            <tr class="jmh">
                <td class="d">JMH</td>
                @foreach ($machines as $machine)
                    <td>{{ $fmt(array_sum($values[$machine['id']] ?? [])) }}</td>
                @endforeach
                <td>{{ $fmt(array_sum(array_map(fn (array $m): float => array_sum($values[$m['id']] ?? []), $machines))) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
