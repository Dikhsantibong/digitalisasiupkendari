@php
    /** @var array<string, mixed> $data */
    $report = $data['report'];
    $numbers = $data['document']['numbers'] ?? [];
    $num = fn (string $key) => ! empty($numbers[$key]) ? ' ('.$numbers[$key].')' : '';
@endphp

<div class="k3-cover">
    <img src="/logo/sidebar-logo.png" alt="Logo" style="height:60px;">
    <div class="k3-cover-org">PT PLN Nusantara Power</div>
    <div class="k3-cover-sub">{{ $report['unit']['service_unit'] ?? 'Unit Pelaksana Pengendalian Pembangkitan Kendari' }}</div>
    <div class="k3-cover-rule"></div>
    <div class="k3-cover-title">Laporan Kinerja<br>K3 &amp; Keamanan</div>
    <div class="k3-cover-unit">{{ $report['unit']['name'] }}</div>
    <div class="k3-cover-period">Periode <strong>{{ $report['period']['label'] }}</strong></div>
    <div class="k3-cover-rule"></div>
    <div class="k3-cover-footer">UP Kendari<small>Unit Pelaksana Pengendalian Pembangkitan Kendari</small></div>
</div>

@include('k3.laporan.letterhead', ['data' => $data])

<div class="k3-h2">1. Time Frame Kinerja K3{{ $num('time_frame') }}</div>
@forelse($report['time_frame'] as $tf)
    @if($loop->first)
        <table class="k3-data">
            <tr><th>Kegiatan</th><th>PIC</th><th>Rencana</th><th>Realisasi</th></tr>
    @endif
            <tr>
                <td>{{ $tf['activity'] }}</td>
                <td>{{ $tf['pic'] ?? '—' }}</td>
                <td class="c">{{ $tf['plan'] }}</td>
                <td class="c">{{ $tf['real'] }}</td>
            </tr>
    @if($loop->last)</table>@endif
@empty
    <p class="k3-note">Belum ada data Time Frame.</p>
@endforelse

<div class="k3-h2">2. Laporan Kecelakaan (PAK/PAHK){{ $num('accidents') }}</div>
@if($report['accidents']['nihil'])
    <p class="k3-nihil">NIHIL — tidak ada kejadian pada periode ini.</p>
@else
    <table class="k3-data">
        <tr><th>Kategori</th><th>Lokasi</th><th>Luka Ringan</th><th>Luka Berat</th><th>Meninggal</th></tr>
        @foreach($report['accidents']['rows'] as $a)
            <tr>
                <td>{{ $a['category'] }}</td>
                <td>{{ $a['lokasi'] ?? '—' }}</td>
                <td class="c">{{ $a['luka_ringan'] }}</td>
                <td class="c">{{ $a['luka_berat'] }}</td>
                <td class="c">{{ $a['meninggal'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

<div class="k3-h2">3. Inspeksi APAR/APAB{{ $num('apar') }}</div>
@forelse($report['apar'] as $ap)
    @if($loop->first)
        <table class="k3-data">
            <tr><th>RFID</th><th>Lokasi</th><th>Kondisi</th><th>Exp Date</th><th>Status</th></tr>
    @endif
            <tr>
                <td>{{ $ap['rfid'] ?? '—' }}</td>
                <td>{{ $ap['location'] ?? '—' }}</td>
                <td>{{ $ap['kondisi'] ?? '—' }}</td>
                <td class="c">{{ $ap['exp_date'] ?? '—' }}</td>
                <td class="c">{{ $ap['status'] }}</td>
            </tr>
    @if($loop->last)</table>@endif
@empty
    <p class="k3-note">Belum ada APAR/APAB.</p>
@endforelse

<div class="k3-h2">4. Kesiapan Fasilitas Darurat{{ $num('emergency_tools') }}</div>
@forelse($report['emergency'] as $em)
    @if($loop->first)
        <table class="k3-data">
            <tr><th>Fasilitas</th><th>Total</th><th>Ready</th><th>Not Ready</th><th>% Kesiapan</th></tr>
    @endif
            <tr>
                <td>{{ $em['name'] }}</td>
                <td class="c">{{ $em['total'] }}</td>
                <td class="c">{{ $em['ready'] }}</td>
                <td class="c">{{ $em['not_ready'] }}</td>
                <td class="c">{{ $em['percent'] }}</td>
            </tr>
    @if($loop->last)</table>@endif
@empty
    <p class="k3-note">Belum ada data kesiapan fasilitas darurat.</p>
@endforelse

<div class="k3-h2">5. Rekap Patroli Keamanan (Kumulatif)</div>
@forelse($report['patrol'] as $pt)
    @if($loop->first)
        <table class="k3-data">
            <tr><th>Lokasi</th><th>Total Scan</th></tr>
    @endif
            <tr><td>{{ $pt['location'] }}</td><td class="c">{{ $pt['total'] }}</td></tr>
    @if($loop->last)</table>@endif
@empty
    <p class="k3-note">Belum ada log patroli.</p>
@endforelse

<div class="k3-h2">6. Sertifikasi Peralatan{{ $num('certificates') }}</div>
@forelse($report['certificates'] as $cert)
    @if($loop->first)
        <table class="k3-data">
            <tr><th>Jenis</th><th>Kategori</th><th>Lokasi</th><th>Uji Ulang</th><th>Status</th></tr>
    @endif
            <tr>
                <td>{{ $cert['jenis'] }}</td>
                <td>{{ $cert['category'] ?? '—' }}</td>
                <td>{{ $cert['lokasi'] ?? '—' }}</td>
                <td class="c">{{ $cert['uji_ulang_tanggal'] ?? '—' }}</td>
                <td class="c">{{ $cert['status'] }}</td>
            </tr>
    @if($loop->last)</table>@endif
@empty
    <p class="k3-note">Belum ada data sertifikat.</p>
@endforelse

<div class="k3-h2">7. Inspeksi Checklist{{ $num('inspections') }}</div>
@forelse($report['inspections'] as $ins)
    @if($loop->first)
        <table class="k3-data">
            <tr><th>Form</th><th>Tanggal</th><th>Jumlah Item</th><th>Ketua Tim</th></tr>
    @endif
            <tr>
                <td>{{ $ins['form_code'] }}</td>
                <td class="c">{{ $ins['date'] ?? '—' }}</td>
                <td class="c">{{ $ins['items'] }}</td>
                <td>{{ $ins['ketua_tim'] ?? '—' }}</td>
            </tr>
    @if($loop->last)</table>@endif
@empty
    <p class="k3-note">Belum ada inspeksi checklist.</p>
@endforelse

<div class="k3-h2">8. Lampiran</div>
@forelse($report['attachments'] as $att)
    <div class="k3-fig">
        <img src="{{ $att['url'] }}" alt="{{ $att['title'] }}">
        <figcaption><strong>{{ $att['title'] }}</strong>@if($att['category']) · {{ $att['category'] }}@endif</figcaption>
    </div>
@empty
    <p class="k3-note">Belum ada lampiran.</p>
@endforelse
