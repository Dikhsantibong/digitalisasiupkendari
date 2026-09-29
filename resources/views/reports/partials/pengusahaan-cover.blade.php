{{--
    Sampul Laporan Pengusahaan Pembangkit — shared by HAR, K3 & Operasi.
    Params: $title (e.g. "LAPORAN KINERJA K3 & KAM"), $unitName, $monthName, $year,
    optional $id (anchor of the cover) and $subtitle (line under the title).
    The photo is public/background/bg-login.jpeg (inlined for dompdf by EmbedsReportLogo).
--}}
<div class="pc-cover" @isset($id) id="{{ $id }}" @endisset>
    <table class="pc-header">
        <tr>
            <td class="pc-logo-left"><img src="/logo/sidebar-logo.png" alt="Logo PLN"></td>
            <td class="pc-head-text">
                <div class="pc-company">PT. PLN NUSANTARA POWER</div>
                <div class="pc-parent">UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI</div>
                <div class="pc-unit">UNIT LAYANAN PUSAT LISTRIK TENAGA DIESEL {{ strtoupper(preg_replace('/^PLTD\s+/i', '', (string) ($unitName ?? ''))) }}</div>
            </td>
            <td class="pc-logo-right"><img src="/logo/k3.png" alt="Logo K3"></td>
        </tr>
    </table>

    <div class="pc-title-box">
        <h1 class="pc-title">{{ $title }}</h1>
        @isset($subtitle)
            <div class="pc-subtitle">{{ $subtitle }}</div>
        @endisset
    </div>

    <div class="pc-photo-frame">
        <img src="/background/bg-login.jpeg" alt="Foto Unit Pembangkit" class="pc-photo">
    </div>

    <div class="pc-period-box">
        <div class="pc-period">PERIODE {{ strtoupper((string) ($monthName ?? '')) }} {{ $year ?? '' }}</div>
    </div>
</div>
