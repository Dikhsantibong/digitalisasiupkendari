<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['title'] }} - {{ $data['unit_name'] }}</title>
    <style>
@include('k3.formulir.partials.styles')
    </style>
</head>
<body>
    @if(!empty($contentHtml))
        {!! $contentHtml !!}
    @else
        @include($data['body_view'])
    @endif
</body>
</html>
