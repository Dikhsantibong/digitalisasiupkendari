@php
    /** @var array<string, mixed> $data */
    $report = $data['report'];
    $numbers = $data['document']['numbers'] ?? [];
    $num = fn (string $key) => ! empty($numbers[$key]) ? ' <small>('.$numbers[$key].')</small>' : '';
    $rupiah = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $dayMap = function (array $map): string {
        $parts = [];
        foreach ($map as $d => $v) {
            if ($v !== null && $v !== '') {
                $parts[] = "{$d}:{$v}";
            }
        }
        return $parts === [] ? '—' : implode(' · ', $parts);
    };

    $rowsForTypes = function (array $codes) use ($report): array {
        $wanted = array_map('strtolower', $codes);

        return collect($report['wo_by_type'])
            ->filter(fn ($g): bool => in_array(strtolower($g['type']), $wanted, true))
            ->flatMap(fn ($g) => $g['rows'])->values()->all();
    };
    $rowsForWaiting = function (array $keys) use ($report): array {
        return collect($report['wo_waiting'])
            ->filter(fn ($g): bool => in_array($g['key'], $keys, true))
            ->flatMap(fn ($g) => $g['rows'])->values()->all();
    };

    $woPm = $rowsForTypes(['PM']);
    $woPdm = $rowsForTypes(['PDM', 'PdM']);
    $woEnji = $rowsForTypes(['ENJI']);
    $waitingShutdown = $rowsForWaiting(['shutdown']);
    $waitingMaterialJasa = $rowsForWaiting(['material', 'jasa']);
    $waitingCount = collect($report['wo_waiting'])->sum(fn ($g): int => count($g['rows']));
    $totalTasks = collect($report['activities'])->sum(fn ($a): int => count($a['tasks']));

    $sections = [
        'Executive Summary',
        'Daftar Isi',
        'Istilah dan Definisi',
        'Isi Laporan',
        'Work Order Summary (Fix)',
        'Akumulasi Biaya Pemeliharaan',
        'Rekapitulasi Work Order Task',
        'Work Order PM (Preventive Maintenance)',
        'Work Order PdM (Predictive Maintenance)',
        'Work Order ENJI (Engineering)',
        'Work Order Waiting Shutdown',
        'Work Order Waiting Material & Jasa',
        'Lampiran',
    ];

    $glossary = [
        ['WO (Work Order)', 'Perintah kerja pemeliharaan yang menjadi dasar pelaksanaan pekerjaan.'],
        ['SR (Service Request)', 'Permintaan pekerjaan/perbaikan sebelum diterbitkan menjadi Work Order.'],
        ['PM (Preventive Maintenance)', 'Pemeliharaan terjadwal untuk mencegah kerusakan berdasarkan jam operasi/kalender.'],
        ['PdM (Predictive Maintenance)', 'Pemeliharaan berbasis kondisi melalui pemantauan/pengukuran parameter.'],
        ['CM (Corrective Maintenance)', 'Pemeliharaan perbaikan setelah ditemukan kelainan/kerusakan.'],
        ['ENJI (Engineering)', 'Pekerjaan rekayasa/modifikasi untuk peningkatan keandalan atau kinerja.'],
        ['Waiting Shutdown', 'Work Order yang menunggu kesempatan mesin berhenti (shutdown) untuk dikerjakan.'],
        ['Waiting Material / Jasa', 'Work Order yang tertunda karena menunggu ketersediaan material atau jasa pihak ketiga.'],
        ['HARMES', 'Pemeliharaan Mesin — log kegiatan harian pemeliharaan pembangkit.'],
        ['Rencana vs Realisasi', 'Perbandingan jadwal pemeliharaan yang direncanakan terhadap yang terealisasi.'],
    ];
@endphp

{{-- 1. COVER --}}
<div class="har-cover" id="sec-1">
    <img src="/logo/sidebar-logo.png" alt="Logo">
    <div class="har-cover-org">PT PLN Nusantara Power</div>
    <div class="har-cover-sub">{{ $report['unit']['service_unit'] ?? 'Unit Pelaksana Pengendalian Pembangkitan Kendari' }}</div>
    <div class="har-cover-rule"></div>
    <div class="har-cover-title">Laporan Kinerja<br>Pemeliharaan</div>
    <div class="har-cover-unit">{{ $report['unit']['name'] }}</div>
    <div class="har-cover-period">Periode <strong>{{ $report['period']['label'] }}</strong></div>
    <div class="har-cover-rule"></div>
    <div class="har-cover-footer">UP Kendari<small>Unit Pelaksana Pengendalian Pembangkitan Kendari</small></div>
</div>

@include('har.laporan.letterhead', ['data' => $data])

