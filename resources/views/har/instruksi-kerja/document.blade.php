{{--
    One Instruksi Kerja (IK) Pemeliharaan (App\Models\HarInstruksiKerja), laid out
    like the MKP form: logo, kop, "INSTRUKSI KERJA (IK)" + judul, the parts and the
    Dibuat / Disetujui box. Mirrors the preview in
    resources/js/pages/har/input/instruksi-kerja/index.tsx. Param: $doc (array).
--}}
@php
    $bold = fn (string $text): string => preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($text));
    $marker = function (string $style, int $n): string {
        return match ($style) {
            'angka' => $n.'.',
            'huruf' => chr(96 + (($n - 1) % 26) + 1).'.',
            'butir' => '&bull;',
            'panah' => '&#10146;',
            'strip' => '&ndash;',
            default => '',
        };
    };
    $heading = 0;
    $counter = 0;
    $meta = array_filter([
        'No. Dokumen' => $doc['no_dokumen'] ?? '',
        'Revisi' => $doc['revisi'] ?? '',
        'Tanggal' => ! empty($doc['tanggal']) ? \Illuminate\Support\Carbon::parse($doc['tanggal'])->format('d-m-Y') : '',
    ]);
@endphp
<div style="font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #000; line-height: 1.5;">
    <div style="text-align: center;">
        <img src="/logo/mkp.jpg" alt="MKP Mitra Karya Prima" style="max-height: 62px; max-width: 190px;">
        <div style="font-size: 15px; font-weight: bold; letter-spacing: 0.3px; margin-top: 10px;">{{ $doc['kop'] ?? '' }}</div>
    </div>
    <div style="border-bottom: 1px solid #555; margin: 8px 0 12px 0;"></div>

    <div style="text-align: center; font-size: 14px; font-weight: bold; line-height: 1.45;">
        <div>INSTRUKSI KERJA (IK)</div>
        @foreach(preg_split('/\r\n|\n/', (string) ($doc['judul'] ?? '')) as $line)
            @if(trim($line) !== '')
                <div>{{ strtoupper(trim($line)) }}</div>
            @endif
        @endforeach
    </div>
    @if($meta !== [])
        <div style="text-align: center; font-size: 9px; color: #333; margin-top: 4px;">
            {{ collect($meta)->map(fn ($v, $k) => "{$k}: {$v}")->implode('   ·   ') }}
        </div>
    @endif

    <div style="margin-top: 10px;">
        @foreach($doc['sections'] ?? [] as $section)
            @php
                $style = $section['gaya'] ?? 'angka';
                $numbered = in_array($style, ['angka', 'huruf'], true);
                if (empty($section['lanjut'])) {
                    $counter = 0;
                }
                $points = array_values(array_filter($section['butir'] ?? [], fn ($p): bool => trim((string) ($p['teks'] ?? '')) !== ''));
            @endphp
            <div style="margin-top: 7px; page-break-inside: avoid;">
                @if(! empty($section['bernomor']))
                    @php $heading++; @endphp
                    <table style="border-collapse: collapse; width: 100%;"><tr>
                        <td style="width: 26px; font-weight: bold; vertical-align: top; padding: 0;">{{ $heading }}.</td>
                        <td style="font-weight: bold; padding: 0;">{{ strtoupper($section['judul'] ?? '') }}</td>
                    </tr></table>
                @else
                    <div style="font-weight: bold; margin-top: 8px;">{{ strtoupper($section['judul'] ?? '') }}</div>
                @endif
            </div>

            @if(trim((string) ($section['pengantar'] ?? '')) !== '')
                <div style="margin: 3px 0 2px 26px;">{!! $bold($section['pengantar']) !!}</div>
            @endif

            @foreach($points as $point)
                @php $counter += $numbered ? 1 : 0; @endphp
                @if($style === 'paragraf')
                    <div style="margin: 2px 0 0 26px;">{!! $bold($point['teks']) !!}</div>
                @else
                    <table style="border-collapse: collapse; margin: 2px 0 0 {{ ! empty($section['bernomor']) ? 30 : 44 }}px; width: 92%;">
                        <tr>
                            <td style="width: 24px; vertical-align: top; padding: 0; text-align: {{ $numbered ? 'right' : 'left' }}; padding-right: 6px;">{!! $marker($style, $counter) !!}</td>
                            <td style="vertical-align: top; padding: 0;">{!! $bold($point['teks']) !!}</td>
                        </tr>
                    </table>
                @endif
                @foreach($point['sub'] ?? [] as $sub)
                    @if(trim((string) $sub) !== '')
                        <table style="border-collapse: collapse; margin: 1px 0 0 {{ ! empty($section['bernomor']) ? 62 : 76 }}px; width: 86%;">
                            <tr>
                                <td style="width: 14px; vertical-align: top; padding: 0;">-</td>
                                <td style="vertical-align: top; padding: 0;">{!! $bold($sub) !!}</td>
                            </tr>
                        </table>
                    @endif
                @endforeach
            @endforeach
        @endforeach
    </div>

    @if(($doc['dibuat_jabatan'] ?? '') !== '' || ($doc['disetujui_jabatan'] ?? '') !== '')
        <table style="width: 100%; border-collapse: collapse; margin-top: 26px; font-size: 10px; page-break-inside: avoid;">
            <tr>
                <td style="width: 34%; border: 1px solid #000; text-align: center; padding: 2px;">Dibuat</td>
                <td style="border: 1px solid #000;"></td>
                <td style="width: 34%; border: 1px solid #000; text-align: center; padding: 2px;">Disetujui</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; border-bottom: none; text-align: center; padding: 2px;">{{ $doc['dibuat_jabatan'] ?? '' }}</td>
                <td style="border-left: 1px solid #000; border-right: 1px solid #000;"></td>
                <td style="border: 1px solid #000; border-bottom: none; text-align: center; padding: 2px;">{{ $doc['disetujui_jabatan'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="border-left: 1px solid #000; border-right: 1px solid #000; height: 46px;"></td>
                <td style="border-left: 1px solid #000; border-right: 1px solid #000;"></td>
                <td style="border-left: 1px solid #000; border-right: 1px solid #000;"></td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; border-top: none; text-align: center; padding: 2px;">{{ strtoupper($doc['dibuat_nama'] ?? '') }}</td>
                <td style="border: 1px solid #000; border-top: none;"></td>
                <td style="border: 1px solid #000; border-top: none; text-align: center; padding: 2px;">{{ strtoupper($doc['disetujui_nama'] ?? '') }}</td>
            </tr>
        </table>
    @endif
</div>
