{{-- Shared HAR letterhead: logo + organisation + ISO document box. Used as the
     banner above the spreadsheet editor and prepended to the grid-mode PDF, so
     Excel mode shows the same kop + logo as the text mode. The ISO number is a
     default; it stays editable inside the document. --}}
<table style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="width:70px; vertical-align:top;">
            <img src="/logo/sidebar-logo.png" alt="Logo" style="height:52px;">
        </td>
        <td style="vertical-align:top;">
            <div class="har-org">
                {{ $data['report']['unit']['service_unit'] ?? 'UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI' }}<br>
                UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI<br>
                <small>SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)</small>
            </div>
        </td>
        <td style="width:38%; vertical-align:top;">
            <table class="har-meta">
                <tr><td>No. Dokumen</td><td>{{ $data['document']['number'] }}</td></tr>
                <tr><td>Revisi</td><td>{{ $data['document']['revision'] ?? '00' }}</td></tr>
                <tr><td>Halaman</td><td>1 dari 1</td></tr>
            </table>
        </td>
    </tr>
</table>
<div class="har-title">{{ $data['document']['title'] }} — {{ $data['report']['unit']['name'] }} · {{ $data['report']['period']['label'] }}</div>
<hr class="har-hr">
