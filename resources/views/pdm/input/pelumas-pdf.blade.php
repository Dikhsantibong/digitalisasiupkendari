{{-- PDF (A4 portrait) Input PdM "Pengukuran Kualitas Pelumas" — definisi: App\Support\PdmForms\PelumasForm. --}}
@extends('pdm.input.layouts.form')

@section('content')
    @include('pdm.input.partials.form-fields', ['position' => 'header'])

    @foreach($form->sections() as $section)
        @include('pdm.input.partials.form-section', ['section' => $section])
    @endforeach

    @include('pdm.input.partials.form-boxes')
@endsection
