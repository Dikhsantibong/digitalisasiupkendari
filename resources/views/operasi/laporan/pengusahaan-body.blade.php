@php
    /** @var array<string, mixed> $data  App\Services\Operasi\OperasiPengusahaanDocument::build() */
    $report = $data['report'] ?? [];
    $unit = $report['unit'] ?? [];
    $period = $report['period'] ?? [];
    $signatories = $report['signatories'] ?? [];
    $number = $data['document']['number'] ?? '';
    /** @var list<array<string, mixed>> $sections  every Akses 2 — Pengusahaan Operasi input (OperasiPengusahaanReport) */
    $sections = $data['pengusahaan'] ?? [];
    $portrait = array_values(array_filter($sections, fn (array $s): bool => $s['orientation'] === 'portrait'));
    $landscape = array_values(array_filter($sections, fn (array $s): bool => $s['orientation'] === 'landscape'));
    $shared = ['unit' => $unit, 'period' => $period, 'emptyText' => 'Belum diisi pada periode ini (menu Pengusahaan Operasi).'];
@endphp

{{-- =========================================================================
     SEGMEN 1: PORTRAIT (Sampul, Pengesahan & tabel input pengusahaan standar)
     ========================================================================= --}}
<div class="seg-cover-info">
    {{-- HALAMAN 1: SAMPUL --}}
    @include('reports.partials.pengusahaan-cover', [
        'title' => 'LAPORAN PENGUSAHAAN PEMBANGKIT',
        'subtitle' => 'BIDANG OPERASI',
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
                    <div class="pk-title">LEMBAR PENGESAHAN LAPORAN PENGUSAHAAN PEMBANGKIT (OPERASI)</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: {{ $number }}</div>
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
                Dokumen <strong>Laporan Pengusahaan Pembangkit Bidang Operasi</strong> ini memuat realisasi produksi energi, pemakaian sendiri,
                pemakaian dan penerimaan bahan bakar &amp; pelumas, penyaluran feeder, pasokan cadangan, catatan star-stop mesin, stok BBM harian,
                serta status Berita Acara BBM &amp; pelumas pada <strong>{{ $unit['name'] ?? 'Unit Pembangkit' }}</strong> untuk periode
                <strong>{{ $period['label'] ?? '' }}</strong>.
            </p>
            <p style="margin-top: 10px;">
                Seluruh angka disusun otomatis dari input Pengusahaan Operasi (Input Harian, Star-Stop, Feeder, Pasokan Cadangan, Penerimaan BBM,
                Resource Pembangkit dan Berita Acara), telah diperiksa kebenarannya, dan disahkan oleh pejabat yang berwenang di bawah ini.
            </p>
        </div>

        <div style="margin-top: 40px;">
            <div style="text-align: right; font-size: 9px; margin-bottom: 15px;">
                Kendari, {{ $period['formatted_date'] ?? '' }}
            </div>
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

    <div class="page-break"></div>

    @foreach($portrait as $section)
        @include('k3.laporan.partials.pengusahaan-section', ['section' => $section] + $shared)
        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</div>

{{-- =========================================================================
     SEGMEN 2: LANDSCAPE (tabel input pengusahaan lebar, tanpa potongan kolom)
     ========================================================================= --}}
<div class="seg-tables-wide">
    @foreach($landscape as $section)
        @include('k3.laporan.partials.pengusahaan-section', ['section' => $section] + $shared)
        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</div>
