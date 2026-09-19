@php
    /**
     * A K3 formulir point filled from the jadwal rows that record that work
     * (see K3ReportBuilder::formulirPoints): one row per kegiatan with its
     * source, plan, realisation and realised dates.
     *
     * @var list<array{sumber: string, kegiatan: string, rencana: int, realisasi: int, kinerja: string, tanggal: string}> $rows
     * @var string|null $message
     * @var string|null $note  shown above the table, e.g. when it stands in for a missing input
     */
    $note ??= null;
@endphp
@if(empty($rows))
    @include('k3.laporan.partials.no-data', ['message' => $message ?? null])
@else
    @if($note)
        <div class="k3x-note">{{ $note }}</div>
    @endif
    <table class="k3x-table">
        <thead>
            <tr>
                <th style="width: 22px;">No</th>
                <th>Kegiatan / Uraian Pemeriksaan</th>
                <th style="width: 120px;">Sumber Data</th>
                <th style="width: 44px;">Rencana</th>
                <th style="width: 48px;">Realisasi</th>
                <th style="width: 42px;">Kinerja</th>
                <th style="width: 110px;">Tanggal Realisasi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $index => $row)
                <tr>
                    <td class="k3x-c">{{ $index + 1 }}</td>
                    <td>{{ $row['kegiatan'] }}</td>
                    <td class="k3x-c">{{ $row['sumber'] }}</td>
                    <td class="k3x-c">{{ $row['rencana'] }}</td>
                    <td class="k3x-c">{{ $row['realisasi'] }}</td>
                    <td class="k3x-c">{{ $row['kinerja'] }}</td>
                    <td class="k3x-c">{{ $row['tanggal'] !== '' ? $row['tanggal'] : '—' }}</td>
                </tr>
            @endforeach
            <tr class="k3x-total">
                <td></td>
                <td>TOTAL</td>
                <td></td>
                <td class="k3x-c">{{ collect($rows)->sum('rencana') }}</td>
                <td class="k3x-c">{{ collect($rows)->sum('realisasi') }}</td>
                <td class="k3x-c">{{ collect($rows)->sum('rencana') > 0 ? round(collect($rows)->sum('realisasi') / collect($rows)->sum('rencana') * 100).'%' : '-' }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endif
