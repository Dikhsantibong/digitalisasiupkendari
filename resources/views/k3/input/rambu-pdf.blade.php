{{-- PDF (A4 landscape) halaman Input K3 "rambu" — tabel dari App\Services\K3\K3InputTables::tables('rambu'). --}}
@extends('k3.input.layouts.export')

@section('content')
    @foreach($tables as $table)
        @include('k3.input.partials.export-table', ['table' => $table, 'first' => $loop->first])
    @endforeach
@endsection
