@php
    /**
     * Official 3-column kop (PLN logo · organisation lines · MKP logo) of the
     * Laporan Logistik front pages.
     *
     * @var string $title
     * @var string $unitName
     */
@endphp
<table class="k3-official-kop">
    <tr>
        <td rowspan="4" class="kop-logo-left">
            <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="max-height: 42px; max-width: 120px;">
        </td>
        <td class="kop-center-cell">JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE - KIT</td>
        <td rowspan="4" class="kop-logo-right lg-kop-logo-right">
            <img src="/logo/mkp.jpg" alt="Mitra Karya Prima">
        </td>
    </tr>
    <tr><td class="kop-center-cell">LAPORAN PROJECT SENTRAL {{ strtoupper($unitName) }}</td></tr>
    <tr><td class="kop-center-cell">LAPORAN LOGISTIK &amp; GUDANG</td></tr>
    <tr><td class="kop-center-cell kop-section-title">{{ $title }}</td></tr>
</table>
