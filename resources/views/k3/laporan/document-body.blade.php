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
    <svg class="k3-cover-bg" viewBox="0 0 794 1123" xmlns="http://www.w3.org/2000/svg">
        <polygon points="0,0 210,0 0,270" fill="#0b2545" />
        <polygon points="210,0 248,0 0,320 0,270" fill="#00a3e0" />
        <polygon points="248,0 262,0 0,338 0,320" fill="#f59e0b" />
        <polygon points="0,110 135,35 110,170 0,230" fill="#0080b0" opacity="0.25" />
        <polygon points="460,1123 794,520 794,1123" fill="#005b82" />
        <path d="M 0 715 Q 220 815 540 735 Q 568 725 565 750 C 560 780 480 960 470 1123 L 0 1123 Z" fill="#0b2545" />
        <path d="M 0 707 Q 220 807 540 727 Q 575 717 572 750 C 567 780 487 960 477 1123 L 470 1123 C 480 960 560 780 565 750 Q 568 725 540 735 Q 220 815 0 715 Z" fill="#f59e0b" />
    </svg>

    <div class="k3-cover-content">
        <div class="k3-cover-logos">
            <table class="k3-logos-table">
                <tr>
                    <td class="k3-logo-cell-left">
                        <img src="/logo/sidebar-logo.png" class="k3-logo-pln" alt="PLN Nusantara Power">
                    </td>
                    <td class="k3-logo-divider-cell">
                        <div class="k3-logo-vdiv"></div>
                    </td>
                    <td class="k3-logo-cell-right">
                        <img src="/logo/mkp.jpg" class="k3-logo-mkp" alt="Mitra Karya Prima">
                    </td>
                </tr>
            </table>
        </div>

        <div class="k3-cover-title-wrap">
            <h1 class="k3-cover-main-title">
                LAPORAN K3 LINGKUNGAN<br>PEMBANGKIT
            </h1>
            <div class="k3-cover-title-line"></div>
        </div>

        <div class="k3-cover-spec-box">
            <table class="k3-spec-table">
                <tr>
                    <td class="k3-spec-label">NAMA PEMBANGKIT</td>
                    <td class="k3-spec-colon">:</td>
                    <td class="k3-spec-val">{{ strtoupper($report['unit']['name'] ?? '') }}</td>
                </tr>
                <tr>
                    <td class="k3-spec-label">PERIODE PELAPORAN</td>
                    <td class="k3-spec-colon">:</td>
                    <td class="k3-spec-val">BULAN {{ strtoupper($report['period']['label'] ?? '') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="k3-cover-pillars-badge">
        <table class="k3-pillars-table">
            <tr>
                <td class="k3-pillar-item">
                    <svg class="k3-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <polyline points="9 12 11 14 15 10"/>
                    </svg>
                    <span class="k3-p-text">
                        <strong>ANDAL</strong><small>RELIABLE</small>
                    </span>
                </td>
                <td class="k3-pillar-sep">|</td>
                <td class="k3-pillar-item">
                    <svg class="k3-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    <span class="k3-p-text">
                        <strong>EFISIEN</strong><small>EFFICIENT</small>
                    </span>
                </td>
                <td class="k3-pillar-sep">|</td>
                <td class="k3-pillar-item">
                    <svg class="k3-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                    </svg>
                    <span class="k3-p-text">
                        <strong>BERSIH</strong><small>CLEAN</small>
                    </span>
                </td>
                <td class="k3-pillar-sep">|</td>
                <td class="k3-pillar-item">
                    <svg class="k3-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                    <span class="k3-p-text">
                        <strong>AMAN</strong><small>SAFE</small>
                    </span>
                </td>
            </tr>
        </table>
    </div>
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
