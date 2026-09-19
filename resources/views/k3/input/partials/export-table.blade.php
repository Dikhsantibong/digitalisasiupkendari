@php
    /**
     * Kop + heading + table (or red line) of one K3 input table in a PDF export.
     *
     * @var array<string, mixed> $table
     * @var string $unitHeaderName
     * @var bool $first
     */
@endphp
@if(! $first)
    <div class="page-break"></div>
@endif

@include('k3.laporan.partials.kop', ['title' => $table['title'], 'unitHeaderName' => $unitHeaderName])

<div class="k3x-heading">{{ $table['title'] }}@if($table['document_number'] !== '') ({{ $table['document_number'] }})@endif</div>
<div class="k3x-subheading">
    Unit: {{ $table['unit'] }}@if($table['period'] !== '') &nbsp;·&nbsp; Periode: {{ $table['period'] }}@endif
</div>

@if(! $table['has_data'])
    @include('k3.laporan.partials.no-data', ['message' => 'Belum ada data untuk periode ini.'])
@else
    @if(! $table['saved'])
        <div class="k3x-note">Belum ada data tersimpan untuk periode ini — menampilkan daftar/template yang tampil di halaman input.</div>
    @endif
    @include('k3.input.partials.table', ['table' => $table])
@endif
