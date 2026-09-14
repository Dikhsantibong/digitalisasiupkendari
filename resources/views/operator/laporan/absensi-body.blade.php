@php
    /** @var array<string, mixed> $report */
    $days = $report['days'];
    $codes = $report['codes'];
@endphp

{{-- COVER --}}
<div class="op-cover">
    <img src="/logo/sidebar-logo.png" alt="Logo">
    <div class="op-cover-org">PT PLN Nusantara Power</div>
    <div class="op-cover-sub">{{ $report['unit']['service_unit'] ?? 'Unit Pelaksana Pengendalian Pembangkitan Kendari' }}</div>
    <div class="op-cover-rule"></div>
    <div class="op-cover-title">Laporan Absensi<br>&amp; Jadwal Shift</div>
    <div class="op-cover-unit">{{ $report['unit']['name'] }}</div>
    <div class="op-cover-period">{{ $report['group_label'] }}</div>
    <div class="op-cover-period">Periode <strong>{{ $report['period_label'] }}</strong></div>
    <div class="op-cover-rule"></div>
    <div class="op-cover-footer">UP Kendari<small>Unit Pelaksana Pengendalian Pembangkitan Kendari</small></div>
</div>

@include('operasi.laporan.partials.letterhead', ['report' => $report, 'title' => 'Laporan Absensi & Jadwal Kerja Shift'])

{{-- 1. INFORMASI --}}
<div class="op-h2">1. Informasi</div>
<table class="op-data" style="width:70%">
    <tr><th style="width:30%">Unit</th><td style="text-align:left">{{ $report['unit']['name'] }}</td></tr>
    <tr><th>Kelompok</th><td style="text-align:left">{{ $report['group_label'] }}</td></tr>
    <tr><th>Periode</th><td style="text-align:left">{{ $report['period_label'] }}</td></tr>
    <tr><th>No. Dokumen</th><td style="text-align:left">{{ $report['document_number'] }}</td></tr>
</table>

{{-- 2. JADWAL & ABSENSI --}}
<div class="op-h2 break-before">2. Jadwal &amp; Absensi</div>
@if(empty($report['employees']))
    <p class="op-muted">Belum ada pegawai {{ $report['group_type'] === 'shift' ? 'shift (dengan regu)' : 'non-shift' }} pada unit ini.</p>
@else
    <table class="ls-data">
        <tr>
            <th>NIP</th><th>Nama</th><th>Regu</th>
            @foreach($days as $d)
                <th class="{{ $d['is_holiday'] ? 'hol' : ($d['is_weekend'] ? 'wknd' : '') }}">{{ $d['day'] }}<br>{{ $d['dow'] }}</th>
            @endforeach
        </tr>
        @foreach($report['employees'] as $e)
            <tr>
                <td class="l">{{ $e['nip'] ?? '—' }}</td>
                <td class="l">{{ $e['name'] }}</td>
                <td>{{ $e['regu'] ?? '—' }}</td>
                @foreach($days as $d)
                    <td class="{{ $d['is_holiday'] ? 'hol' : ($d['is_weekend'] ? 'wknd' : '') }}">{{ $e['cells'][$d['day']] ?? '' }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endif

{{-- 3. REKAP KEHADIRAN --}}
<div class="op-h2 break-before">3. Rekap Kehadiran</div>
@if(empty($report['employees']))
    <p class="op-muted">Belum ada data.</p>
@else
    <table class="op-data">
        <tr>
            <th style="text-align:left">Nama</th><th>Regu</th>
            @foreach($codes as $c)<th>{{ $c['code'] }}</th>@endforeach
            <th>% Hadir</th>
        </tr>
        @foreach($report['employees'] as $e)
            <tr>
                <td style="text-align:left">{{ $e['name'] }}</td>
                <td class="c">{{ $e['regu'] ?? '—' }}</td>
                @foreach($codes as $c)<td class="c">{{ $e['recap'][$c['code']] ?? 0 }}</td>@endforeach
                <td class="c">{{ $e['percent'] !== null ? round($e['percent'] * 100).'%' : '—' }}</td>
            </tr>
        @endforeach
        <tr>
            <td style="text-align:left"><strong>TOTAL</strong></td>
            <td></td>
            @foreach($codes as $c)<td class="c"><strong>{{ $report['totals'][$c['code']] ?? 0 }}</strong></td>@endforeach
            <td></td>
        </tr>
    </table>
@endif

{{-- 4. KETERANGAN KODE --}}
<div class="op-h2">4. Keterangan Kode</div>
<table class="op-data" style="width:70%">
    <tr><th style="width:20%">Kode</th><th style="text-align:left">Keterangan</th></tr>
    @foreach($codes as $c)
        <tr><td class="c">{{ $c['code'] }}</td><td style="text-align:left">{{ $c['label'] }}</td></tr>
    @endforeach
</table>
