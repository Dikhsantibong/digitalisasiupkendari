<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12.5px; line-height: 1.45; margin: 4px 0 6px; }
        .unit { font-family: 'Courier New', monospace; font-weight: bold; font-size: 9px; margin-bottom: 2px; }
        .sheet th, .sheet td { border: 0.6px solid #000; padding: 1.5px 3px; }
        .sheet th { font-family: 'Courier New', monospace; font-size: 8px; text-align: center; }
        .sheet th.k { text-align: left; width: 11%; }
        .sheet td { text-align: right; font-size: 8.5px; }
        .sheet td.d { text-align: center; font-weight: bold; }
        .sheet td.sum, .sheet th.sum { font-weight: bold; }
        .sheet td.neg { color: #b42318; }
        .sheet tr.jmh td { font-weight: bold; border-top: 1.2px solid #000; }
        .notes { margin-top: 6px; font-size: 8.5px; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $count = max(1, count($machines));
        $fmt = fn (float $value, bool $blank): string => $blank && $value == 0.0 ? '' : number_format($value, 2, ',', '.');
    @endphp

    <div class="title">{{ $title }}<br>BULAN {{ strtoupper($period_label) }}</div>
    <div class="unit">{{ strtoupper($unit->name) }}</div>

    <table class="sheet">
        <thead>
            <tr>
                <th class="k">MESIN</th>
                <th colspan="{{ $count }}">M A K</th>
                <th rowspan="5" class="sum" style="width: 13%;">TOTAL</th>
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
            <tr>
                <th class="k">TGL.</th>
                <th colspan="{{ $count + 1 }}"></th>
            </tr>
        </thead>
        <tbody>
            @for ($day = 1; $day <= $days_in_month; $day++)
                @php $dayTotal = 0; @endphp
                <tr>
                    <td class="d">{{ $day }}</td>
                    @foreach ($machines as $machine)
                        @php
                            $value = (float) ($readings[$machine['id']][$day] ?? 0);
                            $dayTotal += $value;
                        @endphp
                        <td class="{{ $value < 0 ? 'neg' : '' }}">{{ $fmt($value, $blank_zero) }}</td>
                    @endforeach
                    <td class="sum {{ $dayTotal < 0 ? 'neg' : '' }}">{{ number_format($dayTotal, 2, ',', '.') }}</td>
                </tr>
            @endfor
            <tr class="jmh">
                <td class="d">JMH</td>
                @foreach ($machines as $machine)
                    <td>{{ number_format($totals_by_machine[$machine['id']] ?? 0, 2, ',', '.') }}</td>
                @endforeach
                <td>{{ number_format($grand_total, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if (filled($catatan ?? null))
        <div class="notes"><strong>Catatan:</strong><br>{!! nl2br(e($catatan)) !!}</div>
    @endif
</body>
</html>
