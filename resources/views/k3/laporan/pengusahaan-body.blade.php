@php
    /** @var array<string, mixed> $data */
    $report = $data['report'] ?? [];
    $unit = $report['unit'] ?? [];
    $period = $report['period'] ?? [];
    $signatories = $report['signatories'] ?? [];
    /** @var list<array<string, mixed>> $sections  every Akses 2 — Pengusahaan K3 input & formulir (K3PengusahaanReport) */
    $sections = $data['pengusahaan'] ?? [];
    $portrait = array_values(array_filter($sections, fn (array $s): bool => $s['orientation'] === 'portrait'));
    $landscape = array_values(array_filter($sections, fn (array $s): bool => $s['orientation'] === 'landscape'));
@endphp

{{-- =========================================================================
     SEGMEN 1: PORTRAIT (Sampul, Pengesahan & tabel input/formulir pengusahaan standar)
     ========================================================================= --}}
<div class="seg-cover-info">
    {{-- HALAMAN 1: SAMPUL --}}
    @include('reports.partials.pengusahaan-cover', [
        'title' => 'LAPORAN KINERJA K3 & KAM',
        'subtitle' => 'LAPORAN PENGUSAHAAN PEMBANGKIT',
        'unitName' => $unit['name'] ?? '',
        'monthName' => $period['month_name'] ?? '',
        'year' => $period['year'] ?? '',
    ])

    <div class="page-break"></div>

    {{-- HALAMAN 2: LEMBAR PENGESAHAN --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo">
                    <img src="/logo/sidebar-logo.png" alt="PLN">
                </td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">LEMBAR PENGESAHAN LAPORAN KINERJA K3 &amp; KAM</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? 'PLTD Poasia' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                    <div>Halaman: Lembar Pengesahan</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div style="margin: 25px 0 20px 0; font-size: 9.5px; line-height: 1.6; text-align: justify;">
            <p>
                Dokumen <strong>Laporan Kinerja Keselamatan, Kesehatan Kerja, Lingkungan Hidup, dan Keamanan (K3L &amp; KAM)</strong> ini disusun sebagai bentuk pertanggungjawaban pelaksanaan program keselamatan kerja, pemantauan kesiapan fasilitas tanggap darurat, pengelolaan lingkungan, serta kepatuhan regulasi ketenagakerjaan pada <strong>{{ $unit['name'] ?? 'Unit Layanan' }}</strong> untuk periode <strong>{{ $period['label'] ?? '' }}</strong>.
            </p>
            <p style="margin-top: 10px;">
                Seluruh data hasil inspeksi peralatan, checklist patroli, catatan kecelakaan kerja (Nihil), status sertifikasi kelaikan peralatan, serta matriks kesiapan fasilitas keselamatan telah diperiksa, diverifikasi kebenarannya, dan disahkan oleh pejabat yang berwenang di bawah ini.
            </p>
        </div>

        <div style="margin-top: 40px;">
            <div style="text-align: right; font-size: 9px; margin-bottom: 15px;">
                Kendari, {{ $period['formatted_date'] ?? date('d F Y') }}
            </div>
            <table class="sign-table">
                <tr>
                    <td>
                        <div class="sign-role">Dibuat Oleh:</div>
                        <div class="sign-position">{{ $signatories['officer_k3']['position'] ?? 'Officer K3L' }}</div>
                        <div class="sign-space">
                            @if(!empty($signatories['officer_k3']['signature']))
                                <img src="{{ $signatories['officer_k3']['signature'] }}" alt="TTD Officer K3">
                            @endif
                        </div>
                        <div class="sign-name">{{ ($signatories['officer_k3']['name'] ?? '') ?: '(...................................)' }}</div>
                    </td>
                    <td>
                        <div class="sign-role">Diperiksa Oleh:</div>
                        <div class="sign-position">{{ $signatories['tl_k3']['position'] ?? 'Team Leader K3 & Keamanan' }}</div>
                        <div class="sign-space">
                            @if(!empty($signatories['tl_k3']['signature']))
                                <img src="{{ $signatories['tl_k3']['signature'] }}" alt="TTD TL K3">
                            @endif
                        </div>
                        <div class="sign-name">{{ ($signatories['tl_k3']['name'] ?? '') ?: '(...................................)' }}</div>
                    </td>
                    <td>
                        <div class="sign-role">Disetujui Oleh:</div>
                        <div class="sign-position">{{ $signatories['manager']['position'] ?? 'Manager UL' }}</div>
                        <div class="sign-space">
                            @if(!empty($signatories['manager']['signature']))
                                <img src="{{ $signatories['manager']['signature'] }}" alt="TTD Manager">
                            @endif
                        </div>
                        <div class="sign-name">{{ ($signatories['manager']['name'] ?? '') ?: '(...................................)' }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="page-break"></div>

    @foreach($portrait as $section)
        @include('k3.laporan.partials.pengusahaan-section', ['section' => $section, 'unit' => $unit, 'period' => $period])
        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</div>

{{-- =========================================================================
     SEGMEN 2: LANDSCAPE (tabel input/formulir pengusahaan lebar, tanpa potongan kolom)
     ========================================================================= --}}
<div class="seg-tables-wide">
    @foreach($landscape as $section)
        @include('k3.laporan.partials.pengusahaan-section', ['section' => $section, 'unit' => $unit, 'period' => $period])
        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</div>
