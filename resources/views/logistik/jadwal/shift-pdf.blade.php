@php
    /**
     * PDF of the Logistik "Jadwal Shift Operator": a shift code per day, the
     * rekap absensi and the persentase kehadiran, plus the legend.
     *
     * @var array{title: string, kop: string} $sheet
     * @var \App\Models\Unit $unit
     * @var int $month
     * @var int $year
     * @var list<array{col: int, label: string, dow_en: string, is_weekend: bool, is_holiday: bool}> $columns
     * @var list<array<string, mixed>> $rows
     * @var array<string, string> $codes
     */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['title'] }} - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('logistik.jadwal.partials.styles')
    <style>
        th.day { width: 15px; font-size: 5px; padding: 1px 0; }
        .period { border-collapse: collapse; margin: 6px 0; }
        .period td { border: 1px solid #000; padding: 1px 6px; font-weight: bold; font-size: 7.5px; }
        td.pct { background: #92d050; font-weight: bold; }
    </style>
</head>
<body>
    @include('logistik.jadwal.partials.kop-table')
    <table class="period">
        <tr><td style="width: 60px;">BULAN</td><td style="width: 90px; text-align: right;">{{ strtoupper(\App\Support\Indonesian::monthName($month)) }}</td></tr>
        <tr><td>TAHUN</td><td style="text-align: right;">{{ $year }}</td></tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="width: 18px;">NO</th>
                <th rowspan="2" style="width: 80px;">PIC</th>
                <th rowspan="2" style="width: 80px;">NAMA</th>
                @foreach($columns as $column)
                    <th class="day {{ $column['is_holiday'] ? 'red' : ($column['is_weekend'] ? 'off' : '') }}">{{ substr($column['dow_en'], 0, 3) }}</th>
                @endforeach
                <th colspan="5">REKAP ABSENSI</th>
                <th rowspan="2" style="width: 44px;">PERSENTASE KEHADIRAN</th>
            </tr>
            <tr>
                @foreach($columns as $column)
                    <th class="day {{ $column['is_holiday'] ? 'red' : ($column['is_weekend'] ? 'off' : '') }}">{{ $column['label'] }}</th>
                @endforeach
                <th style="width: 22px;">PAGI</th>
                <th style="width: 22px;">SAKIT</th>
                <th style="width: 22px;">IZIN</th>
                <th style="width: 22px;">CUTI</th>
                <th style="width: 26px;">MANKIR</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $index => $row)
                <tr>
                    <td class="c b" style="height: 20px;">{{ $index + 1 }}</td>
                    <td class="c b">{{ $row['pic'] }}</td>
                    <td class="c b">{{ $row['nama'] }}</td>
                    @foreach($columns as $column)
                        @php $code = $row['days']->{$column['col']} ?? ''; @endphp
                        <td class="c b {{ $column['is_holiday'] ? 'red' : ($code === 'OF' ? 'off' : '') }}">{{ $code }}</td>
                    @endforeach
                    @foreach(['P', 'S', 'I', 'C', 'M'] as $code)
                        <td class="c b">{{ $row['summary'][$code] }}</td>
                    @endforeach
                    <td class="c pct">{{ $row['summary']['kehadiran'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="legend">
        <tr><td colspan="2" class="b">KETERANGAN</td></tr>
        <tr><td class="off" style="width: 130px;">Hari Libur Sabtu - Minggu</td><td></td></tr>
        <tr><td class="red">Hari Libur Nasional</td><td></td></tr>
        @foreach($codes as $code => $label)
            <tr><td class="b">{{ $code }}</td><td>= {{ $label }}</td></tr>
        @endforeach
    </table>
</body>
</html>
