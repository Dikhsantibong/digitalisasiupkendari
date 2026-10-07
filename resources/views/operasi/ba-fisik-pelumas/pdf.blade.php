<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>BA Pemeriksaan Fisik Pelumas - {{ $unit->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm 14mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-weight: bold; font-size: 12.5px; line-height: 1.5; margin: 6px 0 10px; }
        .opening { margin: 8px 0 10px; line-height: 1.5; }
        .sheet th, .sheet td { border: 0.7px solid #000; padding: 4px 4px; }
        .sheet th { background: #c9d7ea; font-weight: normal; font-size: 9px; text-align: center; }
        .sheet td { text-align: center; }
        .sheet td.name { text-align: left; }
        .sheet td.sel { text-align: right; }
        .sheet tr.total td { font-weight: bold; }
        .sheet td.shade { background: #bbbbbb; }
        .closing { margin-top: 8px; }
        .sign { width: 100%; margin-top: 6px; }
        .sign td { width: 50%; text-align: center; vertical-align: top; }
    </style>
</head>
<body>
    @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])

    @php
        $fmt = fn (?float $value): string => $value === null ? '' : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
        $sum = fn (string $field): float => array_sum(array_map(fn (array $row): float => $row[$field], $rows));
        $liters = array_filter(array_map(fn (array $item): ?float => $item['liter'], $items), fn (?float $value): bool => $value !== null);
    @endphp

    <div class="title">
        BERITA ACARA<br>PEMERIKSAAN FISIK PELUMAS<br>{{ strtoupper($unit->name) }}<br>
        NO : {{ $header['nomor'] ?: '..................................' }}
    </div>

    <div class="opening">{{ $opening }}</div>

    <table class="sheet">
        <thead>
            <tr>
                <th rowspan="2" style="width: 15%;">JENIS PELUMAS</th>
                <th rowspan="2">PERSEDIAAN AWAL PER {{ strtoupper($month_start) }}</th>
                <th rowspan="2">PENERIMAAN S/D {{ strtoupper($tanggal_label) }}</th>
                <th rowspan="2">STOCK S/D {{ strtoupper($tanggal_label) }} PUKUL {{ $header['pukul'] }} WITA</th>
                <th rowspan="2">PEMK. SENDIRI S/D {{ strtoupper($tanggal_label) }}</th>
                <th rowspan="2">PENGIRIMAN (TUG. 8)</th>
                <th rowspan="2">PERSEDIAAN MENURUT PENCATATAN ADMINISTRASI</th>
                <th colspan="3">STOCK FISIK</th>
                <th rowspan="2">SELISIH FISIK - ADMINISTRASI</th>
            </tr>
            <tr><th>drum</th><th>cm</th><th>liter</th></tr>
        </thead>
        <tbody>
            @foreach ($lubricants as $lubricant)
                @php
                    $row = $rows[$lubricant['key']];
                    $item = $items[$lubricant['key']];
                @endphp
                <tr>
                    <td class="name">{{ $lubricant['name'] }}@if ($lubricant['code']) {{ $lubricant['code'] }}@endif</td>
                    <td>{{ $fmt($row['awal']) }}</td>
                    <td>{{ $fmt($row['penerimaan']) }}</td>
                    <td>{{ $fmt($row['stock']) }}</td>
                    <td>{{ $fmt($row['pemakaian']) }}</td>
                    <td>{{ $fmt($row['pengiriman']) }}</td>
                    <td>{{ $fmt($row['administrasi']) }}</td>
                    <td>{{ $fmt($item['drum']) }}</td>
                    <td>{{ $fmt($item['cm']) }}</td>
                    <td>{{ $fmt($item['liter']) }}</td>
                    <td class="sel">{{ $item['liter'] === null ? '' : $fmt(round($item['liter'] - $row['administrasi'], 2)) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td class="name">JUMLAH TOTAL</td>
                <td>{{ $fmt($sum('awal')) }}</td>
                <td>{{ $fmt($sum('penerimaan')) }}</td>
                <td>{{ $fmt($sum('stock')) }}</td>
                <td>{{ $fmt($sum('pemakaian')) }}</td>
                <td>{{ $fmt($sum('pengiriman')) }}</td>
                <td>{{ $fmt($sum('administrasi')) }}</td>
                <td class="shade"></td>
                <td class="shade"></td>
                <td>{{ $liters === [] ? '' : $fmt(array_sum($liters)) }}</td>
                <td class="sel">{{ $liters === [] ? '' : $fmt(round(array_sum($liters) - array_sum(array_map(fn (string $key): float => $rows[$key]['administrasi'], array_keys($liters))), 2)) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="closing">Demikian Berita Acara ini dibuat untuk digunakan sebagaimana mestinya.</div>
    @if ($catatan)
        <div class="closing"><b>Catatan:</b> {{ $catatan }}</div>
    @endif

    <table class="sign">
        <tr><td></td><td>Kendari, {{ $tanggal_label }}</td></tr>
        <tr><td>Mengetahui<br>{{ $header['mengetahui_jabatan'] }}</td><td>Dibuat<br>{{ $header['dibuat_jabatan'] }}</td></tr>
        <tr><td style="height: 55px;"></td><td></td></tr>
        <tr><td>{{ strtoupper($header['mengetahui'] ?: '....................................') }}</td><td>{{ strtoupper($header['dibuat'] ?: '....................................') }}</td></tr>
    </table>
</body>
</html>
