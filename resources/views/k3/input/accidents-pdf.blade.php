{{-- PDF (A4 landscape) halaman Input K3 "Laporan Kecelakaan (PAK/PAHK)" — tabel dari App\Services\K3\K3InputTables::tables('accidents'). --}}
@extends('k3.input.layouts.export')

@section('content')
    @foreach($tables as $table)
        @include('k3.input.partials.export-table', ['table' => $table, 'first' => $loop->first])

        @if($table['has_data'] && collect($table['rows'])->every(fn (array $row): bool => ($row['cells'][9] ?? '') === 'Nihil'))
            <div class="k3x-legend"><strong>NIHIL</strong> — tidak ada kecelakaan / penyakit akibat kerja pada periode ini.</div>
        @endif
    @endforeach
@endsection
