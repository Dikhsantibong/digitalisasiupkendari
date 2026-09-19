{{-- PDF (A4 portrait) Input PdM "Laporan Pengukuran Vibrasi Mesin & Generator" — definisi: App\Support\PdmForms\VibrasiForm. --}}
@extends('pdm.input.layouts.form')

@section('content')
    @include('pdm.input.partials.form-fields', ['position' => 'header'])

    {{-- Titik pengukuran: generator A1-C2, mesin D1-F2, coupling G1-G3 --}}
    <table style="width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 7.5px; text-align: center;">
        <tr>
            <td style="width: 8%;"></td>
            <td style="width: 26%;">A2 &nbsp;&nbsp;&nbsp; B2 &nbsp;&nbsp;&nbsp; C2</td>
            <td style="width: 8%;"></td>
            <td style="width: 50%;">D2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; E2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; F2</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td style="border: 1px solid #000; padding: 8px; font-weight: bold;">GENERATOR</td>
            <td style="font-weight: bold;">G ◄</td>
            <td style="border: 1px solid #000; padding: 8px; font-weight: bold;">MESIN</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td>A1 &nbsp;&nbsp;&nbsp; B1 &nbsp;&nbsp;&nbsp; C1</td>
            <td>G1 · G2 · G3</td>
            <td>D1 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; E1 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; F1</td>
            <td></td>
        </tr>
    </table>

    @foreach($form->sections() as $section)
        @include('pdm.input.partials.form-section', ['section' => $section])
    @endforeach

    @include('pdm.input.partials.form-fields', ['position' => 'footer'])
    @include('pdm.input.partials.form-boxes')
@endsection
