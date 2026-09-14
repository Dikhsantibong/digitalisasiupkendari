@php
    /** @var array<string, mixed> $report */
    $groups = $report['groups'];
    $bearing = $report['bearing'];
    $statusLabel = $report['status'] === 'submitted' ? 'Terkirim (terkunci)' : 'Draft';
@endphp

{{-- SEGMENT A (portrait): cover + kop + informasi. --}}
<div class="seg-a">
{{-- COVER --}}
<div class="op-cover">
    <img src="/logo/sidebar-logo.png" alt="Logo">
    <div class="op-cover-org">PT PLN Nusantara Power</div>
    <div class="op-cover-sub">{{ $report['unit']['service_unit'] ?? 'Unit Pelaksana Pengendalian Pembangkitan Kendari' }}</div>
    <div class="op-cover-rule"></div>
    <div class="op-cover-title">Laporan<br>Logsheet Operator</div>
    <div class="op-cover-unit">{{ $report['unit']['name'] }}</div>
    <div class="op-cover-period">{{ $report['engine']['name'] }}</div>
    <div class="op-cover-period">{{ $report['date_label'] }}</div>
    <div class="op-cover-rule"></div>
    <div class="op-cover-footer">UP Kendari<small>Unit Pelaksana Pengendalian Pembangkitan Kendari</small></div>
</div>

@include('operasi.laporan.partials.letterhead', ['report' => $report, 'title' => 'Laporan Logsheet Operator'])

{{-- 1. INFORMASI --}}
<div class="op-h2">1. Informasi Logsheet</div>
<table class="op-data" style="width:70%">
    <tr><th style="width:30%">Unit</th><td style="text-align:left">{{ $report['unit']['name'] }}</td></tr>
    <tr><th>Mesin</th><td style="text-align:left">{{ $report['engine']['name'] }}</td></tr>
    <tr><th>Hari/Tanggal</th><td style="text-align:left">{{ $report['date_label'] }}</td></tr>
    <tr><th>Shift</th><td style="text-align:left">{{ $report['shift'] ? 'Shift '.$report['shift'] : '—' }}</td></tr>
    <tr><th>Status</th><td style="text-align:left">{{ $statusLabel }}</td></tr>
    <tr><th>No. Dokumen</th><td style="text-align:left">{{ $report['document_number'] }}</td></tr>
</table>
</div>

{{-- SEGMENT B (landscape): tabel input logsheet — LAYOUT SAMA seperti halaman
     input (jam sebagai baris, parameter sebagai kolom). Kertas halaman ini saja
     yang landscape (via merge), cover & lainnya tetap portrait. --}}
<div class="seg-b">
{{-- 2. PEMBACAAN PARAMETER PER JAM — header dikelompokkan (parent + sub-kolom,
     mis. Flow Meter → IN/OUT) sama seperti halaman input. --}}
<div class="op-h2">2. Pembacaan Parameter per Jam</div>
<table class="ls-data">
    <tr>
        <th rowspan="2">Jam</th>
        @foreach($groups as $g)
            @if($g['has_sub'])
                <th colspan="{{ count($g['members']) }}">{{ $g['name'] }}{{ $g['unit_of_measure'] ? ' ('.$g['unit_of_measure'].')' : '' }}</th>
            @else
                <th rowspan="2">{{ $g['unit_of_measure'] ? $g['name'].' ('.$g['unit_of_measure'].')' : $g['name'] }}</th>
            @endif
        @endforeach
    </tr>
    <tr>
        @foreach($groups as $g)
            @if($g['has_sub'])
                @foreach($g['members'] as $m)<th>{{ $m['sub_channel'] }}</th>@endforeach
            @endif
        @endforeach
    </tr>
    @foreach($report['rows'] as $row)
        <tr>
            <td><strong>{{ $row['time_slot'] }}</strong></td>
            @foreach($groups as $g)
                @foreach($g['members'] as $m)
                    <td>{{ $row['values']['p_'.$m['id']] ?? '' }}</td>
                @endforeach
            @endforeach
        </tr>
    @endforeach
</table>

{{-- 3. BEARING GENERATOR TEMPERATUR (horizontal — sama seperti input) --}}
@if($bearing)
    <div class="op-h2">3. Bearing Generator Temperatur{{ $bearing['unit_of_measure'] ? ' ('.$bearing['unit_of_measure'].')' : '' }}</div>
    <table class="ls-bearing">
        <tr>
            <th>JAM</th>
            @foreach($report['rows'] as $row)<th>{{ $row['time_slot'] }}</th>@endforeach
        </tr>
        <tr>
            <td><strong>Suhu</strong></td>
            @foreach($report['rows'] as $row)<td>{{ $row['values']['p_'.$bearing['id']] ?? '' }}</td>@endforeach
        </tr>
    </table>
@endif
</div>

{{-- SEGMENT C (portrait): ringkasan. --}}
<div class="seg-c">
{{-- 4. RINGKASAN HARIAN --}}
<div class="op-h2">4. Ringkasan Harian</div>
<table class="op-data" style="width:70%">
    @foreach($report['stats'] as $stat)
        <tr><th style="width:40%; text-align:left">{{ $stat['label'] }}</th><td style="text-align:right">{{ $stat['value'] }}</td></tr>
    @endforeach
</table>
</div>
