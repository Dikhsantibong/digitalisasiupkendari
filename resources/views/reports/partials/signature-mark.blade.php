@php
    /**
     * Renders a single signature cell's approved mark (QR barcode, uploaded image,
     * electronic stamp text, or blank space when pending) without nested tables.
     *
     * @var array{mode?: ?string, qr?: ?string, image?: ?string, verify_url?: ?string, signed_at?: ?string, position?: ?string} $signer
     */
    $signer = $signer ?? [];
    $mode = $signer['mode'] ?? config('reports.signature_mode', 'qr');
    $height = (int) ($height ?? 78);
    $qrSize = (int) ($qrSize ?? 54);
    $imgMaxHeight = max(44, $height - 6);
    $formattedDate = ! empty($signer['signed_at'])
        ? \Illuminate\Support\Carbon::parse($signer['signed_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i')
        : null;
    $position = $signer['position'] ?? '';
@endphp
@if($mode === 'text' && $formattedDate)
    <div style="height:{{ $height }}px; margin:4px 0; line-height:{{ $height }}px; text-align:center;">
        <span style="display:inline-block; line-height:1.3; vertical-align:middle; font-size:8pt; font-style:italic; color:#1f4e79;">Ditandatangani secara elektronik<br>{{ $formattedDate }}</span>
    </div>
@elseif($mode === 'text')
    <div style="height:{{ $height }}px; margin:4px 0;">&nbsp;</div>
@elseif($mode === 'image' && ! empty($signer['image']))
    <div style="height:{{ $height }}px; margin:4px 0; line-height:{{ $height }}px; text-align:center;">
        <img src="{{ $signer['image'] }}" alt="Tanda tangan {{ $position }}" style="max-height:{{ $imgMaxHeight }}px; max-width:150px; vertical-align:middle;">
    </div>
@elseif(! empty($signer['qr']))
    @php($qrPad = max(2, (int) floor(($height - $qrSize) / 2)))
    <div style="margin:4px 0; padding:{{ $qrPad }}px 0; line-height:1; text-align:center;">
        @if(! empty($signer['verify_url']))
            <a href="{{ $signer['verify_url'] }}" target="_blank" rel="noopener" style="display:inline-block; line-height:1; text-decoration:none; border:none;">
                <img src="{{ $signer['qr'] }}" alt="QR Verifikasi {{ $position }}" width="{{ $qrSize }}" height="{{ $qrSize }}" style="width:{{ $qrSize }}px; height:{{ $qrSize }}px; display:block; border:none;">
            </a>
        @else
            <img src="{{ $signer['qr'] }}" alt="QR Verifikasi {{ $position }}" width="{{ $qrSize }}" height="{{ $qrSize }}" style="width:{{ $qrSize }}px; height:{{ $qrSize }}px; display:inline-block; border:none;">
        @endif
    </div>
@elseif(! empty($signer['image']))
    <div style="height:{{ $height }}px; margin:4px 0; line-height:{{ $height }}px; text-align:center;">
        <img src="{{ $signer['image'] }}" alt="Tanda tangan {{ $position }}" style="max-height:{{ $imgMaxHeight }}px; max-width:150px; vertical-align:middle;">
    </div>
@elseif($formattedDate)
    <div style="height:{{ $height }}px; margin:4px 0; line-height:{{ $height }}px; text-align:center;">
        <span style="display:inline-block; line-height:1.3; vertical-align:middle; font-size:8pt; font-style:italic; color:#1f4e79;">Ditandatangani secara elektronik<br>{{ $formattedDate }}</span>
    </div>
@else
    <div style="height:{{ $height }}px; margin:4px 0;">&nbsp;</div>
@endif
