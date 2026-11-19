{{-- Laporan UL letterhead shown above the spreadsheet editor: PLN logo + kop (no MKP logo, user 2026-11-18). --}}
<table style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="width:130px; vertical-align:middle;"><img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:46px;"></td>
        <td style="vertical-align:middle;">
            <div class="har-org">
                JASA PENDUKUNG TEKNIS UP KENDARI 11 &amp; 6 SITE - KIT<br>
                {{ mb_strtoupper($data['report']['ul']['name']) }}<br>
                <small>{{ mb_strtoupper($data['report']['title']) }} — {{ mb_strtoupper($data['report']['period']['label']) }}</small>
            </div>
        </td>
        <td style="width:130px;"></td>
    </tr>
</table>
<hr class="har-hr">
