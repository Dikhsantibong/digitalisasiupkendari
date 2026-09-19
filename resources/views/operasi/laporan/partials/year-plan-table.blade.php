@php
    /**
     * Yearly month plan (Jadwal pembuatan IK / data teknis); the report month is highlighted.
     *
     * @var list<array{nama: string, pic: string, months: array<string, mixed>, jumlah: int}> $rows
     * @var string $namaHeader
     * @var int $month
     * @var list<string> $monthsShort
     */
@endphp
<table class="op-data">
    <tr>
        <th rowspan="2" style="width: 24px;">No</th>
        <th rowspan="2">{{ $namaHeader }}</th>
        <th rowspan="2" style="width: 100px;">PIC Pembuat</th>
        <th colspan="12">Bulan</th>
        <th rowspan="2" style="width: 40px;">Jumlah</th>
    </tr>
    <tr>
        @foreach($monthsShort as $index => $label)
            <th class="{{ $index + 1 === $month ? 'op-current' : '' }}" style="width: 28px;">{{ $label }}</th>
        @endforeach
    </tr>
    @foreach($rows as $idx => $row)
        <tr>
            <td class="c">{{ $idx + 1 }}</td>
            <td>{{ $row['nama'] }}</td>
            <td class="c">{{ $row['pic'] ?: '—' }}</td>
            @for($m = 1; $m <= 12; $m++)
                <td class="c {{ ! empty($row['months'][(string) $m]) ? 'op-done' : '' }}">{{ ! empty($row['months'][(string) $m]) ? '1' : '' }}</td>
            @endfor
            <td class="c">{{ $row['jumlah'] }}</td>
        </tr>
    @endforeach
</table>
