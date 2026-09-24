{{-- PDF (A4 portrait) Input PdM "Dokumen/Laporan Checklist Patrol Check PdM" — definisi: App\Support\PdmForms\ChecklistPatrolCheckPdmForm. --}}
@extends('pdm.input.layouts.form')

@section('content')
    @include('pdm.input.partials.form-fields', ['position' => 'header'])

    @if($summary = $document['summary'])
        <div class="form-title">{{ strtoupper($summary['title']) }}</div>
        <table class="grid th-{{ $form->headerColor() }}" style="margin-bottom: 4px;">
            <thead>
                <tr>
                    @foreach($summary['columns'] as $label)
                        <th>{{ $label }}</th>
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

    @foreach($form->sections() as $section)
        @include('pdm.input.partials.form-section', ['section' => $section])
    @endforeach

    @include('pdm.input.partials.form-boxes')
@endsection
