<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $heading }} - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .doc-info { font-size: 8.5px; line-height: 1.4; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12.5px; line-height: 1.45; margin: 4px 0 6px; }
        .unit { font-family: 'Courier New', monospace; font-weight: bold; font-size: 9px; margin-bottom: 2px; }
        .sheet th, .sheet td { border: 0.6px solid #000; padding: 1.5px 2.5px; }
        .sheet th { font-family: 'Courier New', monospace; font-size: 7.5px; text-align: center; }
        .sheet th.k { text-align: left; width: 8%; }
        .sheet td { text-align: right; font-size: 8px; }
        .sheet td.d { text-align: center; font-weight: bold; }
        .sheet td.sum, .sheet th.sum { font-weight: bold; }
        .sheet td.neg { color: #b42318; }
        .sheet tr.jmh td { font-weight: bold; border-top: 1.2px solid #000; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 72%; vertical-align: top;">@include('operasi.partials.kop-pengusahaan', ['unit' => $unit])</td>
            <td style="vertical-align: top;" class="doc-info">No. Dok.: LK.02.04.46.0902<br>Revisi &nbsp;&nbsp;:<br>Tanggal : 01 – 03 – 05</td>
        </tr>
    </table>
    @php
        $fmt = fn (float $value): string => $value == 0.0 ? '-' : ($value < 0 ? '('.number_format(abs($value), 0, ',', '.').')' : number_format($value, 0, ',', '.'));
        $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];
    @endphp

    <div class="title">{{ $heading }}<br>BULAN {{ strtoupper($period_label) }}</div>
    <div class="unit">{{ strtoupper($unit->name) }}</div>

    <table class="sheet">
        <thead>
            <tr>
                <th class="k">MERK</th>
                @foreach ($groups as $group)
                    <th colspan="{{ count($group['machines']) }}">{{ implode(' ', str_split($group['merk'])) }}</th>
                    <th rowspan="4" class="sum">JUMLAH<br>{{ $group['merk'] }}</th>
                @endforeach
                <th rowspan="5" class="sum" style="width: 10%;">TOTAL KWH<br>{{ strtoupper($unit->name) }}</th>
            </tr>
            <tr>
                <th class="k">TYPE</th>
                @foreach ($groups as $group)
                    @foreach ($group['machines'] as $machine)
                        <th>{{ $machine['type'] ?: '-' }}</th>
                    @endforeach
                @endforeach
            </tr>
            <tr>
                <th class="k">NO. SERI</th>
                @foreach ($groups as $group)
                    @foreach ($group['machines'] as $machine)
                        <th>{{ $machine['serial_number'] ?: '-' }}</th>
                    @endforeach
                @endforeach
            </tr>
            <tr>
                <th class="k">UNIT</th>
                @foreach ($groups as $group)
                    @foreach ($group['machines'] as $machine)
                        <th>{{ $machine['name'] }}</th>
                    @endforeach
                @endforeach
            </tr>
            <tr>
                <th class="k">NO. UNIT</th>
                @php $no = 0; @endphp
                @foreach ($groups as $index => $group)
                    @foreach ($group['machines'] as $machine)
                        <th>{{ ++$no }}</th>
                    @endforeach
                    <th class="sum">{{ $roman[$index] ?? $index + 1 }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @for ($day = 1; $day <= $days_in_month; $day++)
                <tr>
                    <td class="d">{{ $day }}</td>
                    @foreach ($groups as $group)
                        @foreach ($group['machines'] as $machine)
                            @php $value = (float) ($values[$machine['id']][$day] ?? 0); @endphp
                            <td class="{{ $value < 0 ? 'neg' : '' }}">{{ $fmt($value) }}</td>
                        @endforeach
                        <td class="sum">{{ $fmt($group['totals_by_day'][$day]) }}</td>
                    @endforeach
                    <td class="sum">{{ number_format($totals_by_day[$day], 1, ',', '.') }}</td>
                </tr>
            @endfor
            <tr class="jmh">
                <td class="d">JMH</td>
                @foreach ($groups as $group)
                    @foreach ($group['machines'] as $machine)
                        <td>{{ $fmt($totals_by_machine[$machine['id']] ?? 0) }}</td>
                    @endforeach
                    <td>{{ $fmt($group['total']) }}</td>
                @endforeach
                <td>{{ number_format($grand_total, 1, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
