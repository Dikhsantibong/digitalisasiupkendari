{{-- Waiting Work Order table. Expects: $rows (list of waiting WO row arrays). --}}
@if(empty($rows))
    <p class="har-note">Tidak ada Work Order pada kategori ini.</p>
@else
    <table class="har-data">
        <tr>
            <th>No</th><th>WONUM</th><th>Deskripsi</th><th>Mesin</th><th>Report</th><th>Status</th><th>Group</th>
        </tr>
        @foreach($rows as $r)
            <tr>
                <td class="c">{{ $loop->iteration }}</td>
                <td>{{ $r['wonum'] }}</td>
                <td>{{ $r['description'] ?? '—' }}</td>
                <td>{{ $r['engine'] ?? '—' }}</td>
                <td class="c">{{ $r['report_date'] ?? '—' }}</td>
                <td class="c">{{ $r['status'] ?? '—' }}</td>
                <td class="c">{{ $r['work_group'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif
