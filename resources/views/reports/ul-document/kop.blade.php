@php
    /**
     * Official kop (PLN logo · organisation lines) of the Laporan UL pages —
     * without the MKP logo (user, 2026-11-18).
     *
     * @var string $title
     * @var string $ulName
     */
@endphp
<table class="k3-official-kop">
    <tr>
        <td rowspan="4" class="kop-logo-left">
            <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="max-height: 42px; max-width: 120px;">
        </td>
        <td class="kop-center-cell">JASA PENDUKUNG TEKNIS UP KENDARI 11 &amp; 6 SITE - KIT</td>
    </tr>
    <tr><td class="kop-center-cell">{{ mb_strtoupper($ulName) }}</td></tr>
    <tr><td class="kop-center-cell">LAPORAN UNIT LAYANAN</td></tr>
    <tr><td class="kop-center-cell kop-section-title">{{ $title }}</td></tr>
</table>
