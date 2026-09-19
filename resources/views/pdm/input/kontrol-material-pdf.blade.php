{{-- PDF (A4 landscape) Input PdM "Form Kontrol Material, Peralatan dan Tools" — definisi: App\Support\PdmForms\KontrolMaterialForm. --}}
@extends('pdm.input.layouts.form')

@section('content')
    @include('pdm.input.partials.form-fields', ['position' => 'header'])

    @foreach($form->sections() as $section)
        @include('pdm.input.partials.form-section', ['section' => $section])
    @endforeach

    @include('pdm.input.partials.form-boxes')
@endsection
