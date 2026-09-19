@php
    /**
     * PDF of the Logistik input "Laporan Inspeksi Checklist 5S5R": A. identitas,
     * B. penilaian per 5S5R section (items × 15 pelaksanaan, rencana,
     * realisasi, a. data, eviden photos) and the akumulatif.
     *
     * @var array{title: string, kop: string, row_label: string} $sheet
     * @var \App\Models\Unit $unit
     * @var int $month
     * @var list<array{col: int}> $columns
     * @var list<array<string, mixed>> $rows
     * @var list<array{key: string, number: string, title: string, note: string}> $sections
     */
    $grouped = collect($rows)->groupBy(fn (array $row): string => (string) $row['section']);
    $percent = fn (int $done, int $of): string => $of > 0 ? round($done / $of * 100).'%' : '0%';
    $totals = collect($sections)->mapWithKeys(fn (array $section): array => [$section['key'] => [
        'rencana' => (int) collect($grouped->get($section['key'], []))->max(fn (array $row): int => $row['summary']['rencana']),
        'realisasi' => (int) collect($grouped->get($section['key'], []))->sum(fn (array $row): int => $row['summary']['realisasi']),
    ]]);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['title'] }} - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('logistik.jadwal.partials.styles')
    <style>
        body { font-size: 7px; }
        .heading { font-weight: bold; font-size: 8.5px; margin: 6px 0 1px 0; }
        .note { margin-bottom: 2px; }
        table.grid td { height: 34px; }
        td.photo { width: 70px; text-align: center; }
        td.photo img { max-width: 66px; max-height: 32px; }
        .ident { border-collapse: collapse; width: 300px; }
        .ident th, .ident td { border: 1px solid #000; padding: 1px 4px; }
        .ident th { background: #5bc8f5; }
        .section-block { page-break-inside: avoid; }
    </style>
</head>
<body>
    @include('logistik.jadwal.partials.kop-table')
    <div class="heading">A. IDENTITAS</div>
    <table class="ident">
        <tr><th style="width: 20px;">NO</th><th>Uraian</th><th>Keterangan</th></tr>
        <tr><td class="c">1</td><td>Nama Pembangkit</td><td class="c">{{ strtoupper($unit->name) }}</td></tr>
        <tr><td class="c">2</td><td>Bulan</td><td class="c">{{ strtoupper(\App\Support\Indonesian::monthName($month)) }}</td></tr>
    </table>

    <div class="heading">B. PENILAIAN 5S5R</div>
    @foreach($sections as $section)
        <div class="section-block">
            <div class="heading">{{ $section['number'] }}. {{ $section['title'] }}</div>
            <div class="note"><b>Tujuan:</b> {{ $section['note'] }}</div>
            <table class="grid">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 16px;">No</th>
                        <th rowspan="2" style="width: 130px;">{{ $sheet['row_label'] }}</th>
                        <th colspan="{{ count($columns) }}">TANGGAL PELAKSANAAN</th>
                        <th rowspan="2" style="width: 34px;">RENCANA</th>
                        <th rowspan="2" style="width: 36px;">REALISASI</th>
                        <th rowspan="2" style="width: 32px;">A. DATA</th>
                        <th rowspan="2" colspan="3">EVIDEN</th>
                    </tr>
                    <tr>
                        @foreach($columns as $column)
                            <th class="day">{{ $column['col'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($grouped->get($section['key'], []) as $row)
                        <tr>
                            <td class="c">{{ $loop->iteration }}</td>
                            <td>{{ $row['nama'] }}</td>
                            @foreach($columns as $column)
                                @php $done = ($row['days']->{$column['col']} ?? null) === 'D'; @endphp
                                <td class="c {{ $done ? 'done' : '' }}">{{ $done ? '1' : '' }}</td>
                            @endforeach
                            <td class="c">{{ $row['summary']['rencana'] }}</td>
                            <td class="c">{{ $row['summary']['realisasi'] }}</td>
                            <td class="c">{{ $row['summary']['kinerja'] }}</td>
                            @for($i = 0; $i < 3; $i++)
                                <td class="photo">@if(isset($row['evidence_urls'][$i]))<img src="{{ $row['evidence_urls'][$i] }}" alt="Eviden">@endif</td>
                            @endfor
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="{{ count($columns) + 2 }}" class="c b" style="height: 12px;">TOTAL</td>
                        <td class="c b">{{ $totals[$section['key']]['rencana'] }}</td>
                        <td class="c b">{{ $totals[$section['key']]['realisasi'] }}</td>
                        <td class="c b">{{ $percent($totals[$section['key']]['realisasi'], $totals[$section['key']]['rencana']) }}</td>
                        <td colspan="3"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach

    <table class="legend">
        <tr><td class="b" colspan="2">CATATAN :</td></tr>
        <tr><td></td><td>1. Isilah angka 1 jika dilaksanakan kegiatan</td></tr>
        <tr><td></td><td>2. Lampirkan eviden foto pelaksanaan pada tiap item</td></tr>
        <tr><td></td><td>3. Isilah tanggal pelaksanaan berdasarkan jadwal pelaksanaan 5R</td></tr>
    </table>

    <table class="ident" style="margin-top: 6px;">
        <tr><th>AKUMULATIF</th><th>RENCANA</th><th>REALISASI</th><th>A. KINERJA</th></tr>
        @foreach($sections as $section)
            <tr>
                <td>{{ strtok($section['title'], ' ') }}</td>
                <td class="c">{{ $totals[$section['key']]['rencana'] }}</td>
                <td class="c">{{ $totals[$section['key']]['realisasi'] }}</td>
                <td class="c">{{ $percent($totals[$section['key']]['realisasi'], $totals[$section['key']]['rencana']) }}</td>
            </tr>
        @endforeach
        <tr class="b">
            <td>TOTAL</td>
            <td class="c">{{ $totals->sum('rencana') }}</td>
            <td class="c">{{ $totals->sum('realisasi') }}</td>
            <td class="c">{{ $percent($totals->sum('realisasi'), $totals->sum('rencana')) }}</td>
        </tr>
    </table>
</body>
</html>
