@php
    /**
     * Isi Formulir K3 yang disimpan di halaman k3/formulir/* (lihat
     * K3ReportBuilder::formulirSheets), satu blok per periode tersimpan
     * (Kontrol K3 Mingguan: per minggu).
     *
     * @var array{form: array<string, mixed>, entries: list<array{period_label: string, sections: array<string, list<array<string, string>>>, header: array<string, string>, catatan: string}>} $sheet
     * @var string $unitName
     */
    $reportClasses = ['table' => 'k3x-table', 'dark' => '', 'bar' => 'k3x-caption', 'section_row' => 'k3x-total', 'header' => 'k3x-table', 'center' => 'k3x-c'];
    $showPeriod = count($sheet['entries']) > 1 || $sheet['form']['period'] === 'weekly';
@endphp
<div class="k3x-note">Sumber data: {{ $sheet['form']['title'] }} (menu Formulir K3 &amp; Keamanan).</div>
@foreach($sheet['entries'] as $entry)
    @if($showPeriod)
        <div class="k3x-caption">{{ $entry['period_label'] }}</div>
    @endif
    @include('k3.formulir.partials.record-tables', [
        'form' => $sheet['form'],
        'values' => $entry['sections'],
        'header' => $entry['header'],
        'unitName' => $unitName,
        'classes' => $reportClasses,
    ])
    @if($sheet['form']['notes_label'] !== null && trim($entry['catatan']) !== '')
        <div class="k3x-caption">{{ $sheet['form']['notes_label'] }}</div>
        <div>{!! nl2br(e($entry['catatan'])) !!}</div>
    @endif
@endforeach
