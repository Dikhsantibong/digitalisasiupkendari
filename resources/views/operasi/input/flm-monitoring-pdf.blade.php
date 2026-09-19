@php
    /**
     * PDF (A4 landscape, like the paper form) of the Operasi input
     * "Monitoring FLM".
     *
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var list<array{no_urut: int, mesin: string, tanggal: string|null, masalah: string, kondisi_awal: list<string>, kondisi_akhir: string, catatan: string, status: string}> $rows
     * @var array<string, string> $kondisiAwal
     * @var string|null $logoLeft
     * @var string|null $logoRight
     */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Monitoring FLM - {{ $unit->name }} - {{ $periodLabel }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 10mm 16mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8px; color: #000; margin: 0; }
        .kop { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: middle; text-align: center; }
        .kop .logo { width: 170px; }
        .kop .logo img { max-height: 46px; max-width: 150px; }
        .kop .lines { font-weight: bold; font-size: 10px; line-height: 1.5; }
        .period { font-weight: bold; font-size: 9px; margin: 6px 0 3px 0; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 2px 3px; vertical-align: middle; }
        table.grid th { background: #9dd9f3; font-weight: bold; text-align: center; }
        table.grid td { height: 16px; }
        .c { text-align: center; }
        .b { font-weight: bold; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td class="logo">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
            <td class="lines">
                JASA PENDUKUNG TEKNIS 6 SITE<br>
                {{ strtoupper($unit->name) }}<br>
                LAPORAN PROJECT<br>
                MONITORING FLM
            </td>
            <td class="logo">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
        </tr>
    </table>

    <div class="period">PRIODE : {{ $periodLabel }}</div>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;">No</th>
                <th rowspan="2" style="width: 120px;">Mesin / Peralatan</th>
                <th rowspan="2" style="width: 90px;">Tanggal</th>
                <th rowspan="2" style="width: 160px;">Masalah Awal yg ditemukan</th>
                <th colspan="{{ count($kondisiAwal) }}">Kondisi Awal</th>
                <th rowspan="2" style="width: 80px;">Kondisi Akhir</th>
                <th rowspan="2" style="width: 150px;">Catatan FLM</th>
                <th rowspan="2" style="width: 42px;">Status</th>
            </tr>
            <tr>
                @foreach($kondisiAwal as $label)
                    <th style="width: 58px;">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                @php $filled = $row['mesin'] !== '' || $row['masalah'] !== '' || $row['tanggal']; @endphp
                <tr>
                    <td class="c">{{ $row['no_urut'] }}</td>
                    <td>{{ $row['mesin'] }}</td>
                    <td class="c">{{ $row['tanggal'] ? \App\Support\Indonesian::longDate(\Illuminate\Support\Carbon::parse($row['tanggal'])) : '' }}</td>
                    <td>{{ $row['masalah'] }}</td>
                    @foreach(array_keys($kondisiAwal) as $key)
                        <td class="c b">{{ in_array($key, $row['kondisi_awal'], true) ? '✓' : '' }}</td>
                    @endforeach
                    <td class="c">{{ $row['kondisi_akhir'] }}</td>
                    <td>{{ $row['catatan'] }}</td>
                    <td class="c b">{{ $filled ? strtoupper($row['status']) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
