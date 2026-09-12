{{-- Shared K3 letterhead: logo + organisation + ISO document box. --}}
<table style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="width:70px; vertical-align:top;">
            <img src="/logo/sidebar-logo.png" alt="Logo" style="height:52px;">
        </td>
        <td style="vertical-align:top;">
            <div class="k3-org">
                {{ $data['report']['unit']['service_unit'] ?? 'UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI' }}<br>
                UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI<br>
                <small>SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)</small>
            </div>
        </td>
        <td style="width:38%; vertical-align:top;">
            <table class="k3-meta">
                <tr><td>No. Dokumen</td><td>{{ $data['document']['number'] }}</td></tr>
                <tr><td>Revisi</td><td>{{ $data['document']['revision'] ?? '00' }}</td></tr>
                <tr><td>Halaman</td><td>1 dari 1</td></tr>
            </table>
        </td>
    </tr>
</table>
<div class="k3-title">{{ $data['document']['title'] }} — {{ $data['report']['unit']['name'] }} · {{ $data['report']['period']['label'] }}</div>
<hr class="k3-hr">
