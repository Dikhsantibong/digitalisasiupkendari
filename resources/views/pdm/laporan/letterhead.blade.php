{{-- Laporan PdM letterhead shown above the spreadsheet editor: logos + kop + document number. --}}
<table style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="width:130px; vertical-align:middle;"><img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:46px;"></td>
        <td style="vertical-align:middle;">
            <div class="har-org">
                JASA PENDUKUNG TEKNIS UP KENDARI 11 &amp; 6 SITE - KIT<br>
                {{ strtoupper($data['report']['unit']['name']) }}<br>
                <small>{{ $data['document']['title'] }} — {{ strtoupper($data['report']['period']['label']) }}</small>
            </div>
        </td>
        <td style="width:130px; text-align:right; vertical-align:middle;"><img src="/logo/mkp.jpg" alt="Mitra Karya Prima" style="height:46px;"></td>
    </tr>
</table>
<hr class="har-hr">
