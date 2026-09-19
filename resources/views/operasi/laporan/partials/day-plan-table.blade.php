@php
    /**
     * Daily rencana/realisasi matrix (Jadwal 5S5R, Inventarisasi).
     *
     * @var list<array{pelaksana: string, rencana: list<int>, realisasi: list<int>, rencana_count: int, target: int, realisasi_count: int, performance: int}> $rows
     * @var string $labelHeader
     * @var int $daysInMonth
     */
@endphp
<table class="op-data op-grid">
    <tr>
        <th rowspan="2" style="width: 100px;">{{ $labelHeader }}</th>
        <th rowspan="2" style="width: 44px;">Status</th>
        <th colspan="{{ $daysInMonth }}">Tanggal</th>
        <th rowspan="2" style="width: 30px;">Renc</th>
        <th rowspan="2" style="width: 30px;">Target</th>
        <th rowspan="2" style="width: 30px;">Real</th>
        <th rowspan="2" style="width: 32px;">Kinerja</th>
    </tr>
    <tr>
        @for($d = 1; $d <= $daysInMonth; $d++)<th>{{ $d }}</th>@endfor
    </tr>
    @foreach($rows as $row)
        <tr>
            <td rowspan="2" class="op-name">{{ $row['pelaksana'] }}</td>
            <td class="c">RENC</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td class="c {{ in_array($d, $row['rencana'], true) ? 'op-plan' : '' }}">{{ in_array($d, $row['rencana'], true) ? '1' : '' }}</td>
            @endfor
            <td rowspan="2" class="c">{{ $row['rencana_count'] }}</td>
            <td rowspan="2" class="c">{{ $row['target'] }}</td>
            <td rowspan="2" class="c">{{ $row['realisasi_count'] }}</td>
            <td rowspan="2" class="c">{{ $row['performance'] }}%</td>
        </tr>
        <tr>
            <td class="c">REAL</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td class="c {{ in_array($d, $row['realisasi'], true) ? 'op-done' : '' }}">{{ in_array($d, $row['realisasi'], true) ? '1' : '' }}</td>
            @endfor
        </tr>
    @endforeach
</table>
