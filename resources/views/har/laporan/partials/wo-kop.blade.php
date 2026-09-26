{{-- Kop lembar WO Laporan Pengusahaan (PLN NP UP Kendari · Integrated Management System · judul biru + No. Dokumen/Revisi/Tanggal). Expects: $title, $number, $revision, $date. --}}
<table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:10px;">
    <tr>
        <td style="width:170px; text-align:left; vertical-align:middle; padding:6px 8px; border:1px solid #000; border-bottom:none;">
            <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:34px;">
        </td>
        <td style="text-align:center; vertical-align:middle; padding:6px; border:1px solid #000; border-bottom:none;">
            <div style="font-weight:bold; font-size:12px; letter-spacing:0.5px;">PLN NUSANTARA POWER</div>
            <div style="font-weight:bold; font-size:11px; margin-top:2px;">UP KENDARI</div>
        </td>
        <td style="width:90px; text-align:center; vertical-align:middle; padding:4px 6px; border:1px solid #000; border-bottom:none;">
            <img src="/logo/k3.png" alt="K3" style="height:42px;">
        </td>
    </tr>
    <tr>
        <td colspan="3" style="text-align:center; font-weight:bold; font-size:10px; padding:3px 0; border:1px solid #000; letter-spacing:0.5px;">
            INTEGRATED MANAGEMENT SYSTEM
        </td>
    </tr>
    <tr>
        <td colspan="2" style="background:#7fa9d8; text-align:center; font-weight:bold; font-size:13px; color:#000; border:1px solid #000; padding:8px; vertical-align:middle; letter-spacing:0.5px;">
            {{ $title }}
        </td>
        <td style="padding:0; border:1px solid #000; vertical-align:top; font-size:9px;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #000; width:55px;">No. Dokumen</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $number }}</td>
                </tr>
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #000;">Revisi</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $revision }}</td>
                </tr>
                <tr>
                    <td style="padding:2px 4px;">Tanggal</td>
                    <td style="padding:2px 4px;">{{ $date }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
