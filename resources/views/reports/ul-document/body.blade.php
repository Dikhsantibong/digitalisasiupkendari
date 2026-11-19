@php
    /**
     * Dokumen Laporan UL (Pengusahaan Pembangkit, Logistik, K3L) — one
     * `.ul-section` per page group in Daftar Isi order: I. Sampul, II. Daftar
     * Isi, III. Lembar Pengesahan (portrait), then one landscape section per
     * bagian (KPI akumulasi UL, arah data, perbandingan per unit, grafik 3D,
     * tabel rincian) and the Status Laporan & Kelengkapan Input. The PDF
     * export fills the Daftar Isi page numbers (OrientationPdfMerger::renderSections).
     *
     * @var array<string, mixed> $data  UlReportDocumentBuilder::build()
     */
    $report = $data['report'];
    $ulName = $report['ul']['name'];
    $roman = ['IV.', 'V.', 'VI.', 'VII.', 'VIII.'];
    $closing = $roman[count($report['parts'])] ?? 'IX.';
@endphp

{{-- ===================== I. SAMPUL (portrait) ===================== --}}
<div class="ul-section" id="sec-1">
    <div class="k3-cover">
        <svg class="k3-cover-bg" viewBox="0 0 794 1123" xmlns="http://www.w3.org/2000/svg">
            <polygon points="0,0 210,0 0,270" fill="#0b2545" />
            <polygon points="210,0 248,0 0,320 0,270" fill="#00a3e0" />
            <polygon points="248,0 262,0 0,338 0,320" fill="#f59e0b" />
            <polygon points="0,110 135,35 110,170 0,230" fill="#0080b0" opacity="0.25" />
            <polygon points="460,1123 794,520 794,1123" fill="#005b82" />
            <path d="M 0 715 Q 220 815 540 735 Q 568 725 565 750 C 560 780 480 960 470 1123 L 0 1123 Z" fill="#0b2545" />
            <path d="M 0 707 Q 220 807 540 727 Q 575 717 572 750 C 567 780 487 960 477 1123 L 470 1123 C 480 960 560 780 565 750 Q 568 725 540 735 Q 220 815 0 715 Z" fill="#f59e0b" />
        </svg>

        <div class="k3-cover-content">
            <div class="k3-cover-logos">
                <table class="k3-logos-table">
                    {{-- Laporan UL: PLN Nusantara Power only, no MKP logo (user, 2026-11-18). --}}
                    <tr>
                        <td style="vertical-align: middle; text-align: center;"><img src="/logo/sidebar-logo.png" class="k3-logo-pln" alt="PLN Nusantara Power"></td>
                    </tr>
                </table>
            </div>

            <div class="k3-cover-title-wrap">
                <h1 class="k3-cover-main-title">{{ mb_strtoupper($report['title']) }}</h1>
                <div class="k3-cover-title-line"></div>
            </div>

            <div class="k3-cover-spec-box">
                <table class="k3-spec-table">
                    <tr>
                        <td class="k3-spec-label">UNIT LAYANAN</td>
                        <td class="k3-spec-colon">:</td>
                        <td class="k3-spec-val">{{ mb_strtoupper($ulName) }}</td>
                    </tr>
                    <tr>
                        <td class="k3-spec-label">UNIT PEMBANGKIT</td>
                        <td class="k3-spec-colon">:</td>
                        <td class="k3-spec-val">{{ mb_strtoupper(collect($report['units'])->pluck('name')->join(', ') ?: '-') }}</td>
                    </tr>
                    <tr>
                        <td class="k3-spec-label">PERIODE PELAPORAN</td>
                        <td class="k3-spec-colon">:</td>
                        <td class="k3-spec-val">BULAN {{ mb_strtoupper($report['period']['label']) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="k3-cover-pillars-badge">
            <table class="k3-pillars-table">
                <tr>
                    <td class="k3-pillar-item"><span class="k3-p-text"><strong>ANDAL</strong><small>RELIABLE</small></span></td>
                    <td class="k3-pillar-sep">|</td>
                    <td class="k3-pillar-item"><span class="k3-p-text"><strong>EFISIEN</strong><small>EFFICIENT</small></span></td>
                    <td class="k3-pillar-sep">|</td>
                    <td class="k3-pillar-item"><span class="k3-p-text"><strong>BERSIH</strong><small>CLEAN</small></span></td>
                    <td class="k3-pillar-sep">|</td>
                    <td class="k3-pillar-item"><span class="k3-p-text"><strong>AMAN</strong><small>SAFE</small></span></td>
                </tr>
            </table>
        </div>
    </div>
</div>

{{-- ===================== II. DAFTAR ISI (portrait) ===================== --}}
<div class="ul-section" id="sec-2">
    @include('reports.ul-document.kop', ['ulName' => $ulName, 'title' => 'DAFTAR ISI '.mb_strtoupper($report['title'])])

    <table class="toc-table">
        <tr class="toc-head">
            <td class="n">No.</td>
            <td>Uraian</td>
            <td class="pg">Halaman</td>
        </tr>
        <tr><td class="n">I.</td><td style="font-weight: bold;">SAMPUL</td><td class="pg">&nbsp;</td></tr>
        <tr><td class="n">II.</td><td style="font-weight: bold;">DAFTAR ISI</td><td class="pg"><a href="#sec-2">…</a></td></tr>
        <tr><td class="n">III.</td><td style="font-weight: bold;">LEMBAR PENGESAHAN</td><td class="pg"><a href="#sec-3">…</a></td></tr>
        @foreach ($report['parts'] as $i => $part)
            <tr><td class="n">{{ $roman[$i] }}</td><td style="font-weight: bold;">{{ mb_strtoupper($part['title']) }}</td><td class="pg"><a href="#part-{{ $part['key'] }}">…</a></td></tr>
        @endforeach
        <tr><td class="n">{{ $closing }}</td><td style="font-weight: bold;">STATUS LAPORAN UNIT &amp; KELENGKAPAN INPUT</td><td class="pg"><a href="#sec-status">…</a></td></tr>
    </table>
</div>

{{-- ===================== III. LEMBAR PENGESAHAN (portrait) ===================== --}}
<div class="ul-section" id="sec-3">
    @include('reports.ul-document.kop', ['ulName' => $ulName, 'title' => 'LEMBAR PENGESAHAN'])

    <div class="k3-pengesahan-body">
        <div style="font-weight: bold; margin-bottom: 14px; text-transform: uppercase;">JASA PENDUKUNG TEKNIS 6 SITE - {{ $ulName }}</div>
        <div style="margin-bottom: 14px;">Dengan ini menyatakan bahwa :</div>
        <div style="font-weight: bold; margin-bottom: 16px;">1. {{ mb_strtoupper($report['title']) }} — {{ mb_strtoupper($ulName) }}</div>
        <div style="margin-bottom: 16px; text-align: justify; line-height: 1.6;">Telah disusun berdasarkan data input Laporan Project dan Laporan Divisi seluruh unit pembangkit di bawah {{ $ulName }} ({{ collect($report['units'])->pluck('name')->join(', ') ?: '-' }}) periode {{ $report['period']['label'] }}.</div>
        <div style="margin-bottom: 16px; text-align: justify; line-height: 1.6;">{{ $data['validation'] }} untuk digunakan sebagai dokumen pelaporan dan evaluasi kinerja Unit Layanan.</div>
        <div style="margin-bottom: 22px; text-align: justify; line-height: 1.6;">Demikian lembar pengesahan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</div>

        <div style="margin-top: 26px; margin-bottom: 16px; text-align: right; padding-right: 15px; font-size: 10.5pt;">{{ $data['tempat_tanggal'] }}</div>

        {!! $data['signatures'] !!}
    </div>
</div>

{{-- ===================== IV… BAGIAN LAPORAN (landscape) ===================== --}}
@foreach ($report['parts'] as $i => $part)
    <div class="ul-section ul-landscape" id="part-{{ $part['key'] }}">
        @include('reports.ul-document.kop', ['ulName' => $ulName, 'title' => $roman[$i].' '.mb_strtoupper($part['title'])])

        @if ($part['kpis'] !== [])
            <div class="ul-h">A. Indikator Utama (Akumulasi UL)</div>
            <table class="ul-table">
                <tr><th>Indikator</th><th>Bulan Ini (Akumulasi UL)</th><th>Rata-rata per Unit</th><th>Bulan Lalu</th><th>Perubahan</th><th>Penilaian</th></tr>
                @foreach ($part['kpis'] as $kpi)
                    <tr>
                        <td>{{ $kpi['label'] }}</td>
                        <td class="r"><strong>{{ $kpi['value'] }}</strong></td>
                        <td class="r">{{ $kpi['average'] ?? '—' }}</td>
                        <td class="r">{{ $kpi['previous'] }}</td>
                        <td class="c">{{ $kpi['delta'] === null ? '—' : ($kpi['trend'] === 'naik' ? '▲ ' : ($kpi['trend'] === 'turun' ? '▼ ' : '■ ')).number_format(abs($kpi['delta']), 1, ',', '.').'%' }}</td>
                        <td class="c">{{ $kpi['assessment'] ? ucfirst($kpi['assessment']) : '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <p class="ul-empty">Belum ada unit aktif di bawah UL ini.</p>
        @endif

        @if ($part['insights'] !== [])
            <div class="ul-h">B. Ringkasan Arah Data</div>
            <ul class="ul-insights">
                @foreach ($part['insights'] as $line)
                    <li>{{ $line['text'] }}</li>
                @endforeach
            </ul>
        @endif

        @if ($part['unit_table'] !== null)
            <div class="ul-h">C. {{ $part['unit_table']['title'] }}</div>
            <table class="ul-table">
                <tr>@foreach ($part['unit_table']['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr>
                @foreach ($part['unit_table']['rows'] as $row)
                    <tr>@foreach ($row as $c => $cell)<td class="{{ $c === 0 ? '' : 'r' }}">{{ $cell }}</td>@endforeach</tr>
                @endforeach
                <tr class="ul-foot">@foreach ($part['unit_table']['footer'] as $c => $cell)<td class="{{ $c === 0 ? '' : 'r' }}">{{ $cell }}</td>@endforeach</tr>
            </table>
        @endif

        @if ($part['charts'] !== [])
            <div class="ul-h">D. Grafik 3D &amp; Akumulasi</div>
            <table class="ul-charts">
                @foreach (array_chunk($part['charts'], 2) as $pair)
                    <tr>
                        @foreach ($pair as $chart)
                            <td><div class="ul-chart-title">{{ $chart['title'] }}</div><img src="{{ $chart['image'] }}" alt="{{ $chart['title'] }}"></td>
                        @endforeach
                        @if (count($pair) === 1)<td></td>@endif
                    </tr>
                @endforeach
            </table>
        @endif

        @foreach ($part['sections'] as $s => $section)
            <div class="ul-h">{{ chr(69 + $s) }}. {{ $section['title'] }}</div>
            <table class="ul-table">
                <tr>@foreach ($section['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr>
                @forelse ($section['rows'] as $row)
                    <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($section['columns']) }}" class="ul-empty">Belum ada data pada periode ini.</td></tr>
                @endforelse
                @if (! empty($section['footer']))
                    <tr class="ul-foot">@foreach ($section['footer'] as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                @endif
            </table>
        @endforeach
    </div>
@endforeach

{{-- ===================== STATUS LAPORAN & KELENGKAPAN (landscape) ===================== --}}
<div class="ul-section ul-landscape" id="sec-status">
    @include('reports.ul-document.kop', ['ulName' => $ulName, 'title' => $closing.' STATUS LAPORAN UNIT & KELENGKAPAN INPUT'])

    @foreach ([$report['reports'], $report['completeness']] as $t => $table)
        <div class="ul-h">{{ chr(65 + $t) }}. {{ $table['title'] }}</div>
        <table class="ul-table">
            <tr>@foreach ($table['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr>
            @forelse ($table['rows'] as $row)
                <tr>@foreach ($row as $c => $cell)<td class="{{ $c === 0 ? '' : 'c' }}">{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($table['columns']) }}" class="ul-empty">Tidak ada data.</td></tr>
            @endforelse
        </table>
    @endforeach
</div>
