<table id="ttd-pengusahaan" class="sign-table">
    <tr>
        @foreach ([['Dibuat Oleh:', 'staf'], ['Diperiksa Oleh:', 'tl'], ['Disetujui Oleh:', 'manager']] as [$role, $key])
            @php($signer = $signatories[$key] ?? [])
            <td>
                <div class="sign-role">{{ $role }}</div>
                <div class="sign-position">{{ $signer['position'] ?? '' }}</div>
                @include('reports.partials.signature-mark', ['signer' => $signer, 'height' => 64, 'qrSize' => 50])
                <div class="sign-name">{{ ($signer['name'] ?? '') ?: '(...................................)' }}</div>
            </td>
        @endforeach
    </tr>
</table>