{{-- 2. EXECUTIVE SUMMARY --}}
<div class="har-h2" id="sec-2">2. Executive Summary</div>
<p class="har-p">
    Laporan ini merangkum kinerja pemeliharaan {{ $report['unit']['name'] }} pada periode
    <strong>{{ $report['period']['label'] }}</strong>. Sepanjang periode tercatat
    <strong>{{ $report['wo_summary']['total'] }}</strong> Work Order dengan tingkat penyelesaian
    <strong>{{ $report['wo_summary']['percent'] }}%</strong> ({{ $report['wo_summary']['complete'] }} selesai,
    {{ $report['wo_summary']['open'] }} berjalan), serta {{ $report['sr_summary']['total'] }} Service Request
    ({{ $report['sr_summary']['open'] }} open). Terdapat {{ $waitingCount }} Work Order berstatus menunggu.
    Total biaya pemeliharaan efektif periode ini <strong>{{ $rupiah($report['cost']['effective_total']) }}</strong>
    (akumulasi tahun berjalan {{ $rupiah($report['cost']['ytd']) }}).
</p>
<table class="har-data">
    <tr><th>Total WO</th><th>% Complete</th><th>Total SR</th><th>WO Waiting</th><th>Biaya Efektif</th></tr>
    <tr>
        <td class="c">{{ $report['wo_summary']['total'] }}</td>
        <td class="c">{{ $report['wo_summary']['percent'] }}%</td>
        <td class="c">{{ $report['sr_summary']['total'] }}</td>
        <td class="c">{{ $waitingCount }}</td>
        <td class="r">{{ $rupiah($report['cost']['effective_total']) }}</td>
    </tr>
</table>

{{-- 3. DAFTAR ISI --}}
<div class="har-h2 break-before" id="sec-3">3. Daftar Isi</div>
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
<div class="har-h2 break-before" id="sec-4">4. Istilah dan Definisi</div>
<table class="har-data">
    <tr><th style="width:30%">Istilah</th><th>Definisi</th></tr>
    @foreach($glossary as [$term, $def])
        <tr><td><strong>{{ $term }}</strong></td><td>{{ $def }}</td></tr>
    @endforeach
</table>

{{-- 5. ISI LAPORAN --}}
<div class="har-h2 break-before" id="sec-5">5. Isi Laporan</div>
<p class="har-p">
    Bagian ini memuat rincian pelaksanaan pemeliharaan {{ $report['unit']['name'] }} periode
    {{ $report['period']['label'] }}, meliputi ringkasan Service Request, rencana versus realisasi
    pemeliharaan, dan log kegiatan HARMES.
</p>

<div class="har-h3">5.1 Ringkasan Service Request{!! $num('sr_summary') !!}</div>
<table class="har-data">
    <tr><th>Total SR</th><th>Open</th><th>Close</th><th>Per Kategori</th></tr>
    <tr>
        <td class="c">{{ $report['sr_summary']['total'] }}</td>
        <td class="c">{{ $report['sr_summary']['open'] }}</td>
        <td class="c">{{ $report['sr_summary']['close'] }}</td>
        <td>{{ collect($report['sr_summary']['by_category'])->map(fn ($c) => $c['category'].': '.$c['count'])->implode(' · ') ?: '—' }}</td>
    </tr>
</table>

<div class="har-h3">5.2 Rencana vs Realisasi{!! $num('schedules') !!}</div>
@forelse($report['schedules'] as $scope)
    <p><strong>{{ $scope['scope'] }}</strong></p>
    <table class="har-data">
        <tr><th>Mesin</th><th>Rencana (tgl:kode)</th><th>Realisasi (tgl:kode)</th></tr>
        @foreach($scope['rows'] as $r)
            <tr>
                <td>{{ $r['engine'] }}</td>
                <td>{{ $dayMap($r['rencana']) }}</td>
                <td>{{ $dayMap($r['realisasi']) }}</td>
            </tr>
        @endforeach
    </table>
@empty
    <p class="har-note">Belum ada jadwal.</p>
@endforelse

<div class="har-h3">5.3 Log Kegiatan HARMES{!! $num('activities') !!}</div>
@forelse($report['activities'] as $a)
    @if($loop->first)
        <table class="har-data">
            <tr>
                <th>Tanggal</th><th>Mesin</th><th>Jenis</th><th>Uraian Kegiatan</th>
                <th>Material</th><th>Hasil</th><th>No. WO/SR</th>
            </tr>
    @endif
            <tr>
                <td class="c">{{ $a['date'] ?? '—' }}</td>
                <td>{{ $a['engine'] ?? '—' }}</td>
                <td class="c">{{ $a['type'] ?? '—' }}</td>
                <td>{{ implode('; ', $a['tasks']) ?: ($a['keterangan'] ?? '—') }}</td>
                <td>{{ collect($a['materials'])->map(fn ($m) => $m['name'].' ('.($m['quantity'] ?? '').($m['unit_of_measure'] ?? '').')')->implode(', ') ?: '—' }}</td>
                <td class="c">{{ $a['work_result'] ?? '—' }}</td>
                <td class="c">{{ $a['no_wo'] ?? $a['no_sr'] ?? '—' }}</td>
            </tr>
    @if($loop->last)
        </table>
    @endif
