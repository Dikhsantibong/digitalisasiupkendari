{{-- PDF (A4 landscape) halaman Input K3 "emergency-facility" — tabel dari App\Services\K3\K3InputTables::tables('emergency-facility'). --}}
@extends('k3.input.layouts.export')

@section('content')
    @foreach($tables as $table)
        @include('k3.input.partials.export-table', ['table' => $table, 'first' => $loop->first])
    @endforeach
@endsection
