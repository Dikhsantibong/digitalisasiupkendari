@php
    /**
     * Official 3-column kop (PLN logo · organisation lines · MKP logo) printed at
     * the top of every report page.
     *
     * @var string $title
     * @var string $unitHeaderName
     * @var string|null $line1
     * @var string|null $line2
     */
    $line1 ??= 'JASA PENDUKUNG TEKNIS UP KENDARI 11 & 6 SITE - KIT';
    $line2 ??= $unitHeaderName;
@endphp
<table class="k3-official-kop">
    <tr>
        <td rowspan="4" class="kop-logo-left">
            <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="max-height: 42px; max-width: 120px;">
        </td>
        <td class="kop-center-cell">{{ $line1 }}</td>
        <td rowspan="4" class="kop-logo-right">
            <img src="/logo/k3.png" alt="Logo K3" style="max-height: 42px; max-width: 120px;">
        </td>
    </tr>
    <tr>
        <td class="kop-center-cell">{{ $line2 }}</td>
    </tr>
    <tr>
        <td class="kop-center-cell">LAPORAN PROJECT</td>
    </tr>
    <tr>
        <td class="kop-center-cell kop-section-title">{{ $title }}</td>
    </tr>
</table>
