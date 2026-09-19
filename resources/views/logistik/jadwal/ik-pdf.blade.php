@php
    /**
     * PDF of the Logistik "Jadwal Pembuatan Instruksi Kerja (IK)": one IK per
     * row with a mark per month (1 = rencana, ✓ = realisasi), jumlah, total IK
     * and the rencana / realisasi recap.
     *
     * @var array{title: string, kop: string} $sheet
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var list<array{col: int, label: string}> $columns
     * @var list<array<string, mixed>> $rows
     */
    $planned = collect($rows)->filter(fn (array $row): bool => $row['summary']['rencana'] > 0)->count();
    $done = collect($rows)->filter(fn (array $row): bool => $row['summary']['realisasi'] > 0)->count();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['title'] }} - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('logistik.jadwal.partials.styles')
    <style>
        table.grid td { height: 12px; }
        .recap { border-collapse: collapse; margin-top: 10px; width: 380px; }
        .recap th, .recap td { border: 1px solid #000; padding: 1px 4px; font-size: 7px; }
        .recap th { background: #5bc8f5; text-align: left; }
    </style>
</head>
<body>
    @include('logistik.jadwal.partials.kop-table')
    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="width: 20px;">NO</th>
                <th rowspan="2" style="width: 210px;">INSTRUKSI KERJA</th>
                <th rowspan="2" style="width: 110px;">PIC PEMBUAT</th>
                <th colspan="{{ count($columns) }}">BULAN — {{ strtoupper($periodLabel) }}</th>
                <th rowspan="2" style="width: 38px;">JUMLAH</th>
            </tr>
            <tr>
                @foreach($columns as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr class="section">
                <td class="c">A.</td>
                <td colspan="{{ count($columns) + 3 }}">PEMBUATAN INSTRUKSI KERJA</td>
            </tr>
            @foreach($rows as $index => $row)
                <tr>
                    <td class="c">{{ $index + 1 }}</td>
                    <td>{{ $row['nama'] !== '' ? $row['nama'] : 'IK..........................................................' }}</td>
                    <td>{{ $row['pic'] }}</td>
                    @foreach($columns as $column)
                        @php $code = $row['days']->{$column['col']} ?? null; @endphp
                        <td class="c {{ $code === 'D' ? 'done' : '' }}">{{ $code === 'D' ? '✓' : ($code === 'R' ? '1' : '') }}</td>
                    @endforeach
                    <td class="c b">{{ $row['summary']['rencana'] }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="{{ count($columns) + 3 }}" class="c b">TOTAL IK</td>
                <td class="c b">{{ collect($rows)->sum(fn (array $row): int => $row['summary']['rencana']) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="recap">
        <tr><th style="width: 20px;">A.</th><th>IK</th><th style="width: 50px;">NILAI</th><th style="width: 50px;">A. DATA</th></tr>
        <tr><td class="c">1</td><td>RENCANA</td><td class="c">{{ $planned }}</td><td rowspan="2" class="c b">{{ $planned > 0 ? round($done / $planned * 100).'%' : '0%' }}</td></tr>
        <tr><td class="c">2</td><td>REALISASI</td><td class="c">{{ $done }}</td></tr>
    </table>
</body>
</html>
