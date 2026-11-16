@php
    /**
     * A signature block of a Laporan Pembangkit (Lembar Pengesahan or the
     * in-report block), built by ReportWorkflowService::signatureBlocks().
     * One flat table with a fixed id — no nested tables — so the block can be
     * found and refreshed inside a saved, edited document cleanly.
     *
     * @var string $id  ttd-pengesahan | ttd-laporan
     * @var list<array{caption: string, position: string, name: ?string, mode?: ?string, qr?: ?string, image?: ?string, verify_url?: ?string, signed_at?: ?string}> $signers
     */
    $width = count($signers) > 0 ? round(100 / count($signers), 2) : 100;
@endphp
<table id="{{ $id }}" class="rpt-ttd" style="width:100%; border-collapse:collapse; margin-top:18px; font-family:'DejaVu Sans', Arial, sans-serif; font-size:10.5pt; color:#000; page-break-inside:avoid;">
    <tr>
        @foreach($signers as $signer)
            <td style="width:{{ $width }}%; text-align:center; vertical-align:top; padding:0 6px; border:none;">
                <div style="font-weight:bold; text-transform:uppercase;">{{ $signer['caption'] }}</div>
                <div style="font-weight:bold; margin-top:2px;">{{ $signer['position'] }}</div>
                @include('reports.partials.signature-mark', ['signer' => $signer])
                <div style="font-weight:bold; text-decoration:underline;">{{ $signer['name'] ? strtoupper($signer['name']) : '(...................................)' }}</div>
            </td>
        @endforeach
    </tr>
</table>
