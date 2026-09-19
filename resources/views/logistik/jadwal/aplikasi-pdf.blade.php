@php
    /**
     * PDF of the Logistik input "Laporan Input Data Aplikasi Pembangkit": per
     * aplikasi a 1 on every day the data was input, target, realisasi, kinerja.
     *
     * @var array{title: string, kop: string, row_label: string} $sheet
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var list<array{col: int, is_red: bool}> $columns
     * @var list<array<string, mixed>> $rows
     */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['title'] }} - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('logistik.jadwal.partials.styles')
    <style>table.grid td { height: 15px; }</style>
</head>
<body>
    @include('logistik.jadwal.partials.kop-table')
    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="width: 20px;">No</th>
                <th rowspan="2" style="width: 130px;">{{ $sheet['row_label'] }}</th>
                <th colspan="{{ count($columns) }}">{{ strtoupper($periodLabel) }}</th>
                <th rowspan="2" style="width: 40px;">TARGET</th>
                <th rowspan="2" style="width: 44px;">REALISASI</th>
                <th rowspan="2" style="width: 44px;">A. KINERJA</th>
            </tr>
            <tr>
                @foreach($columns as $column)
                    <th class="day" @if($column['is_red']) style="color: #ff0000;" @endif>{{ $column['col'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $index => $row)
                <tr>
                    <td class="c">{{ $index + 1 }}</td>
                    <td>{{ $row['nama'] }}</td>
                    @foreach($columns as $column)
                        <td class="c">{{ ($row['days']->{$column['col']} ?? null) === 'D' ? '1' : '' }}</td>
                    @endforeach
                    <td class="c b">{{ $row['summary']['target'] }}</td>
                    <td class="c b">{{ $row['summary']['realisasi'] }}</td>
                    <td class="c b">{{ $row['summary']['kinerja'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
