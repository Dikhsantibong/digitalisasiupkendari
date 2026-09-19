{{-- Waiting Work Order table. Expects: $rows (list of waiting WO row arrays). --}}
<table class="har-data">
    <thead>
        <tr>
            <th>No</th><th>WONUM</th><th>Deskripsi</th><th>Mesin</th><th>Report</th><th>Status</th><th>Group</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            <tr>
                <td class="c">{{ $loop->iteration }}</td>
                <td>{{ $r['wonum'] }}</td>
                <td>{{ $r['description'] ?? '—' }}</td>
                <td>{{ $r['engine'] ?? '—' }}</td>
                <td class="c">{{ $r['report_date'] ?? '—' }}</td>
                <td class="c">{{ $r['status'] ?? '—' }}</td>
                <td class="c">{{ $r['work_group'] ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align:center; padding:10px; color:#666; font-style:italic;">
                    Tidak ada Work Order pada kategori ini.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
