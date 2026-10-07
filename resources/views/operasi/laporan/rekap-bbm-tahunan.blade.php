<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap BBM {{ $year }} - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12.5px; line-height: 1.45; margin: 4px 0 6px; }
        .sheet th, .sheet td { border: 0.6px solid #000; padding: 2px 3px; }
        .sheet th { background: #eeeeee; font-size: 7.5px; text-align: center; }
        .sheet td { text-align: right; }
        .sheet td.l { text-align: left; font-weight: bold; }
        .sheet tr.sum td { font-weight: bold; border-top: 1.1px solid #000; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $fmt = fn (float $value): string => $value == 0.0 ? '-' : number_format($value, 0, ',', '.');
        $columns = ['awal' => 'PERSEDIAAN AWAL', 'penerimaan' => 'PENERIMAAN', 'jumlah_pemakaian' => 'PEMAKAIAN', 'pengiriman' => 'PENGIRIMAN', 'sisa' => 'SISA'];
        $codes = array_column($fuels, 'code');
    @endphp

    <div class="title">REKAP BAHAN BAKAR MINYAK<br>{{ $period_label }}</div>

    @if ($codes === [])
        <p>Belum ada jenis BBM di Data Master unit ini.</p>
    @else
        <table class="sheet">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 9%;">BULAN</th>
                    @foreach ($fuels as $fuel)
                        <th colspan="{{ count($columns) }}">{{ strtoupper($fuel['name']) }} ({{ $fuel['code'] }}) — LITER</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($codes as $code)
                        @foreach ($columns as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($months as $month => $totals)
                    <tr>
                        <td class="l">{{ strtoupper(\App\Support\Indonesian::monthName($month)) }}</td>
                        @foreach ($codes as $code)
                            @foreach (array_keys($columns) as $field)
                                <td>{{ $fmt((float) ($totals[$code][$field] ?? 0)) }}</td>
                            @endforeach
                        @endforeach
                    </tr>
                @endforeach
                <tr class="sum">
                    <td class="l">JUMLAH</td>
                    @foreach ($codes as $code)
                        @foreach (array_keys($columns) as $field)
                            @php
                                $values = array_map(fn (array $totals): float => (float) ($totals[$code][$field] ?? 0), $months);
                                // Stock columns are the first / last month; flows are summed.
                                $value = match ($field) {
                                    'awal' => reset($values) ?: 0.0,
                                    'sisa' => end($values) ?: 0.0,
                                    default => array_sum($values),
                                };
                            @endphp
                            <td>{{ $fmt((float) $value) }}</td>
                        @endforeach
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
