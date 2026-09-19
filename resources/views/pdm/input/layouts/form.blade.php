@php
    /**
     * Shell of the generic PdM form PDFs (resources/views/pdm/input/{form}-pdf.blade.php):
     * page size from the form, kop, period/machine line, then `content`.
     *
     * @var \App\Support\PdmForms\PdmForm $form
     * @var \App\Models\Unit $unit
     * @var \App\Models\Machine|null $machine
     * @var string $periodLabel
     */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $form->title() }} - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('pdm.input.partials.styles')
    <style>
        @page { size: A4 {{ $form->orientation() }}; margin: 10mm 10mm 16mm 10mm; }
        .form-title { font-weight: bold; font-size: 8.5px; margin: 8px 0 2px 0; }
        .form-note { font-size: 7.5px; margin-bottom: 2px; }
        .field-grid { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .field-grid td { border: 1px solid #000; padding: 2px 5px; font-size: 7.5px; vertical-align: top; }
        .field-grid .label { font-weight: bold; background: #f2f2f2; width: 95px; }
        .field-group { font-weight: bold; font-size: 8px; margin: 6px 0 2px 0; }
        .box-title { font-weight: bold; text-align: center; padding: 2px; border: 1px solid #000; background: #d9e1f2; font-size: 8px; }
        .box-body { border: 1px solid #000; border-top: none; padding: 4px; min-height: 28px; font-size: 7.5px; white-space: pre-line; margin-bottom: 6px; }
        .photos img { max-width: 170px; max-height: 120px; margin: 2px; }
        tr.group-head th { font-size: 7px; }
    </style>
</head>
<body>
    @include('pdm.input.partials.kop', ['theme' => $form->theme(), 'lines' => $form->kopLines($unit->name)])
    <div class="muted" style="margin-bottom: 4px;">
        Periode: {{ $periodLabel }}@if($machine) &nbsp;·&nbsp; Mesin: {{ $machine->name }}@endif
    </div>
    @yield('content')
</body>
</html>
