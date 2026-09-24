{{-- Kop resmi formulir K3 (PLN NP - MKP) --}}
<table class="kop-table">
    <tr>
        <td rowspan="4" class="kop-logo">
            @if(!empty($data['logo_pln']))
                <img src="{{ $data['logo_pln'] }}" alt="PLN Nusantara Power" style="max-height: 34px; max-width: 110px;">
            @else
                <strong style="color: #0C7DBB;">PLN Nusantara Power</strong>
            @endif
        </td>
        <td class="kop-line" style="font-size: 11px;">JASA PENDUKUNG TEKNIK 11 &amp; 6 SITE &ndash; KIT</td>
        <td rowspan="4" class="kop-logo">
            @if(!empty($data['logo_mkp']))
                <img src="{{ $data['logo_mkp'] }}" alt="MKP" style="max-height: 34px; max-width: 110px;">
            @else
                <strong>MKP</strong>
            @endif
        </td>
    </tr>
    <tr><td class="kop-line" style="font-size: 10.5px;">{{ mb_strtoupper($data['unit_name']) }}</td></tr>
    <tr><td class="kop-line" style="font-size: 10px;">LAPORAN PROJECT</td></tr>
    <tr><td class="kop-line" style="font-size: 11px;">{{ mb_strtoupper($data['kop_title'] ?? $data['title']) }}</td></tr>
</table>

<table class="info-table">
    <tr>
        <td style="width: 34%;"><strong>Periode</strong> : {{ $data['period_label'] }}</td>
        <td style="width: 33%;"><strong>No. Dokumen</strong> : {{ $data['document_number'] }} (Rev. {{ $data['revision'] }})</td>
        <td style="width: 33%;"><strong>Tanggal Efektif</strong> : {{ $data['effective_date'] }}</td>
    </tr>
</table>
