{{--
    One Dokumen IK K3 Lingkungan Pembangkit (App\Models\K3DokumenIk) laid out like
    the official form: project kop, identity row (SMK3 level · judul · No. Dokumen,
    Tanggal, Revisi, Halaman) and the numbered parts. Used by the IK PDF and the
    Laporan K3 (lampiran). Params: $doc (array), $unitName.
    Styles are inline so the block renders the same in every report shell.
--}}
@php
    $b = 'border: 1px solid #000;';
    $sections = $doc['sections'] ?? [];
    $marker = function (string $style, int $index): string {
        return match ($style) {
            'huruf' => chr(97 + ($index % 26)).'.',
            'angka' => ($index + 1).'.',
            'paragraf' => '',
            default => '&bull;',
        };
    };
    $tanggal = ! empty($doc['tanggal']) ? \Illuminate\Support\Carbon::parse($doc['tanggal'])->format('d-m-Y') : '';
@endphp
<div style="{{ $b }} padding: 0; font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #000; page-break-inside: auto;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 20%; padding: 6px 8px; vertical-align: middle; border-bottom: 1px solid #000;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="max-height: 36px; max-width: 130px;">
            </td>
            <td style="padding: 6px 4px; text-align: center; vertical-align: middle; border-bottom: 1px solid #000; line-height: 1.45;">
                <div>JASA PENDUKUNG TEKNIS UP KENDARI 11 &amp; 6 SITE -KIT</div>
                <div>{{ strtoupper($unitName) }}</div>
                <div>LAPORAN PROJECT</div>
                <div>DOKUMEN IK K3 LINGKUNGAN PEMBANGKIT</div>
            </td>
            <td style="width: 20%; padding: 6px 8px; text-align: right; vertical-align: middle; border-bottom: 1px solid #000;">
                <img src="/logo/mkp.jpg" alt="MKP" style="max-height: 36px; max-width: 110px;">
            </td>
        </tr>
    </table>
    <div style="height: 12px; border-bottom: 1px solid #000;"></div>

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 13%; {{ $b }} border-left: none; border-top: none; text-align: center; vertical-align: middle; padding: 6px 4px;">
                {!! nl2br(e(str_replace(' LEVEL', "\nLEVEL", $doc['sistem'] ?? 'SMK3 LEVEL 3'))) !!}
            </td>
            <td style="{{ $b }} border-top: none; text-align: center; vertical-align: middle; padding: 6px 8px; font-size: 10.5px;">
                {{ strtoupper($doc['judul'] ?? '') }}
            </td>
            <td style="width: 34%; {{ $b }} border-right: none; border-top: none; padding: 0; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse;">
                    @foreach(['No. Dokumen' => $doc['no_dokumen'] ?? '', 'Tanggal' => $tanggal, 'Revisi' => $doc['revisi'] ?? '', 'Halaman' => $doc['halaman'] ?? ''] as $label => $value)
                        <tr>
                            <td style="width: 34%; padding: 2px 5px; {{ $loop->last ? '' : 'border-bottom: 1px solid #000;' }} border-right: 1px solid #000;">{{ $label }}</td>
                            <td style="padding: 2px 5px; {{ $loop->last ? '' : 'border-bottom: 1px solid #000;' }}">: {{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <div style="padding: 10px 14px 16px 14px; line-height: 1.55;">
        @forelse($sections as $sIndex => $section)
            @php
                $style = $section['gaya'] ?? 'butir';
                $points = array_values(array_filter($section['butir'] ?? [], fn ($p): bool => trim((string) $p) !== ''));
            @endphp
            <div style="margin-top: {{ $sIndex === 0 ? '2px' : '12px' }}; page-break-inside: avoid;">
                <div>{{ $sIndex + 1 }}. {{ strtoupper($section['judul'] ?? '') }}</div>
                @if(trim((string) ($section['pengantar'] ?? '')) !== '')
                    <div style="margin: 6px 0 4px 14px;">{{ $section['pengantar'] }}</div>
                @endif
                @foreach($points as $pIndex => $point)
                    @if($style === 'paragraf')
                        <div style="margin: 4px 0 0 14px;">{{ $point }}</div>
                    @else
                        <table style="border-collapse: collapse; margin: 3px 0 0 14px; width: 97%;">
                            <tr>
                                <td style="width: 16px; vertical-align: top; padding: 0;">{!! $marker($style, $pIndex) !!}</td>
                                <td style="vertical-align: top; padding: 0;">{{ $point }}</td>
                            </tr>
                        </table>
                    @endif
                @endforeach
            </div>
        @empty
            <div style="color: #64748b;">Isi instruksi kerja belum diisi.</div>
        @endforelse
    </div>
</div>
