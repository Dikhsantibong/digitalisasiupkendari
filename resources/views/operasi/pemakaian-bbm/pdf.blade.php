<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pemakaian Bahan Bakar - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12px; line-height: 1.4; }
        .unit { font-family: 'Courier New', monospace; font-weight: bold; font-size: 9px; margin: 6px 0 2px; }
        .sheet { margin-bottom: 12px; page-break-inside: avoid; }
        .sheet th, .sheet td { border: 0.6px solid #000; padding: 1.5px 3px; }
        .sheet th { font-family: 'Courier New', monospace; font-size: 8px; text-align: center; }
        .sheet th.k { text-align: left; width: 11%; }
        .sheet td { text-align: right; font-size: 8.5px; }
        .sheet td.d { text-align: center; font-weight: bold; }
        .sheet td.sum, .sheet th.sum { font-weight: bold; }
        .sheet tr.jmh td { font-weight: bold; border-top: 1.2px solid #000; }
        .notes { margin-top: 6px; font-size: 8.5px; }
    </style>
</head>
<body>
    @php
        $fmt = fn (float $value): string => $value > 0 ? number_format($value, 2, ',', '.') : '-';
    @endphp

    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    <div class="title">PEMAKAIAN BAHAN BAKAR (LTR)<br>BULAN {{ strtoupper($period_label) }}</div>

    @forelse ($fuels as $fuel)
        @php $machines = $fuel['machines']; $count = max(1, count($machines)); @endphp
        <div class="unit">{{ strtoupper($unit->name) }} — {{ strtoupper($fuel['name']) }} ({{ $fuel['code'] }})</div>
        <table class="sheet">
            <thead>
                <tr>
                    <th class="k">MESIN</th>
                    @foreach ($machines as $machine)
                        <th>{{ $machine['name'] }}</th>
                    @endforeach
                    <th rowspan="4" class="sum" style="width: 16%;">JUMLAH PEMAKAIAN<br>{{ $fuel['code'] }}</th>
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
                                $value = (float) ($readings[$keyOf($fuel['code'], $machine['id'])][$day] ?? 0);
                                $dayTotal += $value;
                            @endphp
                            <td>{{ $fmt($value) }}</td>
                        @endforeach
                        <td class="sum">{{ $fmt($dayTotal) }}</td>
                    </tr>
                @endfor
                <tr class="jmh">
                    <td class="d">JMH</td>
                    @foreach ($machines as $machine)
                        <td>{{ number_format($summary['totals_by_machine'][$keyOf($fuel['code'], $machine['id'])] ?? 0, 2, ',', '.') }}</td>
                    @endforeach
                    <td>{{ number_format($summary['totals_by_fuel'][$fuel['code']] ?? 0, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @empty
        <p style="text-align: center; padding: 20px;">Unit ini belum punya jenis BBM di Data Master (Tangki BBM / BBM mesin).</p>
    @endforelse

    @if (filled($catatan))
        <div class="notes"><strong>Catatan:</strong><br>{!! nl2br(e($catatan)) !!}</div>
    @endif
</body>
</html>
