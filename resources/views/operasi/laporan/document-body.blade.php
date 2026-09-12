@php
    /** @var array<string, mixed> $report  The MonthlyEngineReport payload. */
    /** @var string $documentNumber */
    /** @var string $reportTitle */
    $fmt = function ($v): string {
        if ($v === null || $v === '') {
            return '—';
        }
        if (! is_numeric($v)) {
            return (string) $v;
        }
        $s = number_format((float) $v, 2, ',', '.');

        return str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s;
    };
    $usesMfo = collect($report['rows'])->contains(fn ($r): bool => ($r['pemakaian_mfo'] ?? null) !== null);
    $summary = $report['summary'] ?? [];
    $total = $summary['total'] ?? [];
    $hours = $report['hours'] ?? null;
    $colspan = $usesMfo ? 9 : 8;

    $sections = [
        'Executive Summary',
        'Daftar Isi',
        'Istilah dan Definisi',
        'Isi Laporan',
        'Produksi Harian',
        'Rekapitulasi Periode',
        'Rekap Jam Operasi',
        'SFC & Bahan Bakar',
    ];

    $glossary = [
        ['kWh Produksi', 'Energi listrik yang dihasilkan generator (stand kWh meter produksi).'],
        ['kWh PS (Pemakaian Sendiri)', 'Energi yang dipakai oleh pembangkit itu sendiri (auxiliary).'],
        ['kWh Netto', 'kWh Produksi dikurangi Pemakaian Sendiri — energi yang disalurkan.'],
        ['HSD', 'High Speed Diesel — bahan bakar solar.'],
        ['MFO', 'Marine Fuel Oil — bahan bakar residu.'],
        ['SFC', 'Specific Fuel Consumption — pemakaian bahan bakar per kWh (L/kWh).'],
        ['Beban Puncak', 'Beban tertinggi (kW) pada periode pagi/malam.'],
        ['Jam Operasi', 'Lama mesin beroperasi menghasilkan energi.'],
        ['Jam Standby', 'Lama mesin siap namun tidak beroperasi.'],
        ['Jam Gangguan', 'Lama mesin tidak dapat beroperasi karena gangguan.'],
    ];

    $summaryRow = function (string $label, array $s) use ($fmt, $usesMfo): string {
        $cells = '<td>'.$label.'</td>';
        foreach (['kwh_produksi', 'kwh_pakai_sendiri', 'kwh_netto', 'pemakaian_hsd'] as $k) {
            $cells .= '<td class="r">'.$fmt($s[$k] ?? null).'</td>';
        }
        if ($usesMfo) {
            $cells .= '<td class="r">'.$fmt($s['pemakaian_mfo'] ?? null).'</td>';
        }
        $cells .= '<td class="r">'.$fmt($s['pemakaian_pelumas_liter'] ?? null).'</td>';

        return $cells;
    };
@endphp

{{-- 1. COVER --}}
<div class="op-cover" id="sec-1">
    <img src="/logo/sidebar-logo.png" alt="Logo">
    <div class="op-cover-org">PT PLN Nusantara Power</div>
    <div class="op-cover-sub">{{ $report['unit']['service_unit'] ?? 'Unit Pelaksana Pengendalian Pembangkitan Kendari' }}</div>
    <div class="op-cover-rule"></div>
    <div class="op-cover-title">Laporan Kinerja<br>Operasi</div>
    <div class="op-cover-unit">{{ $report['unit']['name'] }}</div>
    @if(!empty($report['engine']['name']))
        <div class="op-cover-period">{{ $report['engine']['name'] }}</div>
    @endif
    <div class="op-cover-period">Periode <strong>{{ $report['period']['label'] }}</strong></div>
    <div class="op-cover-rule"></div>
    <div class="op-cover-footer">UP Kendari<small>Unit Pelaksana Pengendalian Pembangkitan Kendari</small></div>
</div>

@include('operasi.laporan.partials.letterhead', ['report' => $report, 'title' => $reportTitle])

{{-- 2. EXECUTIVE SUMMARY --}}
<div class="op-h2" id="sec-2">2. Executive Summary</div>
<p class="op-p">
    Laporan ini merangkum kinerja operasi {{ $report['unit']['name'] }}@if(!empty($report['engine']['name'])) — {{ $report['engine']['name'] }}@endif
    pada periode <strong>{{ $report['period']['label'] }}</strong>. Produksi netto tercatat
    <strong>{{ $fmt($total['kwh_netto'] ?? null) }} kWh</strong> dengan total pemakaian bahan bakar
    {{ $fmt($report['total_bbm']) }} liter dan SFC netto {{ $fmt($report['sfc']['netto'] ?? null) }} L/kWh@if($hours), serta {{ $fmt($hours['operasi']) }} jam operasi@endif.
</p>
<table class="op-data">
    <tr><th>kWh Produksi</th><th>kWh Netto</th><th>Total BBM (L)</th><th>SFC Netto</th><th>Jam Operasi</th></tr>
    <tr>
        <td class="r">{{ $fmt($total['kwh_produksi'] ?? null) }}</td>
        <td class="r">{{ $fmt($total['kwh_netto'] ?? null) }}</td>
        <td class="r">{{ $fmt($report['total_bbm']) }}</td>
        <td class="r">{{ $fmt($report['sfc']['netto'] ?? null) }}</td>
        <td class="r">{{ $hours ? $fmt($hours['operasi']) : '—' }}</td>
    </tr>
</table>

{{-- 3. DAFTAR ISI --}}
<div class="op-h2 break-before" id="sec-3">3. Daftar Isi</div>
@php
    $toc = array_merge([['Cover', 'sec-1']], collect($sections)->map(fn ($t, $i): array => [$t, 'sec-'.($i + 2)])->all());
