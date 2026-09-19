@php
    /**
     * PDF (A4 landscape) of the PdM input "Form Monitoring Pemeriksaan &
     * Pengiriman Sample": page 1 = A & B, page 2 = C, D & E.
     *
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var array<string, array{title: string, default_rows: int, columns: list<array<string, mixed>>}> $sections
     * @var array<string, mixed> $document
     */
    $date = function (?string $value): string {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    };
    $kop = [
        'theme' => 'navy',
        'lines' => [
            'JASA PENDUKUNG TEKNIS 6 - 11 SITE',
            'PLN NP UP KENDARI '.strtoupper($unit->name),
            'FORM MONITORING PEMERIKSAAN & PENGIRIMAN SAMPLE PDM',
            'BAGIAN PdM PEMBANGKIT',
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Form Monitoring Pemeriksaan &amp; Pengiriman Sample PdM - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('pdm.input.partials.styles')
    <style>@page { size: A4 landscape; margin: 10mm 10mm 16mm 10mm; }</style>
</head>
<body>
    @include('pdm.input.partials.kop', $kop)

    <table class="fields">
        <tr><td class="label">Unit / Lokasi</td><td style="width: 260px;">{{ $document['lokasi'] }}</td></tr>
        <tr><td class="label">PIC Monitoring</td><td>{{ $document['pic_monitoring'] }}</td></tr>
        <tr><td class="label">Periode</td><td>{{ $periodLabel }}</td></tr>
    </table>

    @foreach(['pengiriman', 'hasil', 'rekap', 'temuan'] as $key)
        @if($key === 'rekap')
            <div class="page-break"></div>
            @include('pdm.input.partials.kop', $kop)

            <div class="section-title">C. REKAP MONITORING</div>
            <table class="grid th-navy">
                <thead>
                    <tr>
                        <th style="width: 22px;">No</th><th>Jenis Sample</th><th>Target Pengiriman</th><th>Jumlah Terkirim</th>
                        <th>Jumlah Belum Terkirim</th><th>Jumlah Hasil Diterima</th><th>Jumlah Menunggu</th>
                        <th>Jumlah Perlu Tindak Lanjut</th><th>Status Monitoring</th><th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($document['rekap'] as $rekap)
                        <tr class="{{ $rekap['jenis'] === 'TOTAL' ? 'total' : '' }}">
                            <td class="c">{{ $rekap['jenis'] === 'TOTAL' ? '' : $loop->iteration }}</td>
                            <td>{{ $rekap['jenis'] }}</td>
                            <td class="c">{{ $rekap['target'] ?? '-' }}</td>
                            <td class="c">{{ $rekap['terkirim'] }}</td>
                            <td class="c">{{ $rekap['belum_terkirim'] }}</td>
                            <td class="c">{{ $rekap['hasil_diterima'] }}</td>
                            <td class="c">{{ $rekap['menunggu'] }}</td>
                            <td class="c">{{ $rekap['perlu_tindak_lanjut'] }}</td>
                            <td class="c">{{ $rekap['status'] }}</td>
                            <td>{{ $rekap['keterangan'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @continue
        @endif

        @php $section = $sections[$key]; @endphp
        <div class="section-title">{{ $section['title'] }}</div>
        <table class="grid th-navy">
            <thead>
                <tr>
                    <th style="width: 22px;">No</th>
                    @foreach($section['columns'] as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($document['rows'][$key] as $row)
                    <tr>
                        <td class="c">{{ $loop->iteration }}</td>
                        @foreach($section['columns'] as $column)
                            @php $value = $row[$column['key']] ?? null; @endphp
                            <td class="{{ in_array($column['type'] ?? '', ['date', 'select'], true) ? 'c' : '' }}">
                                {{ ($column['type'] ?? '') === 'date' ? $date($value) : $value }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="section-title">E. CATATAN MONITORING</div>
    <div class="note" style="border: 1px solid #000; min-height: 50px; padding: 4px;">{{ $document['catatan'] }}</div>
</body>
</html>
