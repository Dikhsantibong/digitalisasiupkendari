{{-- Laporan letterhead: logo + organisation + report title/period. Shown above
     the spreadsheet editor and prepended to the spreadsheet-mode PDF so the
     Excel mode carries the same header/logo as the text mode. --}}
@php
    /** @var array<string, mixed> $report */
    $unitName = $report['unit']['name'] ?? '';
    $serviceUnit = $report['unit']['service_unit'] ?? 'UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI';
    $engineName = $report['engine']['name'] ?? null;
    $periodLabel = $report['period']['label'] ?? '';
@endphp
<table style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="width:70px; vertical-align:top;">
            <img src="/logo/sidebar-logo.png" alt="Logo" style="height:52px;">
        </td>
        <td style="vertical-align:top;">
            <div class="ba-org">
                UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI<br>
                {{ \Illuminate\Support\Str::upper($serviceUnit) }}<br>
                <small>SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)</small>
            </div>
        </td>
    </tr>
</table>
<div class="ba-title">
    {{ \Illuminate\Support\Str::upper($title) }}<br>
    {{ $unitName }}@if($engineName) — {{ $engineName }}@endif · Periode {{ $periodLabel }}
</div>
<hr class="ba-hr">
