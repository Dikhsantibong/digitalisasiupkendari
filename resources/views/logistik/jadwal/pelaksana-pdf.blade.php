@php
    /**
     * PDF of the Logistik jadwal sheets with one pelaksana per row and a
     * RENCANA / REALISASI line (Piket Patrol Check On Call, 5S5R, Meeting,
     * Inventarisasi Tools & Material).
     *
     * @var array{title: string, kop: string} $sheet
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var list<array{col: int, dow: string, is_red: bool}> $columns
     * @var list<array<string, mixed>> $rows
     */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['title'] }} - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('logistik.jadwal.partials.styles')
</head>
<body>
    @include('logistik.jadwal.partials.kop-table')
    <table class="grid">
        <thead>
            <tr>
                <th rowspan="3" style="width: 120px;">{{ $sheet['row_label'] }}</th>
                <th rowspan="3" style="width: 60px;">RENCANA / REALISASI</th>
                <th colspan="{{ count($columns) }}">BULAN {{ strtoupper($periodLabel) }}</th>
                <th rowspan="3" style="width: 38px;">RENCANA</th>
                <th rowspan="3" style="width: 34px;">TARGET</th>
                <th rowspan="3" style="width: 40px;">REALISASI</th>
                <th rowspan="3" style="width: 40px;">A. KINERJA</th>
            </tr>
            <tr>
                @foreach($columns as $column)
                    <th class="day" @if($column['is_red']) style="color: #ff0000;" @endif>{{ $column['dow'] }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($columns as $column)
                    <th class="day" @if($column['is_red']) style="color: #ff0000;" @endif>{{ $column['col'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td rowspan="2" class="b">{{ $row['nama'] }}</td>
                    <td>RENCANA</td>
                    @foreach($columns as $column)
                        @php $code = $row['days']->{$column['col']} ?? null; @endphp
                        <td class="c">{{ in_array($code, ['R', 'D'], true) ? '1' : '' }}</td>
                    @endforeach
                    <td rowspan="2" class="c b">{{ $row['summary']['rencana'] }}</td>
                    <td rowspan="2" class="c b" style="color: #c00000;">{{ $row['summary']['target'] }}</td>
                    <td rowspan="2" class="c b">{{ $row['summary']['realisasi'] }}</td>
                    <td rowspan="2" class="c b">{{ $row['summary']['kinerja'] }}</td>
                </tr>
                <tr>
                    <td>REALISASI</td>
                    @foreach($columns as $column)
                        @php $code = $row['days']->{$column['col']} ?? null; @endphp
                        <td class="c {{ $code === 'D' ? 'done' : '' }}">{{ $code === 'D' ? '1' : '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
