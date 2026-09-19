@php
    /**
     * A signature block of a Laporan Pembangkit (Lembar Pengesahan or the
     * in-report block), built by ReportWorkflowService::signatureBlocks().
     * One flat table with a fixed id — no nested tables — so the block can be
     * found and refreshed inside a saved, edited document. The signature image
     * (or the electronic-signature mark) appears only once the report is FINAL.
     *
     * @var string $id  ttd-pengesahan | ttd-laporan
     * @var list<array{caption: string, position: string, name: string|null, image: string|null, signed_at: string|null}> $signers
     */
    $width = count($signers) > 0 ? round(100 / count($signers), 2) : 100;
@endphp
<table id="{{ $id }}" class="rpt-ttd" style="width:100%; border-collapse:collapse; margin-top:18px; font-family:'DejaVu Sans', Arial, sans-serif; font-size:10.5pt; color:#000; page-break-inside:avoid;">
    <tr>
        @foreach($signers as $signer)
            <td style="width:{{ $width }}%; text-align:center; vertical-align:top; padding:0 6px; border:none;">
                <div style="font-weight:bold; text-transform:uppercase;">{{ $signer['caption'] }}</div>
                <div style="font-weight:bold; margin-top:2px;">{{ $signer['position'] }}</div>
                <div style="height:78px; margin:4px 0; line-height:78px;">
                    @if(!empty($signer['image']))
                        <img src="{{ $signer['image'] }}" alt="Tanda tangan {{ $signer['position'] }}" style="max-height:72px; max-width:150px; vertical-align:middle;">
                    @elseif(!empty($signer['signed_at']))
                        <span style="display:inline-block; line-height:1.3; vertical-align:middle; font-size:8pt; font-style:italic; color:#1f4e79;">Ditandatangani secara elektronik<br>{{ \Illuminate\Support\Carbon::parse($signer['signed_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                    @endif
                </div>
                <div style="font-weight:bold; text-decoration:underline;">{{ $signer['name'] ? strtoupper($signer['name']) : '(...................................)' }}</div>
            </td>
        @endforeach
    </tr>
</table>
