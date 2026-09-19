{{-- PDF (A4 landscape) halaman Input K3 "Time Frame" — tabel dari App\Services\K3\K3InputTables::tables('time-frame'). --}}
@extends('k3.input.layouts.export')

@section('content')
    @foreach($tables as $table)
        @include('k3.input.partials.export-table', ['table' => $table, 'first' => $loop->first])

        @php
            $plannedRows = array_values(array_filter($table['rows'], fn (array $row): bool => ($row['cells'][3] ?? '') === 'R'));
            $realRows = array_values(array_filter($table['rows'], fn (array $row): bool => ($row['cells'][3] ?? '') === 'Rl'));
            $sumColumn = fn (array $rows): int => array_sum(array_map(fn (array $row): int => (int) ($row['cells'][count($row['cells']) - 3] ?? 0), $rows));
            $totalPlan = $sumColumn($plannedRows);
            $totalReal = $sumColumn($realRows);
        @endphp
        <div class="k3x-legend">
            R = Rencana &nbsp;·&nbsp; Rl = Realisasi &nbsp;·&nbsp;
            Total rencana: <strong>{{ $totalPlan }}</strong> &nbsp;·&nbsp; Total realisasi: <strong>{{ $totalReal }}</strong>
            &nbsp;·&nbsp; Capaian: <strong>{{ $totalPlan > 0 ? round($totalReal / $totalPlan * 100).'%' : '-' }}</strong>
        </div>
    @endforeach
@endsection