@empty
    <p class="har-note">Belum ada log kegiatan.</p>
@endforelse

{{-- 6. WORK ORDER SUMMARY (FIX) --}}
<div class="har-h2 break-before" id="sec-6">6. Work Order Summary (Fix){!! $num('wo_summary') !!}</div>
<table class="har-data">
    <tr><th>Total WO</th><th>Complete (Fix)</th><th>Open</th><th>% Complete</th></tr>
    <tr>
        <td class="c">{{ $report['wo_summary']['total'] }}</td>
        <td class="c">{{ $report['wo_summary']['complete'] }}</td>
        <td class="c">{{ $report['wo_summary']['open'] }}</td>
        <td class="c">{{ $report['wo_summary']['percent'] }}%</td>
    </tr>
</table>

{{-- 7. AKUMULASI BIAYA PEMELIHARAAN --}}
<div class="har-h2 break-before" id="sec-7">7. Akumulasi Biaya Pemeliharaan{!! $num('cost') !!}</div>
<table class="har-data">
    <tr><th>Jasa (WO)</th><th>Material (WO)</th><th>Total Otomatis</th><th>Efektif ({{ $report['cost']['source'] }})</th><th>Akumulasi YTD</th></tr>
    <tr>
        <td class="r">{{ $rupiah($report['cost']['auto_service']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['auto_material']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['auto_total']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['effective_total']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['ytd']) }}</td>
    </tr>
</table>
<p class="har-muted">
    Sumber biaya: {{ $report['cost']['source'] === 'manual' ? 'input manual' : 'akumulasi otomatis dari Work Order' }}.
    YTD = akumulasi Januari s.d. bulan laporan.
</p>

{{-- 8. REKAPITULASI WORK ORDER TASK --}}
<div class="har-h2 break-before" id="sec-8">8. Rekapitulasi Work Order Task{!! $num('wo_by_type') !!}</div>
<table class="har-data">
    <tr><th>Jenis Work Order</th><th>Jumlah WO</th><th>Porsi</th></tr>
    @forelse($report['wo_by_type'] as $g)
        <tr>
            <td>{{ $g['type'] }}</td>
            <td class="c">{{ count($g['rows']) }}</td>
            <td class="c">{{ $report['wo_summary']['total'] > 0 ? round(count($g['rows']) / $report['wo_summary']['total'] * 100).'%' : '—' }}</td>
        </tr>
    @empty
        <tr><td class="c" colspan="3">Tidak ada Work Order.</td></tr>
    @endforelse
    <tr>
        <td><strong>Total</strong></td>
        <td class="c"><strong>{{ $report['wo_summary']['total'] }}</strong></td>
        <td class="c"><strong>100%</strong></td>
    </tr>
</table>
<p class="har-muted">
    Total uraian task (dari log kegiatan HARMES): <strong>{{ $totalTasks }}</strong> item pada
    {{ count($report['activities']) }} kegiatan.
</p>

{{-- 9. WO PM (tabel lengkap) --}}
<div class="har-h2 break-before" id="sec-9">9. Work Order PM (Preventive Maintenance)</div>
@include('har.laporan.partials.wo-table', ['rows' => $woPm])

{{-- 10. WO PdM (tabel lengkap) --}}
<div class="har-h2 break-before" id="sec-10">10. Work Order PdM (Predictive Maintenance)</div>
@include('har.laporan.partials.wo-table', ['rows' => $woPdm])

{{-- 11. WO ENJI (tabel lengkap) --}}
<div class="har-h2 break-before" id="sec-11">11. Work Order ENJI (Engineering)</div>
@include('har.laporan.partials.wo-table', ['rows' => $woEnji])

{{-- 12. WO WAITING SHUTDOWN --}}
<div class="har-h2 break-before" id="sec-12">12. Work Order Waiting Shutdown</div>
@include('har.laporan.partials.waiting-table', ['rows' => $waitingShutdown])

{{-- 13. WO WAITING MATERIAL & JASA --}}
<div class="har-h2 break-before" id="sec-13">13. Work Order Waiting Material &amp; Jasa</div>
@include('har.laporan.partials.waiting-table', ['rows' => $waitingMaterialJasa])

{{-- 14. LAMPIRAN --}}
<div class="har-h2 break-before" id="sec-14">14. Lampiran</div>
@forelse($report['attachments'] as $a)
    <div class="har-fig">
        <img src="{{ $a['url'] }}" alt="{{ $a['title'] }}">
        <figcaption>
            <strong>{{ $a['title'] }}</strong>@if($a['engine']) · {{ $a['engine'] }}@endif @if($a['taken_date']) · {{ $a['taken_date'] }}@endif
            @if($a['caption'])<br>{{ $a['caption'] }}@endif
        </figcaption>
    </div>
@empty
    <p class="har-note">Belum ada lampiran foto.</p>
@endforelse
