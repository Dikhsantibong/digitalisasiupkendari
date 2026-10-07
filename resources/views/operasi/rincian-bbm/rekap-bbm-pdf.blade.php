<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pemakaian Bahan Bakar - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 7mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 7px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 12px; line-height: 1.4; margin: 4px 0 6px; }
        .unit { font-family: 'Courier New', monospace; font-weight: bold; font-size: 9px; margin-bottom: 2px; }
        .sheet th, .sheet td { border: 0.6px solid #000; padding: 1.5px 2.5px; }
        .sheet th { font-size: 6.5px; text-align: center; }
        .sheet td { text-align: right; }
        .sheet td.c { text-align: center; }
        .sheet td.lbl { text-align: center; font-weight: bold; }
        .sheet tr.sum td { font-weight: bold; background: #eeeeee; }
        .sheet .tot { background: #f4f4f4; font-weight: bold; }
        .sign { margin-top: 10px; width: 30%; margin-left: 70%; text-align: center; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $fmt = fn (float $value): string => $value == 0.0 ? '-' : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
        $codes = array_column($fuels, 'key');
        $groups = ['mesin' => 'PEMAKAIAN MESIN'] + array_map('strtoupper', $lain_labels);
        // A stock row shows its value only in the TOTAL group.
        $stock = function (array $values) use ($codes, $groups, $fmt): string {
            $html = '';
            foreach ($groups as $group) {
                foreach ($codes as $code) {
                    $html .= '<td></td>';
                }
            }
            foreach ($codes as $code) {
                $html .= '<td class="tot">'.e($fmt($values[$code] ?? 0)).'</td>';
            }

            return $html;
        };
        $field = fn (string $name): array => array_map(fn (array $row): float => $row[$name], $totals);
    @endphp

    <div class="title">PEMAKAIAN BAHAN BAKAR {{ implode(' & ', $codes) }}<br>BULAN {{ strtoupper($period_label) }}</div>
    <div class="unit">{{ strtoupper($unit->name) }}</div>

    <table class="sheet">
        <thead>
            <tr>
                <th colspan="5">DATA PEMBANGKIT</th>
                <th colspan="{{ (count($groups) + 1) * count($codes) }}">JENIS PEMAKAIAN</th>
            </tr>
            <tr>
                <th rowspan="2">NO</th><th rowspan="2">UNIT</th><th rowspan="2">MERK</th><th rowspan="2">TYPE</th><th rowspan="2">NO. SERI</th>
                @foreach ($groups as $label)
                    <th colspan="{{ count($codes) }}">{{ $label }}</th>
                @endforeach
                <th colspan="{{ count($codes) }}">TOTAL</th>
            </tr>
            <tr>
                @for ($g = 0; $g <= count($groups); $g++)
                    @foreach ($codes as $code)
                        <th>{{ $code }} (LTR)</th>
                    @endforeach
                @endfor
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="5" class="lbl">PERSEDIAAN AWAL</td>{!! $stock($field('awal')) !!}</tr>
            <tr><td colspan="5" class="lbl">PENERIMAAN</td>{!! $stock($field('penerimaan')) !!}</tr>
            <tr><td colspan="5" class="lbl">TUG 10 PENGEMBALIAN</td>{!! $stock($field('pengembalian')) !!}</tr>
            <tr class="sum"><td colspan="5" class="lbl">TOTAL</td>{!! $stock($field('total_persediaan')) !!}</tr>
            @foreach ($machines as $i => $machine)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $machine['name'] }}</td>
                    <td class="c">{{ $machine['merk'] ?: '-' }}</td>
                    <td class="c">{{ $machine['type'] ?: '-' }}</td>
                    <td class="c">{{ $machine['serial_number'] ?: '-' }}</td>
                    @php
                        $mesin = $auto['pemakaian'][$machine['id']] ?? [];
                        $lain = [];
                        foreach ($codes as $code) {
                            foreach (array_keys($lain_labels) as $key) {
                                $lain[$code][$key] = (float) ($manual[$code]['lain'][$machine['id']][$key] ?? 0);
                            }
                        }
                    @endphp
                    @foreach ($codes as $code)
                        <td>{{ $fmt((float) ($mesin[$code] ?? 0)) }}</td>
                    @endforeach
                    @foreach (array_keys($lain_labels) as $key)
                        @foreach ($codes as $code)
                            <td>{{ $fmt($lain[$code][$key]) }}</td>
                        @endforeach
                    @endforeach
                    @foreach ($codes as $code)
                        <td class="tot">{{ $fmt((float) ($mesin[$code] ?? 0) + array_sum($lain[$code])) }}</td>
                    @endforeach
                </tr>
            @endforeach
            <tr class="sum">
                <td colspan="5" class="lbl">TOTAL PEMAKAIAN</td>
                @foreach (array_keys($groups) as $key)
                    @foreach ($codes as $code)
                        <td>{{ $fmt($totals[$code][$key]) }}</td>
                    @endforeach
                @endforeach
                @foreach ($codes as $code)
                    <td>{{ $fmt($totals[$code]['jumlah_pemakaian']) }}</td>
                @endforeach
            </tr>
            <tr><td colspan="5" class="lbl">TUG 8 KE UNIT LAIN</td>{!! $stock($field('pengiriman')) !!}</tr>
            @if (array_sum($field('koreksi')) > 0)
                <tr><td colspan="5" class="lbl">KOREKSI / PEMINJAMAN</td>{!! $stock($field('koreksi')) !!}</tr>
            @endif
            <tr class="sum"><td colspan="5" class="lbl">SISA PERSEDIAAN AKHIR (PERHITUNGAN)</td>{!! $stock($field('sisa')) !!}</tr>
            <tr class="sum"><td colspan="5" class="lbl">SISA PERSEDIAAN AKHIR / FISIK</td>{!! $stock($field('fisik')) !!}</tr>
            <tr><td colspan="5" class="lbl">S E L I S I H</td>{!! $stock($field('selisih')) !!}</tr>
        </tbody>
    </table>

    <div class="sign">
        Kendari, {{ $signed_at }}<br>MANAJER<br><br><br><br>
        ( {{ $manager ? strtoupper($manager) : '....................................' }} )
    </div>
</body>
</html>
