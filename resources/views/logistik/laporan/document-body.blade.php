@php
    /**
     * Laporan Logistik & Gudang — one `.lg-section` per page group: I. Sampul,
     * II. Lembar Pengesahan, III. Daftar Isi, IV. every jadwal, V. every
     * input table. A section that also carries `.lg-landscape` prints on
     * landscape paper. Each table is the jadwal/input PDF view itself (scoped
     * styles, see LogistikDocumentBuilder), so it is always printed in full.
     * The PDF export fills the Daftar Isi page numbers
     * (OrientationPdfMerger::renderSections).
     *
     * @var array<string, mixed> $data
     */
    $unitName = $data['report']['unit']['name'];
    $period = $data['report']['period'];
    $pengesahan = $data['report']['pengesahan'];
    $parts = $data['parts'];
    $groups = [
        'jadwal' => ['num' => 'IV.', 'title' => 'JADWAL LOGISTIK & GUDANG'],
        'input' => ['num' => 'V.', 'title' => 'LAPORAN INPUT LOGISTIK & GUDANG'],
    ];
    $firstOf = fn (string $group): ?array => collect($parts)->firstWhere('group', $group);
@endphp

{{-- ===================== I. SAMPUL (portrait) ===================== --}}
<div class="lg-section" id="sec-1">
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
                    <tr>
                        <td class="k3-logo-cell-left"><img src="/logo/sidebar-logo.png" class="k3-logo-pln" alt="PLN Nusantara Power"></td>
                        <td class="k3-logo-divider-cell"><div class="k3-logo-vdiv"></div></td>
                        <td class="k3-logo-cell-right"><img src="/logo/mkp.jpg" class="k3-logo-mkp" alt="Mitra Karya Prima"></td>
                    </tr>
                </table>
            </div>

            <div class="k3-cover-title-wrap">
                <h1 class="k3-cover-main-title">LAPORAN LOGISTIK &amp;<br>GUDANG PEMBANGKIT</h1>
                <div class="k3-cover-title-line"></div>
            </div>

            <div class="k3-cover-spec-box">
                <table class="k3-spec-table">
                    <tr>
                        <td class="k3-spec-label">NAMA PEMBANGKIT</td>
                        <td class="k3-spec-colon">:</td>
                        <td class="k3-spec-val">{{ strtoupper($unitName) }}</td>
                    </tr>
                    <tr>
                        <td class="k3-spec-label">PERIODE PELAPORAN</td>
                        <td class="k3-spec-colon">:</td>
                        <td class="k3-spec-val">BULAN {{ strtoupper($period['label']) }}</td>
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

{{-- ===================== II. LEMBAR PENGESAHAN (portrait) ===================== --}}
<div class="lg-section" id="sec-2">
    <table class="lg-pengesahan">
        <tr class="head">
            <td class="logo" style="width: 26%;"><img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power"></td>
            <td class="title">LEMBAR PENGESAHAN</td>
            <td class="logo" style="width: 26%; text-align: right;"><img src="/logo/mkp.jpg" alt="Mitra Karya Prima"></td>
        </tr>
        <tr>
            <td colspan="3" class="body">
                <h2>LAPORAN LOGISTIK &amp; GUDANG</h2>
                <p class="center">Dengan ini menyatakan bahwa <b>Laporan Logistik &amp; Gudang</b></p>
                <p class="center" style="font-weight: bold;">
                    Bulan/Periode : {{ $period['month_name'] }}<br>
                    Unit/Site : {{ $unitName }}<br>
                    Tahun : {{ $period['year'] }}
                </p>
                <p style="font-weight: bold; text-align: justify;">Telah disusun berdasarkan kegiatan pengelolaan Logistik &amp; Gudang yang meliputi penerimaan material, pengeluaran material, monitoring stok, inventarisasi tools dan peralatan, kondisi gudang, serta administrasi dan dokumentasi pendukung.</p>
                <p style="font-weight: bold; text-align: justify;">Laporan ini telah dilakukan pemeriksaan dan dinyatakan sesuai untuk digunakan sebagai dokumen pelaporan dan evaluasi kegiatan Logistik &amp; Gudang {{ $unitName }}.</p>
                <p style="font-weight: bold;">Demikian lembar pengesahan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>
                <p style="text-align: right; font-weight: bold; margin: 60px 20px 16px 0;">{{ $pengesahan['tempat_tanggal'] }}</p>
            </td>
        </tr>
        <tr>
            <td colspan="3" style="padding: 0 0 18px 0;">
                {!! $pengesahan['blocks']['pengesahan'] !!}
            </td>
        </tr>
    </table>
</div>

{{-- ===================== III. DAFTAR ISI (portrait) ===================== --}}
<div class="lg-section" id="sec-3">
    @include('logistik.laporan.partials.kop', ['unitName' => $unitName, 'title' => 'DAFTAR ISI LAPORAN LOGISTIK & GUDANG'])

    <table class="toc-table">
        <tr class="toc-head">
            <td class="n">No.</td>
            <td>Uraian</td>
            <td class="pg">Halaman</td>
        </tr>
        <tr><td class="n">I.</td><td style="font-weight: bold;">SAMPUL</td><td class="pg">&nbsp;</td></tr>
        <tr><td class="n">II.</td><td style="font-weight: bold;">LEMBAR PENGESAHAN</td><td class="pg"><a href="#sec-2">…</a></td></tr>
        <tr><td class="n">III.</td><td style="font-weight: bold;">DAFTAR ISI</td><td class="pg"><a href="#sec-3">…</a></td></tr>
        @foreach($groups as $group => $heading)
            @php $first = $firstOf($group); @endphp
            <tr class="lg-toc-group">
                <td class="n">{{ $heading['num'] }}</td>
                <td>{{ $heading['title'] }}</td>
                <td class="pg">@if($first)<a href="#part-{{ $first['key'] }}">…</a>@else - @endif</td>
            </tr>
            @foreach($parts as $part)
                @if($part['group'] === $group)
                    <tr>
                        <td class="n"></td>
                        <td style="padding-left: 10px;">- {{ $part['title'] }}</td>
                        <td class="pg"><a href="#part-{{ $part['key'] }}">…</a></td>
                    </tr>
                @endif
            @endforeach
        @endforeach
    </table>
</div>

{{-- ===================== IV. JADWAL & V. INPUT (one section per table) ===================== --}}
@foreach($parts as $part)
    <div class="lg-section {{ $part['orientation'] === 'landscape' ? 'lg-landscape' : '' }}" id="part-{{ $part['key'] }}">
        <div class="{{ $part['scope'] }}">
            {!! $part['body'] !!}
        </div>
    </div>
@endforeach

{{-- ===================== TANDA TANGAN LAPORAN (portrait) ===================== --}}
<div class="lg-section" id="sec-ttd">
    @include('logistik.laporan.partials.kop', ['unitName' => $unitName, 'title' => 'PENGESAHAN LAPORAN'])

    <div style="font-size: 10.5pt; line-height: 1.6; margin-top: 24px; text-align: justify;">
        Demikian Laporan Logistik &amp; Gudang {{ $unitName }} periode {{ $period['label'] ?? '' }} ini disusun dengan sebenar-benarnya untuk dapat dipergunakan sebagaimana mestinya.
    </div>
    <div style="margin-top: 26px; text-align: right; padding-right: 15px; font-size: 10.5pt;">{{ $pengesahan['tempat_tanggal'] }}</div>

    {{-- Tanda tangan laporan: Project Leader & Office Logistik --}}
    {!! $pengesahan['blocks']['laporan'] !!}
</div>
