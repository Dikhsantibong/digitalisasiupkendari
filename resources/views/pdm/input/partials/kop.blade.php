@php
    /**
     * PdM input letterhead: PLN logo · centred lines · MKP logo.
     *
     * @var list<string> $lines
     * @var string $theme  cyan|navy|plain
     * @var string|null $logoLeft
     * @var string|null $logoRight
     */
@endphp
<table class="kop kop-{{ $theme }}">
    <tr>
        <td class="logo">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
        <td>
            <table class="lines" style="width: 100%; border-collapse: collapse;">
                @foreach($lines as $line)
                    <tr><td>{{ $line }}</td></tr>
                @endforeach
            </table>
        </td>
        <td class="logo logo-right">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
    </tr>
</table>
