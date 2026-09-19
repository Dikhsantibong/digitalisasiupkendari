{{-- Full Work Order detail table (landscape). Expects: $rows (WO row arrays). --}}
@php
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
@endphp
<table class="har-data har-wo">
    <thead>
        <tr>
            <th>No</th><th>WONUM</th><th>Deskripsi</th><th>Jenis</th><th>Mesin</th><th>Work Group</th>
            <th>Status</th><th>Siklus</th><th>Report Date</th><th>Sched Start</th><th>Sched Finish</th>
            <th>Waiting</th><th>Biaya Jasa</th><th>Biaya Material</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            <tr>
                <td class="c">{{ $loop->iteration }}</td>
                <td>{{ $r['wonum'] }}</td>
                <td>{{ $r['description'] ?? '—' }}</td>
                <td class="c">{{ $r['type'] ?? '—' }}</td>
                <td>{{ $r['engine'] ?? '—' }}</td>
                <td class="c">{{ $r['work_group'] ?? '—' }}</td>
                <td class="c">{{ $r['status'] ?? '—' }}</td>
                <td class="c">{{ $r['cycle'] ?? '—' }}</td>
                <td class="c">{{ $r['report_date'] ?? '—' }}</td>
                <td class="c">{{ $r['sched_start'] ?? '—' }}</td>
                <td class="c">{{ $r['sched_finish'] ?? '—' }}</td>
                <td class="c">{{ $r['waiting'] ?? '—' }}</td>
                <td class="r">{{ $rp($r['service_cost'] ?? 0) }}</td>
                <td class="r">{{ $rp($r['material_cost'] ?? 0) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="14" style="text-align:center; padding:10px; color:#666; font-style:italic;">
                    Tidak ada Work Order pada kategori ini.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
