<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $jenis->label() }} - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Times New Roman', serif; font-weight: bold; font-size: 13px; line-height: 1.4; margin: 4px 0 6px; }
        .unit { font-family: 'Courier New', monospace; font-weight: bold; font-size: 9px; margin-bottom: 2px; }
        .sheet th, .sheet td { border: 0.6px solid #000; padding: 1.5px 3px; }
        .sheet th { font-size: 8px; text-align: center; }
        .sheet th.k { text-align: left; width: 11%; }
        .sheet th.mampu { background: #fff3b0; }
        .sheet td { text-align: right; font-size: 8.5px; }
        .sheet td.d { text-align: center; font-weight: bold; }
        .sheet td.sum { font-weight: bold; }
        .sheet tr.jmh td { font-weight: bold; border-top: 1.2px solid #000; }
        .notes { margin-top: 8px; font-family: 'Courier New', monospace; font-size: 8.5px; line-height: 1.5; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $isBeban = $jenis === \App\Enums\MesinHarianJenis::BebanTinggi;
        $decimals = $jenis === \App\Enums\MesinHarianJenis::KaliGangguan ? 2 : 0;
        $fmt = fn (float $value): string => $value == 0.0 ? '' : number_format($value, 0, ',', '.');
        $groups = collect($machines)->groupBy(fn (array $m): string => strtoupper(trim((string) $m['merk'])) ?: 'M A K');
        // Machines in merk order, so each MERK header spans its own columns.
        $machines = $groups->flatten(1)->values()->all();
        $mampuTotal = array_sum(array_map('floatval', $daya_mampu));
    @endphp

    <div class="title">{{ $jenis->title() }}<br>BULAN {{ strtoupper($period_label) }}</div>
    <div class="unit">{{ strtoupper($unit->name) }}</div>

    <table class="sheet">
        <thead>
            <tr>
                <th class="k">MERK</th>
                @foreach ($groups as $merk => $group)
                    <th colspan="{{ count($group) }}">{{ $merk }}</th>
                @endforeach
                <th rowspan="5" style="width: 13%;">{{ $isBeban ? 'JUM. '.strtoupper($unit->name) : 'TOTAL' }}</th>
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
            @if ($isBeban)
                <tr>
                    <th class="k mampu">DAYA MAMPU</th>
                    @foreach ($machines as $machine)
                        <th class="mampu">{{ number_format((float) ($daya_mampu[$machine['id']] ?? 0), 0, ',', '.') }}</th>
                    @endforeach
                    <th class="mampu">{{ number_format($mampuTotal, 0, ',', '.') }}</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @for ($day = 1; $day <= $days_in_month; $day++)
                <tr>
                    <td class="d">{{ $day }}</td>
                    @foreach ($machines as $machine)
                        <td>{{ $fmt((float) ($readings[$machine['id']][$day] ?? 0)) }}</td>
                    @endforeach
                    <td class="sum">{{ number_format($summary['by_day'][$day] ?? 0, $decimals, ',', '.') }}</td>
                </tr>
            @endfor
            <tr class="jmh">
                <td class="d">{{ $isBeban ? 'TERTINGGI' : 'JMH' }}</td>
                @foreach ($machines as $machine)
                    <td>{{ number_format($summary['by_machine'][$machine['id']] ?? 0, $decimals, ',', '.') }}</td>
                @endforeach
                <td>{{ number_format($summary['total'], $decimals, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if (filled($catatan))
        <div class="notes"><strong>Keterangan:</strong><br>{!! nl2br(e($catatan)) !!}</div>
    @endif
</body>
</html>
