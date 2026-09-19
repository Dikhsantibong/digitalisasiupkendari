{{-- PDF (A4 landscape) Input PdM "Laporan Inspeksi Checklist 5S5R" — definisi: App\Support\PdmForms\Checklist5s5rForm. --}}
@extends('pdm.input.layouts.form')

@section('content')
    @include('pdm.input.partials.form-fields', ['position' => 'header'])

    <div class="field-group">B. PENILAIAN 5S5R</div>
    @foreach($form->sections() as $section)
        @include('pdm.input.partials.form-section', ['section' => $section])
    @endforeach

    @if($summary = $document['summary'])
        <table class="grid th-orange" style="width: 55%; margin-top: 10px;">
            <thead>
                <tr>
                    @foreach($summary['columns'] as $label)
                        <th>{{ strtoupper($label) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($summary['rows'] as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td class="c">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

@endsection
