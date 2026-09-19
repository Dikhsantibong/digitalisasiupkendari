@php
    /**
     * Kop of the Logistik & Gudang sheet PDFs: PLN logo, three lines, MKP logo.
     *
     * @var array{kop: string} $sheet
     * @var \App\Models\Unit $unit
     * @var string|null $logoLeft
     * @var string|null $logoRight
     */
@endphp
<table class="kop">
    <tr>
        <td rowspan="3" class="logo">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
        <td class="line">JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE -KIT</td>
        <td rowspan="3" class="logo">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
    </tr>
    <tr><td class="line">LAPORAN PROJECT SENTRAL {{ strtoupper($unit->name) }}</td></tr>
    <tr><td class="line">{{ $sheet['kop'] }}</td></tr>
</table>
