@php
    /**
     * PDF (A4 landscape) of the PdM input "Kesiapan APD Bagian PdM Pembangkit".
     *
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var array<string, \Illuminate\Support\Collection<int, array<string, mixed>>> $groups
     * @var array<string, mixed> $meta
     */
    $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
    $answer = fn ($value): string => $value === null || $value === '' ? '-' : (string) $value;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kesiapan APD Bagian PdM Pembangkit - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('pdm.input.partials.styles')
    <style>@page { size: A4 landscape; margin: 10mm 10mm 16mm 10mm; }</style>
</head>
<body>
    @include('pdm.input.partials.kop', \App\Support\PdmInputKop::for('kesiapan-apd', $unit->name))
    <div class="muted" style="margin-bottom: 4px;">Periode: {{ $periodLabel }}</div>

    <table class="grid th-cyan">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;">No</th>
                <th rowspan="2">Inspeksi</th>
                <th colspan="3">Alat Pelindung Diri</th>
                <th colspan="2">Peralatan Kerja/ Area Kerja</th>
                <th colspan="2">SOP/ IK</th>
                <th colspan="2">P3K</th>
                <th>Cara Kerja</th>
                <th rowspan="2" style="width: 90px;">Keterangan</th>
            </tr>
            <tr>
                <th style="width: 44px;">JUMLAH</th>
                <th style="width: 44px;">SATUAN</th>
                <th style="width: 50px;">Layak/ Tdk layak</th>
                <th style="width: 58px;">Jml memenuhi/ Tdk memenuhi</th>
                <th style="width: 50px;">Layak/ Tdk Layak</th>
                <th style="width: 70px;">Memenuhi/ Tidak memenuhi semua bidang pekerjaan PNP</th>
                <th style="width: 70px;">Memenuhi/ Tidak memenuhi semua bidang pekerjaan Vendor</th>
                <th style="width: 44px;">Ada/ Tdk ada</th>
                <th style="width: 44px;">Ada/ Tdk ada</th>
                <th style="width: 54px;">Ergonomi/ Tdk Ergonomi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groups as $kelompok => $items)
                <tr class="group">
                    <td class="c">{{ $roman[$loop->index] ?? $loop->iteration }}</td>
                    <td colspan="12">{{ $kelompok }}</td>
                </tr>
                @foreach($items as $item)
                    <tr>
                        <td class="c">{{ $loop->iteration }}</td>
                        <td>{{ $item['inspeksi'] }}</td>
                        <td class="c">{{ $item['jumlah'] ?? '' }}</td>
                        <td class="c">{{ $item['satuan'] }}</td>
                        <td class="c">{{ $answer($item['kelayakan_apd']) }}</td>
                        <td class="c">{{ $answer($item['peralatan_jumlah']) }}</td>
                        <td class="c">{{ $answer($item['peralatan_kelayakan']) }}</td>
                        <td class="c">{{ $answer($item['sop_pnp']) }}</td>
                        <td class="c">{{ $answer($item['sop_vendor']) }}</td>
                        <td class="c">{{ $answer($item['p3k_kotak']) }}</td>
                        <td class="c">{{ $answer($item['p3k_isi']) }}</td>
                        <td class="c">{{ $answer($item['cara_kerja']) }}</td>
                        <td>{{ $item['keterangan'] }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="note"><span style="text-decoration: underline;">Catatan:</span> {{ $meta['catatan'] }}</div>

</body>
</html>
