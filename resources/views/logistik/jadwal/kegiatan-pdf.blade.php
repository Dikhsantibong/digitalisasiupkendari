@php
    /**
     * PDF of the Logistik "Jadwal Kegiatan Logistik & Gudang": activities per
     * section with a mark per day (1 = rencana, ✓ = realisasi), target,
     * rencana, realisasi, kinerja and keterangan.
     *
     * @var array{title: string, kop: string} $sheet
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var list<array{col: int, label: string, is_red: bool}> $columns
     * @var list<array<string, mixed>> $rows
     * @var list<array{key: string, number: string, title: string}> $sections
     */
    $grouped = collect($rows)->groupBy(fn (array $row): string => $row['section'] ?? 'non-rutin');
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
                <th rowspan="2" style="width: 20px;">No</th>
                <th rowspan="2" style="width: 150px;">Nama Peralatan</th>
                <th colspan="{{ count($columns) }}">{{ strtoupper($periodLabel) }}</th>
                <th rowspan="2" style="width: 34px;">TARGET</th>
                <th rowspan="2" style="width: 38px;">RENCANA</th>
                <th rowspan="2" style="width: 40px;">REALISASI</th>
                <th rowspan="2" style="width: 40px;">A. KINERJA</th>
                <th rowspan="2" style="width: 90px;">Keterangan</th>
            </tr>
            <tr>
                @foreach($columns as $column)
                    <th class="day" @if($column['is_red']) style="color: #ff0000;" @endif>{{ $column['col'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($sections as $section)
                <tr class="section">
                    <td class="c">{{ $section['number'] }}</td>
                    <td colspan="{{ count($columns) + 6 }}">{{ $section['title'] }}</td>
                </tr>
                @foreach($grouped->get($section['key'], []) as $row)
                    <tr>
                        <td class="c">{{ $loop->iteration }}</td>
                        <td>{{ $row['nama'] }}</td>
                        @foreach($columns as $column)
                            @php $code = $row['days']->{$column['col']} ?? null; @endphp
                            <td class="c {{ $column['is_red'] ? 'red' : ($code === 'D' ? 'done' : '') }}">{{ $code === 'D' ? '✓' : ($code === 'R' ? '1' : '') }}</td>
                        @endforeach
                        <td class="c b">{{ $row['summary']['target'] }}</td>
                        <td class="c b">{{ $row['summary']['rencana'] }}</td>
                        <td class="c b">{{ $row['summary']['realisasi'] }}</td>
                        <td class="c b">{{ $row['summary']['kinerja'] }}</td>
                        <td>{{ $row['keterangan'] }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    <table class="legend">
        <tr><td>1 = Rencana</td><td>✓ = Realisasi</td><td class="red">Sabtu, Minggu &amp; Hari Libur Nasional</td></tr>
    </table>
</body>
</html>
