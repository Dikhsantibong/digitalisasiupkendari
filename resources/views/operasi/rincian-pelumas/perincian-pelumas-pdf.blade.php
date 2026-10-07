<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Perincian Minyak Pelumas - {{ $unit->name }}</title>
    <style>
        @page { margin: 10mm 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 13px; line-height: 1.4; margin: 6px 0 8px; }
        .sheet td, .sheet th { padding: 2px 3px; }
        .sheet th { border: 0.6px solid #000; font-size: 7.5px; text-align: center; }
        .sheet td.v { border-left: 0.6px solid #000; border-right: 0.6px solid #000; border-bottom: 0.4px dotted #777; text-align: right; }
        .sheet td.no { width: 4%; font-weight: bold; }
        .sheet td.lbl { width: 22%; }
        .sheet td.sub { width: 14%; }
        .sheet tr.sec td.lbl { font-weight: bold; }
        .sheet tr.sum td { font-weight: bold; }
        .sheet tr.sum td.v { border-top: 1px solid #000; }
        .sheet tr.box td.v { border: 0.6px solid #000; font-weight: bold; }
        .sheet td.tot { font-weight: bold; }
        .notes { margin-top: 6px; font-size: 8px; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $fmt = fn (float $value): string => $value == 0.0 ? '-' : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
        $keys = array_column($lubricants, 'key');
        $sum = fn (array $amounts): float => array_sum(array_map(fn (string $key): float => (float) ($amounts[$key] ?? 0), $keys));
        $tgl = fn (?string $date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') : '';
        $row = function (string $no, string $label, string $sub, array $amounts, float $total, string $class = '') use ($lubricants, $fmt): string {
            $cells = '';
            foreach ($lubricants as $lubricant) {
                $cells .= '<td class="v">'.e($fmt((float) ($amounts[$lubricant['key']] ?? 0))).'</td>';
            }

            return '<tr class="'.$class.'"><td class="no">'.e($no).'</td><td class="lbl">'.e($label).'</td><td class="sub">'.e($sub).'</td>'.$cells.'<td class="v tot">'.e($fmt($total)).'</td></tr>';
        };
        $field = fn (string $name): array => array_map(fn (array $row): float => $row[$name], array_intersect_key($totals, array_flip($keys)));
    @endphp

    <div class="title">PERINCIAAN MINYAK PELUMAS<br>BULAN {{ strtoupper($period_label) }}</div>

    <table class="sheet">
        <thead>
            <tr>
                <th colspan="3" style="border: none;"></th>
                @foreach ($lubricants as $lubricant)
                    <th>{{ strtoupper($lubricant['name']) }}</th>
                @endforeach
                <th>TOTAL</th>
            </tr>
            <tr>
                <th colspan="3" style="border: none;"></th>
                @foreach ($lubricants as $lubricant)
                    <th>{{ $lubricant['code'] ?: $lubricant['unit_label'] }}</th>
                @endforeach
                <th>PELUMAS</th>
            </tr>
        </thead>
        <tbody>
            {!! $row('I.', 'Persediaan Awal', '', $field('awal'), $totals['total']['awal'], 'sec box') !!}
            <tr class="sec"><td class="no">II.</td><td class="lbl" colspan="2">Penerimaan</td>@foreach ($lubricants as $l)<td class="v"></td>@endforeach<td class="v"></td></tr>
            @forelse ($auto['penerimaan'] as $index => $line)
                {!! $row((string) ($index + 1), $manual['asal'][$line['day']] ?? 'Penerimaan', 'tgl '.str_pad((string) $line['day'], 2, '0', STR_PAD_LEFT).'/'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'/'.$year, $line['amounts'], $sum($line['amounts'])) !!}
            @empty
                {!! $row('1', '-', 'tgl', [], 0) !!}
            @endforelse
            {!! $row('III', 'Pengembalian', 'tgl '.$tgl($manual['pengembalian']['tanggal']), $manual['pengembalian']['amounts'], $sum($manual['pengembalian']['amounts']), 'sec') !!}
            {!! $row('', 'Jumlah Penerimaan', '', $field('jumlah_penerimaan'), $totals['total']['jumlah_penerimaan'], 'sum') !!}
            {!! $row('IV', 'Total Persediaan', '', $field('total_persediaan'), $totals['total']['total_persediaan'], 'sec sum') !!}
            <tr class="sec"><td class="no">V</td><td class="lbl" colspan="2">Pemakaian Mesin</td>@foreach ($lubricants as $l)<td class="v"></td>@endforeach<td class="v"></td></tr>
            @foreach ($machines as $index => $machine)
                {!! $row((string) ($index + 1), $machine['name'], (string) $machine['type'], $auto['pemakaian'][$machine['id']] ?? [], $sum($auto['pemakaian'][$machine['id']] ?? [])) !!}
            @endforeach
            {!! $row('', 'Jumlah Pemakaian', '', $field('jumlah_pemakaian'), $totals['total']['jumlah_pemakaian'], 'sum') !!}
            {!! $row('', 'Pemakaian Non Mesin', '', $manual['non_mesin'], $sum($manual['non_mesin']), 'sum') !!}
            <tr class="sec"><td class="no">VI</td><td class="lbl" colspan="2">Pengiriman ke Unit</td>@foreach ($lubricants as $l)<td class="v"></td>@endforeach<td class="v"></td></tr>
            @php $no = 0; @endphp
            @foreach ($lines as $line => $label)
                {!! $row((++$no).'.', $label, 'tgl '.$tgl($manual['pengiriman'][$line]['tanggal']), $manual['pengiriman'][$line]['amounts'], $sum($manual['pengiriman'][$line]['amounts'])) !!}
            @endforeach
            @if ($sum($auto['kirim_persediaan']) > 0)
                {!! $row((++$no).'.', 'TUG 8 / TUG 10 / Over Flow', '(Persediaan Pelumas)', $auto['kirim_persediaan'], $sum($auto['kirim_persediaan'])) !!}
            @endif
            {!! $row('', 'Jumlah Pengiriman', '', $field('jumlah_pengiriman'), $totals['total']['jumlah_pengiriman'], 'sum') !!}
            {!! $row('VII', 'Sisa Pelumas sesuai perhitungan', '', $field('sisa'), $totals['total']['sisa'], 'sec box') !!}
            {!! $row('VIII', 'Sisa Pelumas persediaan akhir (Fisik)', '', $field('fisik'), $totals['total']['fisik'], 'sec box') !!}
            {!! $row('IX', 'Selisih', '', $field('selisih'), $totals['total']['selisih'], 'sec box') !!}
        </tbody>
    </table>

    @if ($catatan)
        <div class="notes"><b>Catatan:</b> {{ $catatan }}</div>
    @endif
</body>
</html>
