@php
    /**
     * Laporan Pengusahaan Pembangkit (Operasi): sampul, lembar pengesahan,
     * daftar isi, then every chapter of OperasiPengusahaanBook in the unit's
     * filing order. Each part is one `.op-p-section` block (with
     * `.op-p-landscape` when it prints landscape); the PDF export renders them
     * in order and merges the portrait and landscape pages
     * (OrientationPdfMerger::renderSections), filling the Daftar Isi page numbers.
     *
     * @var array<string, mixed> $data  App\Services\Operasi\OperasiPengusahaanDocument::build()
     */
    $report = $data['report'] ?? [];
    $unit = $report['unit'] ?? [];
    $period = $report['period'] ?? [];
    $signatories = $report['signatories'] ?? [];
    $number = $data['document']['number'] ?? '';
    /** @var list<array{no: int, key: string, title: string, parts: list<array{id: string, orientation: string, scope: string, body: string, css: string}>}> $chapters */
    $chapters = $data['chapters'] ?? [];
@endphp

<div class="op-p-section" id="bab-sampul">
    @include('reports.partials.pengusahaan-cover', [
        'title' => 'LAPORAN PENGUSAHAAN PEMBANGKIT',
        'subtitle' => 'BIDANG OPERASI',
        'unitName' => $unit['name'] ?? '',
        'monthName' => $period['month_name'] ?? '',
        'year' => $period['year'] ?? '',
    ])
</div>

<div class="page-break"></div>

<div class="op-p-section" id="bab-pengesahan">
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">LEMBAR PENGESAHAN LAPORAN PENGUSAHAAN PEMBANGKIT (OPERASI)</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: {{ $number }}</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                </td>
            </tr>
        </table>

        <div style="margin: 25px 0 20px 0; font-size: 9.5px; line-height: 1.6; text-align: justify;">
            <p>
                Dokumen <strong>Laporan Pengusahaan Pembangkit Bidang Operasi</strong> ini memuat ikhtisar sentral, berita acara,
                rekap dan rincian bahan bakar &amp; pelumas, neraca daya, beban, data kinerja, jam operasi / pemeliharaan / gangguan,
                kWh, tara kalor, SFC dan TUG 9 pada <strong>{{ $unit['name'] ?? 'Unit Pembangkit' }}</strong> untuk periode
                <strong>{{ $period['label'] ?? '' }}</strong>.
            </p>
            <p style="margin-top: 10px;">
                Setiap bagian adalah lembar dari menu Pengusahaan Operasi yang bersangkutan, telah diperiksa kebenarannya,
                dan disahkan oleh pejabat yang berwenang di bawah ini.
            </p>
        </div>

        <div style="margin-top: 40px;">
            <div style="text-align: right; font-size: 9px; margin-bottom: 15px;">Kendari, {{ $period['formatted_date'] ?? '' }}</div>
            <table class="sign-table">
                <tr>
                    @foreach ([['Dibuat Oleh:', 'staf'], ['Diperiksa Oleh:', 'tl'], ['Disetujui Oleh:', 'manager']] as [$role, $key])
                        <td>
                            <div class="sign-role">{{ $role }}</div>
                            <div class="sign-position">{{ $signatories[$key]['position'] ?? '' }}</div>
                            <div class="sign-space"></div>
                            <div class="sign-name">{{ ($signatories[$key]['name'] ?? '') ?: '(...................................)' }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="page-break"></div>

<div class="op-p-section" id="bab-daftar-isi">
    <div class="op-p-toc-title">DAFTAR ISI<br><span>{{ strtoupper($unit['name'] ?? '') }} &bull; {{ strtoupper($period['label'] ?? '') }}</span></div>
    <table class="op-p-toc">
        <tr class="head"><td class="n">No.</td><td>Uraian</td><td class="pg">Halaman</td></tr>
        @foreach ($chapters as $chapter)
            <tr>
                <td class="n">{{ $chapter['no'] }}</td>
                <td>{{ $chapter['title'] }}</td>
                <td class="pg"><a href="#{{ $chapter['parts'][0]['id'] }}">…</a></td>
            </tr>
        @endforeach
    </table>
</div>

@foreach ($chapters as $chapter)
    @foreach ($chapter['parts'] as $part)
        <div class="page-break"></div>
        <div class="op-p-section{{ $part['orientation'] === 'landscape' ? ' op-p-landscape' : '' }}" id="{{ $part['id'] }}">
            <div class="{{ $part['scope'] }}">{!! $part['body'] !!}</div>
        </div>
    @endforeach
@endforeach
