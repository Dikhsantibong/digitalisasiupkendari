{{-- PDF (A4 portrait) Input PdM "Patrol Check Predictive Maintenance (PdM)" — definisi: App\Support\PdmForms\PatrolCheckPdmForm. --}}
@extends('pdm.input.layouts.form')

@section('content')
    @php
        $header = $document['header'];
        $identitas = [
            'Unit' => $header['unit'] ?? '',
            'Hari/Tanggal' => \App\Support\PdmForms\PatrolCheckPdmForm::dayLabel($header['tanggal'] ?? null),
            'Waktu' => $header['waktu'] ?? '',
            'Tim Patrol' => $header['tim_patrol'] ?? '',
            'Pelaksana' => $header['pelaksana'] ?? '',
        ];
    @endphp

    <div class="field-group">A. Identitas Patrol</div>
    <table class="grid th-{{ $form->headerColor() }}" style="width: 55%;">
        <thead>
            <tr>
                <th style="width: 38%;">Keterangan</th>
                <th>Isi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($identitas as $label => $value)
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach($form->sections() as $section)
        @include('pdm.input.partials.form-section', ['section' => $section])
    @endforeach

    @if($summary = $document['summary'])
        <div class="form-title">{{ $summary['title'] }}</div>
        <table class="grid th-{{ $form->headerColor() }}" style="width: 70%;">
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

    @include('pdm.input.partials.form-boxes')
@endsection
