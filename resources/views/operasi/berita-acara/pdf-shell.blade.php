<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
@include('operasi.berita-acara.partials.ba-styles')
@page {
    margin: {{ $margin_top ?? 15 }}mm {{ $margin_right ?? 15 }}mm {{ $margin_bottom ?? 15 }}mm {{ $margin_left ?? 15 }}mm;
}
body {
    line-height: {{ $line_spacing ?? '1.15' }};
}
    </style>
</head>
<body>
{!! $content !!}
</body>
</html>