@endphp
@foreach($toc as $i => [$tocTitle, $anchor])
    <table class="toc-item"><tr>
        <td class="n">{{ $i + 1 }}.</td>
        <td>{{ $tocTitle }}</td>
        <td class="dots"></td>
        <td class="pg"><a href="#{{ $anchor }}"></a></td>
    </tr></table>
@endforeach

{{-- 4. ISTILAH DAN DEFINISI --}}
<div class="op-h2 break-before" id="sec-4">4. Istilah dan Definisi</div>
<table class="op-data">
    <tr><th style="width:30%">Istilah</th><th>Definisi</th></tr>
    @foreach($glossary as [$term, $def])
        <tr><td>{{ $term }}</td><td style="text-align:left">{{ $def }}</td></tr>
    @endforeach
</table>

{{-- 5. ISI LAPORAN --}}
<div class="op-h2 break-before" id="sec-5">5. Isi Laporan</div>
<p class="op-p">
    Bagian ini memuat rincian operasi harian {{ $report['unit']['name'] }}@if(!empty($report['engine']['name'])) — {{ $report['engine']['name'] }}@endif
    periode {{ $report['period']['label'] }}: rekap produksi harian, subtotal per periode, rekap jam operasi,
    serta konsumsi bahan bakar dan SFC.
</p>

{{-- 6. PRODUKSI HARIAN (tabel lengkap) --}}
<div class="op-h2 break-before" id="sec-6">6. Produksi Harian</div>
<table class="op-data op-wide">
    <tr>
        <th>Tgl</th><th>kWh Produksi</th><th>kWh PS</th><th>kWh Netto</th><th>Pakai HSD (L)</th>
        @if($usesMfo)<th>Pakai MFO (L)</th>@endif
        <th>Pelumas (L)</th><th>BP Pagi</th><th>BP Malam</th>
    </tr>
    @forelse($report['rows'] as $row)
        <tr>
            <td class="c">{{ $row['day'] }}</td>
            <td class="r">{{ $fmt($row['kwh_produksi'] ?? null) }}</td>
            <td class="r">{{ $fmt($row['kwh_pakai_sendiri'] ?? null) }}</td>
            <td class="r">{{ $fmt($row['kwh_netto'] ?? null) }}</td>
            <td class="r">{{ $fmt($row['pemakaian_hsd'] ?? null) }}</td>
            @if($usesMfo)<td class="r">{{ $fmt($row['pemakaian_mfo'] ?? null) }}</td>@endif
            <td class="r">{{ $fmt($row['pemakaian_pelumas_liter'] ?? null) }}</td>
            <td class="r">{{ $fmt($row['beban_puncak_pagi_kw'] ?? null) }}</td>
            <td class="r">{{ $fmt($row['beban_puncak_malam_kw'] ?? null) }}</td>
        </tr>
    @empty
        <tr><td class="c" colspan="{{ $colspan }}">Belum ada data produksi harian.</td></tr>
    @endforelse
</table>

{{-- 7. REKAPITULASI PERIODE --}}
<div class="op-h2 break-before" id="sec-7">7. Rekapitulasi Periode</div>
<table class="op-data">
    <tr>
        <th>Periode</th><th>kWh Produksi</th><th>kWh PS</th><th>kWh Netto</th><th>Pakai HSD (L)</th>
        @if($usesMfo)<th>Pakai MFO (L)</th>@endif
        <th>Pelumas (L)</th>
    </tr>
    @if(!empty($summary['periode_1']))<tr>{!! $summaryRow('Periode I', $summary['periode_1']) !!}</tr>@endif
    @if(!empty($summary['periode_2']))<tr>{!! $summaryRow('Periode II', $summary['periode_2']) !!}</tr>@endif
    @if(!empty($summary['periode_3']))<tr>{!! $summaryRow('Periode III', $summary['periode_3']) !!}</tr>@endif
    @if(!empty($total))<tr class="total">{!! $summaryRow('TOTAL', $total) !!}</tr>@endif
    @if(empty($summary))<tr><td class="c" colspan="{{ $usesMfo ? 7 : 6 }}">Belum ada rekap.</td></tr>@endif
</table>

{{-- 8. REKAP JAM OPERASI --}}
<div class="op-h2 break-before" id="sec-8">8. Rekap Jam Operasi</div>
@if($hours)
    <table class="op-data" style="width:60%">
        <tr><td>Operasi</td><td class="r">{{ $fmt($hours['operasi']) }} jam</td></tr>
        <tr><td>Pemeliharaan (HAR)</td><td class="r">{{ $fmt($hours['har']) }} jam</td></tr>
        <tr><td>Gangguan</td><td class="r">{{ $fmt($hours['gangguan']) }} jam</td></tr>
        <tr><td>Standby</td><td class="r">{{ $fmt($hours['standby']) }} jam</td></tr>
        <tr class="total"><td>Total Jam</td><td class="r">{{ $fmt($hours['total']) }} jam</td></tr>
    </table>
@else
    <p class="op-muted">Belum ada data jam operasi (Star-Stop).</p>
@endif

{{-- 9. SFC & BAHAN BAKAR --}}
<div class="op-h2 break-before" id="sec-9">9. SFC &amp; Bahan Bakar</div>
<table class="op-data" style="width:60%">
    <tr><td>Total BBM (L)</td><td class="r">{{ $fmt($report['total_bbm']) }}</td></tr>
    <tr><td>SFC Bruto (L/kWh)</td><td class="r">{{ $fmt($report['sfc']['bruto'] ?? null) }}</td></tr>
    <tr><td>SFC Netto (L/kWh)</td><td class="r">{{ $fmt($report['sfc']['netto'] ?? null) }}</td></tr>
</table>
<p class="op-muted">No. Dokumen: {{ $documentNumber }}</p>
