<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $unit->name }}</title>
    <style>
        @page { margin: 8mm 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 7.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12px; line-height: 1.45; margin: 4px 0 6px; }
        .unit { font-family: 'Courier New', monospace; font-weight: bold; font-size: 9px; margin-bottom: 2px; }
        .sheet th, .sheet td { border: 0.6px solid #000; padding: 1.5px 2.5px; }
        .sheet th { font-size: 7px; text-align: center; font-weight: bold; }
        .sheet th.item { background: #e11d1d; color: #fff; font-size: 8.5px; }
        .sheet td { text-align: right; }
        .sheet td.d { text-align: center; font-weight: bold; }
        .sheet tr.period td { font-weight: bold; background: #f1f1f1; }
        .sheet tr.total td { font-weight: bold; background: #fff3a3; border-top: 1.2px solid #000; }
        .sheet .first { border-left: 1.4px solid #000; }
        .notes { margin-top: 6px; font-size: 8px; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $fmt = fn (float $value): string => $value == 0.0 ? '-' : number_format($value, 2, ',', '.');
        // Every block: PERSEDIAAN AWAL, then these, then SALDO AKHIR.
        $columns = ['penerimaan' => 'PENERIMAAN', 'pemakaian' => 'PEMAKAIAN'] + array_map('strtoupper', $kirim_columns);
        $span = count($columns) + 2;
    @endphp

    <div class="title">{{ $title }}<br>BULAN {{ strtoupper($period_label) }}</div>
    <div class="unit">{{ strtoupper($unit->name) }}</div>

    @if (count($items) === 0)
        <p>Belum ada jenis {{ str_contains($title, 'PELUMAS') ? 'pelumas' : 'BBM' }} di Data Master unit ini.</p>
    @else
        <table class="sheet">
            <thead>
                <tr>
                    <th rowspan="3" style="width: 4%;">TGL</th>
                    @foreach ($items as $item)
                        <th colspan="{{ $span }}" class="item first">{{ strtoupper($item['name']) }}@if ($item['code']) ({{ $item['code'] }})@endif</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($items as $item)
                        <th class="first">PERSEDIAAN AWAL</th>
                        @foreach ($columns as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                        <th>SALDO AKHIR</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($items as $item)
                        @for ($i = 0; $i < $span; $i++)
                            <th @class(['first' => $i === 0])>{{ strtoupper($item['unit_label']) }}</th>
                        @endfor
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="d"></td>
                    @foreach ($items as $item)
                        <td class="first" colspan="{{ $span - 1 }}"></td>
                        <td>{{ $fmt($sheet[$item['key']]['opening']) }}</td>
                    @endforeach
                </tr>
                @for ($day = 1; $day <= $days_in_month; $day++)
                    <tr>
                        <td class="d">{{ $day }}</td>
                        @foreach ($items as $item)
                            @php($row = $sheet[$item['key']]['rows'][$day])
                            <td class="first">{{ $fmt($row['awal']) }}</td>
                            @foreach ($columns as $field => $label)
                                <td>{{ $fmt($row[$field]) }}</td>
                            @endforeach
                            <td>{{ $fmt($row['akhir']) }}</td>
                        @endforeach
                    </tr>
                @endfor
                @foreach ($sheet[$items[0]['key']]['periods'] as $index => $period)
                    <tr class="period">
                        <td class="d">{{ $period['label'] }}</td>
                        @foreach ($items as $item)
                            @php($sum = $sheet[$item['key']]['periods'][$index])
                            <td class="first"></td>
                            @foreach ($columns as $field => $label)
                                <td>{{ $fmt($sum[$field]) }}</td>
                            @endforeach
                            <td>{{ $fmt($sum['akhir']) }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="total">
                    <td class="d">TOTAL</td>
                    @foreach ($items as $item)
                        @php($sum = $sheet[$item['key']]['total'])
                        <td class="first"></td>
                        @foreach ($columns as $field => $label)
                            <td>{{ $fmt($sum[$field]) }}</td>
                        @endforeach
                        <td>{{ $fmt($sum['akhir']) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endif

    @if ($catatan)
        <div class="notes"><b>Catatan:</b> {{ $catatan }}</div>
    @endif
</body>
</html>
