@php
    /** @var array<string, mixed> $data */
    $report = $data['report'];
    $numbers = $data['document']['numbers'] ?? [];
    $num = fn (string $key) => ! empty($numbers[$key]) ? ' ('.$numbers[$key].')' : '';

    $accidents = $report['accidents'];
    $accInjuries = ($accidents['nihil'] ?? false)
        ? 0
        : collect($accidents['rows'])->sum(fn ($r): int => (int) $r['luka_ringan'] + (int) $r['luka_berat'] + (int) $r['meninggal']);
    $patrolTotal = collect($report['patrol'])->sum('total');

    $sections = [
        'Executive Summary',
        'Daftar Isi',
        'Istilah dan Definisi',
        'Isi Laporan',
        'Time Frame Kinerja K3',
        'Laporan Kecelakaan (PAK/PAHK)',
        'Inspeksi APAR/APAB',
        'Kesiapan Fasilitas Darurat',
        'Rekap Patroli Keamanan',
        'Sertifikasi Peralatan',
        'Inspeksi Checklist',
        'Lampiran',
    ];

    $glossary = [
        ['K3', 'Keselamatan dan Kesehatan Kerja.'],
        ['SMK3', 'Sistem Manajemen Keselamatan dan Kesehatan Kerja.'],
        ['Time Frame', 'Rencana vs realisasi program kerja K3 pada periode berjalan.'],
        ['PAK', 'Penyakit Akibat Kerja.'],
        ['PAHK', 'Penyakit Akibat Hubungan Kerja.'],
        ['NIHIL', 'Tidak ada kejadian kecelakaan/penyakit akibat kerja pada periode ini.'],
        ['APAR', 'Alat Pemadam Api Ringan.'],
        ['APAB', 'Alat Pemadam Api Berat.'],
        ['P3K', 'Pertolongan Pertama Pada Kecelakaan.'],
        ['Patroli Keamanan', 'Ronda keamanan terjadwal yang dicatat melalui titik scan (RFID) per lokasi.'],
        ['Sertifikasi Peralatan', 'Riwayat pengujian & masa berlaku sertifikat alat (crane, bejana tekan, dll.).'],
    ];
@endphp

{{-- 1. COVER --}}
<div class="k3-cover" id="sec-1">
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

{{-- 2. EXECUTIVE SUMMARY --}}
<div class="k3-h2" id="sec-2">2. Executive Summary</div>
<p class="k3-p">
    Laporan ini merangkum kinerja K3 &amp; Keamanan {{ $report['unit']['name'] }} pada periode
    <strong>{{ $report['period']['label'] }}</strong>. Status kecelakaan kerja:
    <strong>{{ ($accidents['nihil'] ?? false) ? 'NIHIL' : $accInjuries.' korban tercatat' }}</strong>.
    Terdapat {{ count($report['time_frame']) }} program Time Frame, {{ count($report['apar']) }} unit APAR/APAB,
    {{ count($report['emergency']) }} jenis fasilitas darurat, {{ $patrolTotal }} total scan patroli,
    {{ count($report['certificates']) }} sertifikat peralatan, dan {{ count($report['inspections']) }} inspeksi checklist.
</p>
<table class="k3-data">
    <tr>
        <th>Kecelakaan</th><th>Time Frame</th><th>APAR/APAB</th><th>Scan Patroli</th><th>Sertifikat</th><th>Inspeksi</th>
    </tr>
    <tr>
        <td class="c">{{ ($accidents['nihil'] ?? false) ? 'NIHIL' : $accInjuries }}</td>
        <td class="c">{{ count($report['time_frame']) }}</td>
        <td class="c">{{ count($report['apar']) }}</td>
        <td class="c">{{ $patrolTotal }}</td>
        <td class="c">{{ count($report['certificates']) }}</td>
        <td class="c">{{ count($report['inspections']) }}</td>
    </tr>
</table>

{{-- 3. DAFTAR ISI --}}
<div class="k3-h2 break-before" id="sec-3">3. Daftar Isi</div>
@php
    $toc = array_merge([['Cover', 'sec-1']], collect($sections)->map(fn ($t, $i): array => [$t, 'sec-'.($i + 2)])->all());
@endphp
@foreach($toc as $i => [$tocTitle, $anchor])
    <table class="toc-item"><tr>
        <td class="n">{{ $i + 1 }}.</td>
        <td>{{ $tocTitle }}</td>
        <td class="dots"></td>
        <td class="pg"><a href="#{{ $anchor }}"></a></td>
    </tr></table>
@endforeach

{{-- 4. ISTILAH DAN DEFINISI --}}
<div class="k3-h2 break-before" id="sec-4">4. Istilah dan Definisi</div>
<table class="k3-data">
    <tr><th style="width:30%">Istilah</th><th>Definisi</th></tr>
    @foreach($glossary as [$term, $def])
        <tr><td><strong>{{ $term }}</strong></td><td>{{ $def }}</td></tr>
    @endforeach
</table>

{{-- 5. ISI LAPORAN --}}
<div class="k3-h2 break-before" id="sec-5">5. Isi Laporan</div>
<p class="k3-p">
    Bagian ini memuat rincian pelaksanaan program K3 &amp; Keamanan {{ $report['unit']['name'] }} periode
    {{ $report['period']['label'] }}: capaian Time Frame, catatan kecelakaan kerja, kesiapan sarana proteksi
    kebakaran &amp; tanggap darurat, patroli keamanan, sertifikasi peralatan, dan inspeksi berkala.
</p>

{{-- 6. TIME FRAME --}}
<div class="k3-h2 break-before" id="sec-6">6. Time Frame Kinerja K3{{ $num('time_frame') }}</div>
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

{{-- 7. LAPORAN KECELAKAAN --}}
<div class="k3-h2 break-before" id="sec-7">7. Laporan Kecelakaan (PAK/PAHK){{ $num('accidents') }}</div>
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

{{-- 8. INSPEKSI APAR/APAB --}}
<div class="k3-h2 break-before" id="sec-8">8. Inspeksi APAR/APAB{{ $num('apar') }}</div>
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

{{-- 9. KESIAPAN FASILITAS DARURAT --}}
<div class="k3-h2 break-before" id="sec-9">9. Kesiapan Fasilitas Darurat{{ $num('emergency_tools') }}</div>
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

{{-- 10. REKAP PATROLI KEAMANAN --}}
<div class="k3-h2 break-before" id="sec-10">10. Rekap Patroli Keamanan (Kumulatif)</div>
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

{{-- 11. SERTIFIKASI PERALATAN --}}
<div class="k3-h2 break-before" id="sec-11">11. Sertifikasi Peralatan{{ $num('certificates') }}</div>
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

{{-- 12. INSPEKSI CHECKLIST --}}
<div class="k3-h2 break-before" id="sec-12">12. Inspeksi Checklist{{ $num('inspections') }}</div>
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

{{-- 13. LAMPIRAN --}}
<div class="k3-h2 break-before" id="sec-13">13. Lampiran</div>
@forelse($report['attachments'] as $att)
    <div class="k3-fig">
        <img src="{{ $att['url'] }}" alt="{{ $att['title'] }}">
        <figcaption><strong>{{ $att['title'] }}</strong>@if($att['category']) · {{ $att['category'] }}@endif</figcaption>
    </div>
@empty
    <p class="k3-note">Belum ada lampiran.</p>
@endforelse
