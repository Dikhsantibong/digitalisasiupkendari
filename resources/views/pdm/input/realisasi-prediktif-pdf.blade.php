@php
    /**
     * PDF (A4 landscape) Input PdM "Realisasi Pemeliharaan Prediktif Bulanan".
     *
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var list<array{day: int, dow: string, is_red: bool}> $days
     * @var list<array<string, mixed>> $rows
     * @var array<string, string> $meta
     */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Realisasi Pemeliharaan Prediktif Bulanan - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('pdm.input.partials.styles')
    <style>
        @page { size: A4 landscape; margin: 10mm 8mm 16mm 8mm; }
        table.grid td.day, table.grid th.day { width: 13px; padding: 1px 0; text-align: center; font-size: 6.5px; }
        .red { background: #ff0000 !important; }
        .st-r { background: #00b0f0; font-weight: bold; text-align: center; font-size: 6.5px; }
        .st-rl { background: #92d050; font-weight: bold; text-align: center; font-size: 6.5px; }
        .doc { border-collapse: collapse; font-size: 7.5px; }
        .doc td { padding: 1px 4px; }
    </style>
</head>
<body>
    @include('pdm.input.partials.kop', \App\Support\PdmInputKop::for('realisasi-prediktif', $unit->name))

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 4px;">
        <tr>
            <td class="b" style="font-size: 8px; font-weight: bold;">SENTRAL {{ strtoupper($meta['sentral']) }}</td>
            <td style="width: 180px;">
                <table class="doc">
                    <tr><td>No. Dokumen</td><td>: {{ $meta['doc_number'] }}</td></tr>
                    <tr><td>Revisi</td><td>: {{ $meta['revision'] }}</td></tr>
                    <tr><td>Tanggal</td><td>: {{ $meta['effective_date'] }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="width: 18px;">NO</th>
                <th rowspan="2" style="width: 90px;">URAIAN</th>
                <th rowspan="2" style="width: 70px;">MESIN / TIPE / S.N</th>
                <th rowspan="2" style="width: 36px;">STATUS</th>
                <th colspan="{{ count($days) }}">{{ strtoupper($periodLabel) }}</th>
                <th rowspan="2" style="width: 36px;">DURASI</th>
                <th rowspan="2" style="width: 34px;">TARGET</th>
                <th rowspan="2" style="width: 38px;">REALISASI</th>
                <th rowspan="2" style="width: 38px;">A. KINERJA</th>
            </tr>
            <tr>
                @foreach($days as $day)
                    <th class="day {{ $day['is_red'] ? 'red' : '' }}">{{ $day['day'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td rowspan="2" class="c">{{ $loop->iteration }}</td>
                    <td rowspan="2" style="font-weight: bold;">{{ $row['uraian'] }}</td>
                    <td rowspan="2" class="c" style="font-weight: bold;">{{ $row['mesin'] }}</td>
                    <td class="st-r">RENCANA</td>
                    @foreach($days as $day)
                        <td class="day {{ $day['is_red'] ? 'red' : '' }}">{{ in_array($day['day'], $row['rencana'], true) ? '1' : '' }}</td>
                    @endforeach
                    <td rowspan="2" class="c">{{ $row['durasi'] !== null ? rtrim(rtrim(number_format($row['durasi'], 2, ',', '.'), '0'), ',') : '' }}</td>
                    <td rowspan="2" class="c">{{ $row['target'] }}</td>
                    <td rowspan="2" class="c">{{ $row['realisasi_count'] }}</td>
                    <td rowspan="2" class="c">{{ $row['kinerja'] }}</td>
                </tr>
                <tr>
                    <td class="st-rl">REAL</td>
                    @foreach($days as $day)
                        <td class="day {{ $day['is_red'] ? 'red' : '' }}">{{ in_array($day['day'], $row['realisasi'], true) ? '1' : '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
