@php
    /** @var array<string, mixed> $data */
    $report = $data['report'];
    $numbers = $data['document']['numbers'] ?? [];
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
@endphp

@include('har.laporan.letterhead', ['data' => $data])

<div class="har-h2">1. Service Request Summary @if(!empty($numbers['sr_summary']))<small>({{ $numbers['sr_summary'] }})</small>@endif</div>
<table class="har-data">
    <tr>
        <th>Total SR</th><th>Open</th><th>Close</th><th>Per Kategori</th>
    </tr>
    <tr>
        <td class="c">{{ $report['sr_summary']['total'] }}</td>
        <td class="c">{{ $report['sr_summary']['open'] }}</td>
        <td class="c">{{ $report['sr_summary']['close'] }}</td>
        <td>{{ collect($report['sr_summary']['by_category'])->map(fn ($c) => $c['category'].': '.$c['count'])->implode(' · ') ?: '—' }}</td>
    </tr>
</table>

<div class="har-h2">2. Work Order Summary @if(!empty($numbers['wo_summary']))<small>({{ $numbers['wo_summary'] }})</small>@endif</div>
<table class="har-data">
    <tr><th>Total WO</th><th>Complete</th><th>Open</th><th>% Complete</th></tr>
    <tr>
        <td class="c">{{ $report['wo_summary']['total'] }}</td>
        <td class="c">{{ $report['wo_summary']['complete'] }}</td>
        <td class="c">{{ $report['wo_summary']['open'] }}</td>
        <td class="c">{{ $report['wo_summary']['percent'] }}%</td>
    </tr>
</table>

<div class="har-h2">3. Rekapitulasi WO per Jenis @if(!empty($numbers['wo_by_type']))<small>({{ $numbers['wo_by_type'] }})</small>@endif</div>
@forelse($report['wo_by_type'] as $group)
    <p><strong>{{ $group['type'] }}</strong></p>
    <table class="har-data">
        <tr>
            <th>WONUM</th><th>Deskripsi</th><th>Mesin</th><th>Report</th>
            <th>Sched Start</th><th>Sched Finish</th><th>Status</th><th>Group</th>
        </tr>
        @foreach($group['rows'] as $r)
            <tr>
                <td>{{ $r['wonum'] }}</td>
                <td>{{ $r['description'] ?? '—' }}</td>
                <td>{{ $r['engine'] ?? '—' }}</td>
                <td class="c">{{ $r['report_date'] ?? '—' }}</td>
                <td class="c">{{ $r['sched_start'] ?? '—' }}</td>
                <td class="c">{{ $r['sched_finish'] ?? '—' }}</td>
                <td class="c">{{ $r['status'] ?? '—' }}</td>
                <td class="c">{{ $r['work_group'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
@empty
    <p class="har-note">Tidak ada Work Order.</p>
@endforelse

<div class="har-h2">4. WO Tertunda</div>
@forelse($report['wo_waiting'] as $group)
    <p><strong>{{ $group['reason'] }}</strong></p>
    <ul>
        @foreach($group['rows'] as $r)
            <li>{{ $r['wonum'] }} — {{ $r['description'] ?? '' }} ({{ $r['status'] ?? '—' }})</li>
        @endforeach
    </ul>
@empty
    <p class="har-note">Tidak ada WO tertunda.</p>
@endforelse

<div class="har-h2">5. Akumulasi Biaya Pemeliharaan @if(!empty($numbers['cost']))<small>({{ $numbers['cost'] }})</small>@endif</div>
<table class="har-data">
    <tr><th>Jasa (WO)</th><th>Material (WO)</th><th>Efektif ({{ $report['cost']['source'] }})</th><th>Akumulasi YTD</th></tr>
    <tr>
        <td class="r">{{ $rupiah($report['cost']['auto_service']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['auto_material']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['effective_total']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['ytd']) }}</td>
    </tr>
</table>

<div class="har-h2">6. Rencana vs Realisasi @if(!empty($numbers['schedules']))<small>({{ $numbers['schedules'] }})</small>@endif</div>
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

<div class="har-h2">7. Log Kegiatan HARMES @if(!empty($numbers['activities']))<small>({{ $numbers['activities'] }})</small>@endif</div>
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

<div class="har-h2">8. Lampiran Foto</div>
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
