@php
    /**
     * Official 3-column kop (PLN logo · organisation lines · MKP logo) printed
     * at the top of every Laporan Operasi page.
     *
     * @var string $title
     * @var string $unitName
     */
@endphp
<table class="op-kop">
    <tr>
        <td rowspan="4" class="op-kop-logo op-kop-logo-left">
            <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="max-height: 42px; max-width: 120px;">
        </td>
        <td class="op-kop-cell">JASA PENDUKUNG TEKNIS UP KENDARI 11 &amp; 6 SITE - KIT</td>
        <td rowspan="4" class="op-kop-logo op-kop-logo-right">
            <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" style="max-height: 42px; max-width: 120px;">
        </td>
    </tr>
    <tr>
        <td class="op-kop-cell">{{ strtoupper($unitName) }}</td>
    </tr>
    <tr>
        <td class="op-kop-cell">LAPORAN PROJECT</td>
    </tr>
    <tr>
        <td class="op-kop-cell op-kop-title">{{ $title }}</td>
    </tr>
</table>
