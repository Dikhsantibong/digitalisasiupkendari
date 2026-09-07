{{-- Shared letterhead: logo + organisation + document box. Used as the banner
     above the spreadsheet editor and prepended to the spreadsheet-mode PDF, so
     the Excel mode shows the same header/logo as the text mode. --}}
<table style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="width:70px; vertical-align:top;">
            <img src="/logo/sidebar-logo.png" alt="Logo" style="height:52px;">
        </td>
        <td style="vertical-align:top;">
            <div class="ba-org">
                UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI<br>
                UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI<br>
                <small>SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)</small>
            </div>
        </td>
        <td style="width:38%; vertical-align:top;">
            <table class="ba-meta">
                <tr><td>No. Dokumen</td><td>SMT-FM-EPI-01.04</td></tr>
                <tr><td>Revisi</td><td>{{ $document['revision'] ?? '00' }}</td></tr>
                <tr><td>Tanggal</td><td>{{ $document['revision_date'] ?? '' }}</td></tr>
                <tr><td>Halaman</td><td>1 dari 1</td></tr>
            </table>
        </td>
    </tr>
</table>
<hr class="ba-hr">
