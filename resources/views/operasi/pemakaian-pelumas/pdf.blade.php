<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pemakaian Pelumas - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 10mm 10mm 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .head td { vertical-align: middle; }
        .org { font-size: 10px; font-weight: bold; line-height: 1.3; }
        .org small { font-size: 8px; font-weight: normal; }
        .title { text-align: center; font-size: 12px; font-weight: bold; margin: 6px 0 2px; }
        .sub { text-align: center; font-size: 9px; margin: 0 0 8px; }
        .sheet { margin-bottom: 10px; page-break-inside: auto; }
        .sheet th, .sheet td { border: 0.6px solid #555; padding: 1.5px 2.5px; }
        .sheet th { background: #e6eef5; font-weight: bold; text-align: center; font-size: 7.5px; }
        .sheet th.lub { background: #cfe1ee; font-size: 8.5px; }
        .sheet th.sum, .sheet td.sum { background: #eaf4fb; font-weight: bold; }
        .sheet td { font-size: 7.5px; text-align: right; }
        .sheet td.c { text-align: center; }
        .sheet tr.period td { background: #f4f8fb; text-align: left; font-weight: bold; font-style: italic; }
        .sheet tr.jml td { background: #e6eef5; font-weight: bold; }
        .sheet tr.ttl td { background: #cfe1ee; font-weight: bold; }
        .recap { width: 50%; margin-top: 6px; }
        .recap th, .recap td { border: 0.6px solid #555; padding: 2px 4px; font-size: 8px; }
        .recap th { background: #e6eef5; text-align: left; }
        .recap td.r { text-align: right; }
        .notes { margin-top: 8px; font-size: 8.5px; }
    </style>
</head>
<body>
    @php
        $fmt = fn ($value): string => (float) $value > 0 ? number_format((float) $value, 2, ',', '.') : '-';
        $sumRange = function (array $row, int $from, int $to): float {
            $total = 0.0;
            for ($day = $from; $day <= $to; $day++) {
                $total += (float) ($row[$day] ?? 0);
            }

            return $total;
        };
    @endphp

    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    <div class="title">REKAPITULASI PEMAKAIAN PELUMAS</div>
    <div class="sub">{{ $unit->name }} — {{ $period_label }}</div>

    @forelse ($chunks as $chunk)
        <table class="sheet">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 22px;">TGL</th>
                    @foreach ($chunk as $lubricant)
                        <th class="lub" colspan="{{ count($lubricant['machines']) + 1 }}">{{ strtoupper($lubricant['name']) }} ({{ $lubricant['unit_label'] }})</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($chunk as $lubricant)
                        @foreach ($lubricant['machines'] as $machine)
                            <th>{{ $machine['name'] }}</th>
                        @endforeach
                        <th class="sum">JUMLAH</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($periods as $period)
                    @if ($period['key'] !== 'p1')
                        <tr class="period"><td colspan="{{ 1 + collect($chunk)->sum(fn ($l) => count($l['machines']) + 1) }}">{{ $period['label'] }}</td></tr>
                    @endif
                    @for ($day = $period['from']; $day <= $period['to']; $day++)
                        <tr>
                            <td class="c">{{ $day }}</td>
                            @foreach ($chunk as $lubricant)
                                @php $dayTotal = 0; @endphp
                                @foreach ($lubricant['machines'] as $machine)
                                    @php
                                        $value = (float) ($readings[$keyOf($lubricant['id'], $machine['id'])][$day] ?? 0);
                                        $dayTotal += $value;
                                    @endphp
                                    <td>{{ $fmt($value) }}</td>
                                @endforeach
                                <td class="sum">{{ $fmt($dayTotal) }}</td>
                            @endforeach
                        </tr>
                    @endfor
                    <tr class="jml">
                        <td class="c">JML</td>
                        @foreach ($chunk as $lubricant)
                            @php $periodTotal = 0; @endphp
                            @foreach ($lubricant['machines'] as $machine)
                                @php
                                    $value = $sumRange($readings[$keyOf($lubricant['id'], $machine['id'])] ?? [], $period['from'], $period['to']);
                                    $periodTotal += $value;
                                @endphp
                                <td>{{ number_format($value, 2, ',', '.') }}</td>
                            @endforeach
                            <td class="sum">{{ number_format($periodTotal, 2, ',', '.') }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="ttl">
                    <td class="c">TTL</td>
                    @foreach ($chunk as $lubricant)
                        @foreach ($lubricant['machines'] as $machine)
                            <td>{{ number_format($sumRange($readings[$keyOf($lubricant['id'], $machine['id'])] ?? [], 1, $periods[2]['to']), 2, ',', '.') }}</td>
                        @endforeach
                        <td class="sum">{{ number_format($summary['totals_by_lubricant'][$lubricant['id']] ?? 0, 2, ',', '.') }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @empty
        <p style="text-align: center; padding: 20px;">Belum ada jenis pelumas di Data Master untuk unit ini.</p>
    @endforelse

    @if (count($chunks) > 0)
        <table class="recap">
            <thead>
                <tr><th>Jenis Pelumas</th><th style="text-align: right;">Total Pemakaian</th></tr>
            </thead>
            <tbody>
                @foreach ($chunks as $chunk)
                    @foreach ($chunk as $lubricant)
                        <tr>
                            <td>{{ $lubricant['name'] }}</td>
                            <td class="r">{{ number_format($summary['totals_by_lubricant'][$lubricant['id']] ?? 0, 2, ',', '.') }} {{ $lubricant['unit_label'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
                <tr>
                    <th>Total keseluruhan</th>
                    <th style="text-align: right;">{{ number_format($summary['grand_total'], 2, ',', '.') }}</th>
                </tr>
            </tbody>
        </table>
    @endif

    @if (filled($catatan))
        <div class="notes"><strong>Catatan:</strong><br>{!! nl2br(e($catatan)) !!}</div>
    @endif
</body>
</html>
