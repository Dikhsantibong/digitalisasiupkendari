{{-- PDF (A4 landscape) halaman Input K3 "Inspeksi APAR/APAB" — tabel dari App\Services\K3\K3InputTables::tables('apar-checks'). --}}
@extends('k3.input.layouts.export')

@section('content')
    @foreach($tables as $table)
        @include('k3.input.partials.export-table', ['table' => $table, 'first' => $loop->first])

        <div class="k3x-legend">
            Status dihitung dari Exp Date: Aktif · Mendekati Expired (≤ {{ config('k3.expiry_warning_days', 60) }} hari) · Expired · Belum Ada Data.
        </div>
    @endforeach
@endsection
