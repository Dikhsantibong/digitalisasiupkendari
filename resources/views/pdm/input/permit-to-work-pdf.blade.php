@php
    /**
     * PDF (A4 portrait, like the paper form) of the PdM input
     * "Laporan Permit to Work (PTW) Pembangkit".
     *
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var list<array{id: int|null, no_urut: int, uraian: string, tanggal: string|null, status: string}> $rows
     * @var int $totalOpen
     * @var int $totalClose
     */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan PTW PdM Pembangkit - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('pdm.input.partials.styles')
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm 16mm 12mm; }
        table.grid td { height: 13px; }
    </style>
</head>
<body>
    @include('pdm.input.partials.kop', [
        'theme' => 'plain',
        'lines' => [
            'JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE - KIT',
            'PLN NP UP KENDARI '.strtoupper($unit->name),
            'LAPORAN PERMIT TO WORK PEMBANGKIT',
            'BAGIAN PdM PEMBANGKIT',
        ],
    ])

    <div class="bar bar-orange">LAPORAN PTW PEMBANGKIT — {{ strtoupper($periodLabel) }}</div>
    <table class="grid th-orange">
        <thead>
            <tr>
                <th rowspan="2" style="width: 26px;">NO</th>
                <th rowspan="2">URAIAN</th>
                <th rowspan="2" style="width: 110px;">TANGGAL</th>
                <th colspan="2">STATUS</th>
            </tr>
            <tr>
                <th style="width: 80px;">OPEN</th>
                <th style="width: 80px;">CLOSE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                @php $filled = $row['id'] !== null; @endphp
                <tr>
                    <td class="c">{{ $row['no_urut'] }}</td>
                    <td>{{ $row['uraian'] }}</td>
                    <td class="c">{{ $row['tanggal'] ? \Illuminate\Support\Carbon::parse($row['tanggal'])->format('d/m/Y') : '' }}</td>
                    <td class="c">{{ $filled && $row['status'] === 'open' ? '✓' : '' }}</td>
                    <td class="c">{{ $filled && $row['status'] === 'close' ? '✓' : '' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3" class="c">TOTAL</td>
                <td class="c">{{ $totalOpen }}</td>
                <td class="c">{{ $totalClose }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
