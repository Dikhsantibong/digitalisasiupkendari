@php
    /**
     * Shared A4-landscape shell for the K3 input PDF exports
     * (resources/views/k3/input/*-pdf.blade.php). Each export fills `content`.
     *
     * @var string $unitHeaderName
     */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
@include('k3.laporan.styles')
@page { size: A4 landscape; margin: 14mm 12mm 18mm 12mm; }
.k3x-heading { font-weight: bold; font-size: 10pt; color: #0b2545; margin: 4px 0 2px 0; }
.k3x-subheading { font-size: 8.5px; color: #475569; margin-bottom: 6px; }
.k3x-note { font-size: 8px; color: #92400e; font-style: italic; margin: 0 0 4px 0; }
.k3x-legend { font-size: 8px; color: #475569; margin-top: 4px; }
.k3x-photo-grid { width: 100%; border-collapse: collapse; }
.k3x-photo-grid td { width: 50%; padding: 6px; text-align: center; vertical-align: top; border: 1px solid #cbd5e1; }
.k3x-photo-grid .k3x-photo { max-width: 340px; max-height: 220px; }
.k3x-photo-caption { font-size: 8.5px; font-weight: bold; margin-top: 4px; }
    </style>
</head>
<body>
@yield('content')
</body>
</html>
