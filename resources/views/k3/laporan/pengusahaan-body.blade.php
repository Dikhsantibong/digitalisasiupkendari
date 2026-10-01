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
     SEGMEN 1: PORTRAIT (Sampul & tabel input/formulir pengusahaan standar)
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
