{{--
    Letterhead of every Pengusahaan Operasi PDF: the PLN Nusantara Power logo
    (public/logo/sidebar-logo.png, inlined because dompdf cannot fetch URLs)
    and the three-line unit name, as on the field Excel sheets.
    Params: $unit (App\Models\Unit).
--}}
@php
    $kopLogoPath = public_path('logo/sidebar-logo.png');
    $kopLogo = is_file($kopLogoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($kopLogoPath)) : null;
@endphp
<table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
    <tr>
        <td style="width: 1%; padding: 0 8px 0 0; vertical-align: middle; border: 0;">
            @if ($kopLogo)
                <img src="{{ $kopLogo }}" alt="PLN Nusantara Power" style="height: 34px;">
            @endif
        </td>
        <td style="vertical-align: middle; border: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; font-size: 8px; font-weight: bold; line-height: 1.35; color: #0b4f7a;">
            NUSANTARA POWER<br>
            UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI<br>
            UNIT LAYANAN PUSAT LISTRIK {{ strtoupper($unit->name) }}
        </td>
    </tr>
</table>
