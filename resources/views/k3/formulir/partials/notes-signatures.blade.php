@if($data['notes_label'] !== null)
    <div class="notes-box">
        <strong>{{ $data['notes_label'] }} :</strong>
        <div style="margin-top: 3px; line-height: 1.4;">
            @if(trim($data['catatan']) !== '')
                {!! nl2br(e($data['catatan'])) !!}
            @else
                ..........................................................................................................................................................................
            @endif
        </div>
    </div>
@endif

@php
    $signers = $data['signers'];
    $isPlain = count($signers) < 3;
    // Dua penandatangan: kiri & kanan dengan kolom tengah kosong, seperti lembar resmi.
    $slots = $isPlain ? [$signers[0] ?? null, null, $signers[1] ?? null] : $signers;
    $lastIndex = count($slots) - 1;
@endphp

<table class="sign-table {{ $isPlain ? 'plain' : '' }}">
    <tr>
        @foreach($slots as $index => $signer)
            <td>
                @if($signer !== null)
                    <div style="min-height: 11px;">{{ $index === $lastIndex && $data['sign_place_date'] !== '' ? $data['sign_place_date'] : '' }}</div>
                    <div>{{ $signer['label'] }}</div>
                    <div style="{{ $isPlain ? '' : 'font-weight: bold;' }} margin-top: 1px;">{{ $data[$signer['key'].'_title'] }}</div>
                    <div class="sign-space">
                        @if(!empty($data[$signer['key'].'_signature']))
                            <img src="{{ $data[$signer['key'].'_signature'] }}" alt="Tanda tangan" style="max-height: 50px; max-width: 140px;">
                        @endif
                    </div>
                    <div class="sign-name">{{ $data[$signer['key'].'_name'] }}</div>
                @endif
            </td>
        @endforeach
    </tr>
</table>
