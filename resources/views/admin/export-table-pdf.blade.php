@php
    /**
     * A4-landscape PDF export for Admin Master Pegawai & Pengguna tables.
     *
     * @var array<string, mixed> $table
     * @var string $unitHeaderName
     */
@endphp
@extends('k3.input.layouts.export')

@section('content')
    <table class="k3-official-kop">
        <tr>
            <td rowspan="4" class="kop-logo-left">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="max-height: 42px; max-width: 120px;">
            </td>
            <td class="kop-center-cell">JASA PENDUKUNG TEKNIS UP KENDARI 11 &amp; 6 SITE - KIT</td>
            <td rowspan="4" class="kop-logo-right">
                <img src="/logo/mkp.jpg" alt="PT Mitra Karya Prima" style="max-height: 42px; max-width: 120px;">
            </td>
        </tr>
        <tr>
            <td class="kop-center-cell">{{ $unitHeaderName }}</td>
        </tr>
        <tr>
            <td class="kop-center-cell">ADMINISTRASI &amp; MASTER DATA</td>
        </tr>
        <tr>
            <td class="kop-center-cell kop-section-title">{{ $table['title'] }}</td>
        </tr>
    </table>

    <div class="k3x-heading">{{ $table['title'] }}</div>
    <div class="k3x-subheading">
        Cakupan: {{ $table['unit'] }}@if($table['period'] !== '') &nbsp;&middot;&nbsp; Dicetak: {{ $table['period'] }}@endif
    </div>

    @if(! $table['has_data'])
        @include('k3.laporan.partials.no-data', ['message' => 'Tidak ada data yang sesuai dengan filter yang dipilih.'])
    @else
        @include('k3.input.partials.table', ['table' => $table])
    @endif
@endsection
