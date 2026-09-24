@include('k3.formulir.partials.kop')

@include('k3.formulir.partials.record-tables', [
    'form' => $data['form'],
    'values' => $data['sections'],
    'header' => $data['header'],
    'unitName' => $data['unit_name'],
])

@include('k3.formulir.partials.notes-signatures')
