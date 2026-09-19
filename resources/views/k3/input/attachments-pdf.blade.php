{{-- PDF (A4 landscape) halaman Input K3 "Lampiran" — foto dari App\Services\K3\K3InputTables::attachmentsWithImages(). --}}
@extends('k3.input.layouts.export')

@section('content')
    @foreach($tables as $table)
        @if(! $loop->first)
            <div class="page-break"></div>
        @endif

        @include('k3.laporan.partials.kop', ['title' => $table['title'], 'unitHeaderName' => $unitHeaderName])

        <div class="k3x-heading">{{ $table['title'] }}</div>
        <div class="k3x-subheading">Unit: {{ $table['unit'] }} &nbsp;·&nbsp; Periode: {{ $table['period'] }}</div>

        @if(! $table['has_data'])
            @include('k3.laporan.partials.no-data', ['message' => 'Belum ada lampiran yang diunggah untuk periode ini.'])
        @else
            <table class="k3x-photo-grid">
                @foreach(array_chunk($table['rows'], 2) as $pair)
                    <tr>
                        @foreach($pair as $row)
                            <td>
                                @if(is_array($row['cells'][3] ?? null))
                                    <img src="{{ $row['cells'][3]['image'] }}" alt="{{ $row['cells'][1] }}" class="k3x-photo">
                                @else
                                    <div class="k3x-note">(Dokumen non-gambar)</div>
                                @endif
                                <div class="k3x-photo-caption">{{ $row['cells'][0] }}. {{ $row['cells'][1] }}</div>
                                <div class="k3x-subheading">{{ $row['cells'][2] }}</div>
                            </td>
                        @endforeach
                        @if(count($pair) < 2)
                            <td></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach
@endsection
