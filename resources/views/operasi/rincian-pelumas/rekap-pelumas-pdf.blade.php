<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pemakaian Pelumas &amp; Grease - {{ $unit->name }}</title>
    <style>
        @page { margin: 8mm 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 7.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 13px; line-height: 1.4; margin: 4px 0 6px; }
        .unit { font-family: 'Courier New', monospace; font-weight: bold; font-size: 9px; margin-bottom: 2px; }
        .sheet th, .sheet td { border: 0.6px solid #000; padding: 1.5px 3px; }
        .sheet th { font-size: 7px; text-align: center; }
        .sheet td { text-align: right; }
        .sheet td.c { text-align: center; }
        .sheet td.l { text-align: left; }
        .sheet tr.sum td { font-weight: bold; border-top: 1.1px solid #000; }
        .sheet td.lbl { text-align: center; font-weight: bold; }
        .notes { margin-top: 6px; font-size: 8px; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $fmt = fn (float $value): string => $value == 0.0 ? '-' : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
        $grease = array_column(array_filter($lubricants, fn (array $l): bool => $l['unit_of_measure'] === 'kg'), 'key');
        $split = function (array $amounts) use ($lubricants, $grease): array {
            $pelumas = 0.0;
            $kg = 0.0;
            foreach ($lubricants as $l) {
                if (in_array($l['key'], $grease, true)) {
                    $kg += (float) ($amounts[$l['key']] ?? 0);
                } else {
                    $pelumas += (float) ($amounts[$l['key']] ?? 0);
                }
            }

            return [$pelumas, $kg];
        };
        $cells = function (array $amounts) use ($lubricants, $fmt, $split): string {
            $html = '';
            foreach ($lubricants as $l) {
                $html .= '<td>'.e($fmt((float) ($amounts[$l['key']] ?? 0))).'</td>';
            }
            [$pelumas, $kg] = $split($amounts);

            return $html.'<td><b>'.e($fmt($pelumas)).'</b></td><td><b>'.e($fmt($kg)).'</b></td>';
        };
        $field = fn (string $name): array => array_map(fn (array $l): float => $totals[$l['key']][$name], array_combine(array_column($lubricants, 'key'), $lubricants));
        $machineCount = count($machines);
    @endphp

    <div class="title">PEMAKAIAN PELUMAS &amp; GREASE<br>BULAN {{ strtoupper($period_label) }}</div>
    <div class="unit">{{ strtoupper($unit->name) }}</div>

    <table class="sheet">
        <thead>
            <tr>
                <th colspan="5">DATA PEMBANGKIT</th>
                @foreach ($lubricants as $l)
                    <th rowspan="2">{{ $l['name'] }}@if ($l['code'])<br>{{ $l['code'] }}@endif</th>
                @endforeach
                <th rowspan="2">TOTAL (LTR)<br>PELUMAS</th>
                <th rowspan="2">TOTAL (KG)<br>GREASE</th>
            </tr>
            <tr>
                <th style="width: 3%;">NO</th>
                <th style="width: 6%;">NO. UNIT</th>
                <th>MERK</th>
                <th>TYPE</th>
                <th>NO. SERI</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="5" class="lbl">PERSEDIAAN AWAL</td>{!! $cells($field('awal')) !!}</tr>
            <tr><td colspan="5" class="lbl">PENERIMAAN</td>{!! $cells($field('penerimaan')) !!}</tr>
            <tr class="sum"><td colspan="5" class="lbl">TOTAL</td>{!! $cells($field('total')) !!}</tr>
            @foreach ($machines as $index => $machine)
                <tr>
                    <td class="c">{{ $index + 1 }}</td>
                    <td class="c">{{ $machine['name'] }}</td>
                    <td class="c">{{ $machine['merk'] ?: '-' }}</td>
                    <td class="c">{{ $machine['type'] ?: '-' }}</td>
                    <td class="c">{{ $machine['serial_number'] ?: '-' }}</td>
                    {!! $cells($auto['pemakaian'][$machine['id']] ?? []) !!}
                </tr>
            @endforeach
            <tr class="sum"><td colspan="5" class="l">(A) JUMLAH PEMAKAIAN MESIN</td>{!! $cells($field('mesin')) !!}</tr>
            @php $no = 0; @endphp
            @foreach ($lines as $line => $label)
                <tr>
                    <td class="c">{{ ++$no }}</td>
                    <td colspan="4" class="l">{{ strtoupper($label) }}</td>
                    {!! $cells($line === 'pengiriman' ? $auto['kirim_persediaan'] : ($manual['alat_bantu'][$line] ?? [])) !!}
                </tr>
            @endforeach
            <tr class="sum"><td colspan="5" class="l">(B) JUMLAH PEMAKAIAN ALAT BANTU</td>{!! $cells($field('alat_bantu')) !!}</tr>
            <tr class="sum"><td colspan="5" class="lbl">TOTAL (A+B)</td>{!! $cells($field('pemakaian')) !!}</tr>
            <tr class="sum"><td colspan="5" class="lbl">SISA PERSEDIAAN AKHIR/KARTU</td>{!! $cells($field('kartu')) !!}</tr>
            <tr class="sum"><td colspan="5" class="lbl">SISA PERSEDIAAN AKHIR/FISIK</td>{!! $cells($field('fisik')) !!}</tr>
            <tr class="sum"><td colspan="5" class="lbl">S E L I S I H</td>{!! $cells($field('selisih')) !!}</tr>
        </tbody>
    </table>

    @if ($catatan)
        <div class="notes"><b>Catatan:</b> {{ $catatan }}</div>
    @endif
</body>
</html>
