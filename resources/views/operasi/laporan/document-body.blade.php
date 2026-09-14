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

{{-- SEGMENT A (portrait): cover + ringkasan + daftar isi + istilah + isi laporan --}}
<div class="seg-a">
{{-- 1. COVER --}}
<div class="op-cover" id="sec-1">
    <svg class="op-cover-bg" viewBox="0 0 794 1123" xmlns="http://www.w3.org/2000/svg">
        <polygon points="0,0 210,0 0,270" fill="#0b2545" />
        <polygon points="210,0 248,0 0,320 0,270" fill="#00a3e0" />
        <polygon points="248,0 262,0 0,338 0,320" fill="#f59e0b" />
        <polygon points="0,110 135,35 110,170 0,230" fill="#0080b0" opacity="0.25" />
        <polygon points="460,1123 794,520 794,1123" fill="#005b82" />
        <path d="M 0 715 Q 220 815 540 735 Q 568 725 565 750 C 560 780 480 960 470 1123 L 0 1123 Z" fill="#0b2545" />
        <path d="M 0 707 Q 220 807 540 727 Q 575 717 572 750 C 567 780 487 960 477 1123 L 470 1123 C 480 960 560 780 565 750 Q 568 725 540 735 Q 220 815 0 715 Z" fill="#f59e0b" />
    </svg>

    <div class="op-cover-content">
        <div class="op-cover-logos">
            <table class="op-logos-table">
                <tr>
                    <td class="op-logo-cell-left">
                        <img src="/logo/sidebar-logo.png" class="op-logo-pln" alt="PLN Nusantara Power">
                    </td>
                    <td class="op-logo-divider-cell">
                        <div class="op-logo-vdiv"></div>
                    </td>
                    <td class="op-logo-cell-right">
                        <img src="/logo/mkp.jpg" class="op-logo-mkp" alt="Mitra Karya Prima">
                    </td>
                </tr>
            </table>
        </div>

        <div class="op-cover-title-wrap">
            <h1 class="op-cover-main-title">
                LAPORAN OPERASI<br>PEMBANGKIT
            </h1>
            <div class="op-cover-title-line"></div>
        </div>

        <div class="op-cover-spec-box">
            <table class="op-spec-table">
                <tr>
                    <td class="op-spec-label">NAMA PEMBANGKIT</td>
                    <td class="op-spec-colon">:</td>
                    <td class="op-spec-val">{{ strtoupper($report['unit']['name'] ?? '') }}</td>
                </tr>
                <tr>
                    <td class="op-spec-label">PERIODE PELAPORAN</td>
                    <td class="op-spec-colon">:</td>
                    <td class="op-spec-val">BULAN {{ strtoupper($report['period']['label'] ?? '') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="op-cover-pillars-badge">
        <table class="op-pillars-table">
            <tr>
                <td class="op-pillar-item">
                    <svg class="op-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <polyline points="9 12 11 14 15 10"/>
                    </svg>
                    <span class="op-p-text">
                        <strong>ANDAL</strong><small>RELIABLE</small>
                    </span>
                </td>
                <td class="op-pillar-sep">|</td>
                <td class="op-pillar-item">
                    <svg class="op-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    <span class="op-p-text">
                        <strong>EFISIEN</strong><small>EFFICIENT</small>
                    </span>
                </td>
                <td class="op-pillar-sep">|</td>
                <td class="op-pillar-item">
                    <svg class="op-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                    </svg>
                    <span class="op-p-text">
                        <strong>BERKELANJUTAN</strong><small>SUSTAINABLE</small>
                    </span>
                </td>
                <td class="op-pillar-sep">|</td>
                <td class="op-pillar-item">
                    <svg class="op-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="7" r="3"/>
                        <path d="M3 18v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1"/>
                        <circle cx="17" cy="9" r="2.5"/>
                        <path d="M17 14h2a3 3 0 0 1 3 3v1"/>
                    </svg>
                    <span class="op-p-text">
                        <strong>KOLABORATIF</strong><small>COLLABORATIVE</small>
                    </span>
                </td>
            </tr>
        </table>
    </div>
</div>

@include('operasi.laporan.partials.letterhead', ['report' => $report, 'title' => $reportTitle])

{{-- 2. EXECUTIVE SUMMARY --}}
<div class="op-h2" id="sec-2">2. Executive Summary</div>
<p class="op-p">
    Laporan ini merangkum kinerja operasi {{ $report['unit']['name'] }}@if(!empty($report['engine']['name'])) — {{ $report['engine']['name'] }}@endif
    pada periode <strong>{{ $report['period']['label'] }}</strong>. Produksi netto tercatat
    <strong>{{ $fmt($total['kwh_netto'] ?? null) }} kWh</strong> dengan total pemakaian bahan bakar
    {{ $fmt($report['total_bbm']) }} liter dan SFC netto {{ $fmt($report['sfc']['netto'] ?? null) }} L/kWh @if($hours), serta {{ $fmt($hours['operasi']) }} jam operasi @endif.
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
