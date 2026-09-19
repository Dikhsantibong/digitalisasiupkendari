@php
    /**
     * PDF of the Logistik input "Laporan Patrol Checklist Logistik & Gudang":
     * per item & day an N (normal → 0 in the N column) or T (tidak normal → 1
     * in the T column), with hasil temuan and pelaksanaan per item and totals.
     *
     * @var array{title: string, kop: string} $sheet
     * @var \App\Models\Unit $unit
     * @var int $month
     * @var int $year
     * @var list<array{col: int}> $columns
     * @var list<array<string, mixed>> $rows
     */
    $total = fn (string $key): int => (int) collect($rows)->sum(fn (array $row): int => $row['summary'][$key]);
    $rencana = $total('rencana');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['title'] }} - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('logistik.jadwal.partials.styles')
    <style>
        body { font-size: 5.5px; }
        th.nt { width: 7px; font-size: 5px; padding: 0; }
        table.grid td { height: 14px; padding: 0 1px; }
        .info { border-collapse: collapse; margin-bottom: 3px; width: 100%; }
        .info td { font-weight: bold; font-size: 6.5px; padding: 1px 4px; }
    </style>
</head>
<body>
    @include('logistik.jadwal.partials.kop-table')
    <table class="info">
        <tr>
            <td>N = NORMAL, SIAP, BAIK</td>
            <td>T = TDK NORMAL, TDK SIAP, KOTOR</td>
            <td style="text-align: right;">TANGGAL 1-{{ count($columns) }} &nbsp; · &nbsp; BULAN {{ strtoupper(\App\Support\Indonesian::monthName($month)) }} &nbsp; · &nbsp; TAHUN {{ $year }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="3" style="width: 14px;">NO</th>
                <th rowspan="3" style="width: 110px;">Item &amp; Area Pemeriksaan</th>
                <th colspan="{{ count($columns) * 2 }}">PATROL CHECKLIST LOGISTIK &amp; GUDANG</th>
                <th colspan="2" rowspan="2">HASIL TEMUAN</th>
                <th colspan="3" rowspan="2">PELAKSANAAN</th>
            </tr>
            <tr>
                @foreach($columns as $column)
                    <th colspan="2">{{ $column['col'] }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($columns as $column)
                    <th class="nt">N</th><th class="nt">T</th>
                @endforeach
                <th style="width: 24px;">NORMAL</th>
                <th style="width: 26px;">T. NORMAL</th>
                <th style="width: 18px;">RNC</th>
                <th style="width: 18px;">REAL</th>
                <th style="width: 22px;">HASIL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $index => $row)
                <tr>
                    <td class="c">{{ $index + 1 }}</td>
                    <td>{{ $row['nama'] }}</td>
                    @foreach($columns as $column)
                        @php $code = $row['days']->{$column['col']} ?? null; @endphp
                        <td class="c">{{ $code === 'N' ? '0' : '' }}</td>
                        <td class="c" @if($code === 'T') style="background: #ffc7ce; font-weight: bold;" @endif>{{ $code === 'T' ? '1' : '' }}</td>
                    @endforeach
                    <td class="c">{{ $row['summary']['normal'] }}</td>
                    <td class="c">{{ $row['summary']['tidak_normal'] }}</td>
                    <td class="c">{{ $row['summary']['rencana'] }}</td>
                    <td class="c">{{ $row['summary']['realisasi'] }}</td>
                    <td class="c">{{ $row['summary']['hasil'] }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="{{ count($columns) * 2 + 2 }}" style="text-align: right;" class="b">TOTAL</td>
                <td class="c b">{{ $total('normal') }}</td>
                <td class="c b">{{ $total('tidak_normal') }}</td>
                <td class="c b">{{ $rencana }}</td>
                <td class="c b">{{ $total('realisasi') }}</td>
                <td class="c b">{{ $rencana > 0 ? round($total('realisasi') / $rencana * 100).'%' : '-' }}</td>
            </tr>
        </tbody>
    </table>

    <table class="legend">
        <tr><td class="b">CATATAN :</td><td>1. Isilah angka 0 jika temuan pemeriksaan normal</td></tr>
        <tr><td></td><td>2. Isilah angka 1 jika temuan pemeriksaan tidak normal</td></tr>
    </table>
</body>
</html>
