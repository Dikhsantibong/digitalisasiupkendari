{{-- Kop lembar Daily Meeting (PLN kiri · baris kop · MKP kanan). --}}
<table class="kop">
    <tr>
        <td class="logo" rowspan="4">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
        <td class="line">JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE -KIT</td>
        <td class="logo" rowspan="4">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
    </tr>
    <tr><td class="line">{{ strtoupper($unit->name) }}</td></tr>
    <tr><td class="line">LAPORAN PROJECT</td></tr>
    <tr><td class="line">{{ $title }}</td></tr>
</table>
