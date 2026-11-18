@php
    /**
     * Signature block of a Laporan UL (id ul-ttd, refreshed inside a saved
     * document): Dibuat Oleh (Admin UL) then every approver — TL Menyetujui,
     * Manager UL Mengesahkan — with an electronic mark once signed.
     *
     * @var list<array{caption: string, position: string, name: string, signed_at: string|null}> $signers
     */
@endphp
<table id="ul-ttd" class="ul-ttd">
    <tr>
        @foreach ($signers as $signer)
            <td style="width: {{ round(100 / max(1, count($signers)), 2) }}%;">
                <div class="ul-ttd-caption">{{ $signer['caption'] }},</div>
                <div class="ul-ttd-position">{{ $signer['position'] }}</div>
                <div class="ul-ttd-mark">
                    @if ($signer['signed_at'])
                        <span>Ditandatangani secara elektronik<br>{{ $signer['signed_at'] }}</span>
                    @else
                        &nbsp;
                    @endif
                </div>
                <div class="ul-ttd-name">{{ $signer['name'] !== '' ? $signer['name'] : '....................................' }}</div>
            </td>
        @endforeach
    </tr>
</table>
