@php
    /**
     * PDF (A4 portrait, like the paper form) of the Logistik input
     * "Rekomendasi Logistik & Gudang".
     *
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var list<array{id: int|null, no_urut: int, uraian: string, kondisi_existing: string, tindak_lanjut: string, keterangan: string}> $rows
     * @var string|null $logoLeft
     * @var string|null $logoRight
     */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekomendasi Logistik &amp; Gudang - {{ $unit->name }} - {{ $periodLabel }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm 16mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8px; color: #000; margin: 0; }
        .kop { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .kop td { border: 1px solid #000; vertical-align: middle; }
        .kop .logo { width: 150px; padding: 4px 8px; text-align: center; }
        .kop .logo img { max-height: 40px; max-width: 135px; }
        .kop .line { text-align: center; font-weight: bold; font-size: 9.5px; padding: 3px 6px; }
        .meta { margin: 14px 0 10px 40px; border-collapse: collapse; }
        .meta td { font-weight: bold; font-size: 9.5px; padding: 3px 4px; }
        .meta .label { width: 110px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; }
        table.grid th { background: #9dd9f3; font-weight: bold; text-align: center; height: 36px; }
        table.grid td { height: 46px; font-size: 7.5px; }
        .c { text-align: center; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td rowspan="3" class="logo">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
            <td class="line">JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE -KIT</td>
            <td rowspan="3" class="logo">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
        </tr>
        <tr><td class="line">PLN NP UP KENDARI - {{ strtoupper($unit->name) }}</td></tr>
        <tr><td class="line">REKOMENDASI LOGISTIK &amp; GUDANG</td></tr>
    </table>

    <table class="meta">
        <tr><td class="label">UNIT</td><td>: {{ strtoupper($unit->name) }}</td></tr>
        <tr><td class="label">PERIODE/BULAN</td><td>: {{ strtoupper($periodLabel) }}</td></tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 36px;">NO.</th>
                <th style="width: 20%;">Uraian</th>
                <th style="width: 26%;">Kondisi Existing</th>
                <th style="width: 26%;">Tindak Lanjut</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td class="c">{{ $row['no_urut'] }}</td>
                    <td>{{ $row['uraian'] }}</td>
                    <td>{{ $row['kondisi_existing'] }}</td>
                    <td>{{ $row['tindak_lanjut'] }}</td>
                    <td>{{ $row['keterangan'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
