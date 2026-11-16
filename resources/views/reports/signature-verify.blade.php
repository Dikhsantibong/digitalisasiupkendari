<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Tanda Tangan Elektronik — PLN Nusantara Power UP Kendari</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 16px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            line-height: 1.5;
        }
        .card {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 22px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .header img {
            height: 36px;
            width: auto;
        }
        .header-title {
            font-size: 15px;
            font-weight: 700;
            color: #0b4a6f;
            margin: 0;
        }
        .header-sub {
            font-size: 12px;
            color: #475569;
            margin: 0;
        }
        .banner {
            padding: 16px 22px;
            border-bottom: 1px solid #e2e8f0;
        }
        .banner.valid {
            background: #ecfdf5;
            color: #065f46;
        }
        .banner.invalid {
            background: #fef2f2;
            color: #991b1b;
        }
        .badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.4px;
            padding: 4px 10px;
            border-radius: 999px;
            margin-bottom: 6px;
        }
        .banner.valid .badge { background: #10b981; color: #fff; }
        .banner.invalid .badge { background: #ef4444; color: #fff; }
        .reason {
            margin: 0;
            font-size: 13.5px;
        }
        .section {
            padding: 18px 22px;
        }
        .section + .section {
            border-top: 1px solid #e2e8f0;
        }
        .section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            margin: 0 0 12px;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        .meta td {
            padding: 6px 0;
            vertical-align: top;
        }
        .meta td.label {
            width: 38%;
            color: #64748b;
            padding-right: 12px;
        }
        .meta td.value {
            font-weight: 600;
            color: #0f172a;
        }
        .chain-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .chain-item:last-child { margin-bottom: 0; }
        .chain-state {
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .chain-state.ok { color: #059669; }
        .chain-state.wait { color: #64748b; }
        .footer {
            padding: 12px 22px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 11.5px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <img src="/logo/icon.png" alt="Logo PLN Nusantara Power">
            <div>
                <p class="header-title">Verifikasi Tanda Tangan Elektronik</p>
                <p class="header-sub">PT PLN Nusantara Power — Unit Pembangkitan Kendari</p>
            </div>
        </div>

        <div class="banner {{ $valid ? 'valid' : 'invalid' }}">
            <span class="badge">{{ $statusBadge }}</span>
            <p class="reason">{{ $reason }}</p>
        </div>

        @if($found)
            <div class="section">
                <h2 class="section-title">Detail Dokumen &amp; Penanda Tangan</h2>
                <table class="meta">
                    <tr>
                        <td class="label">Dokumen</td>
                        <td class="value">{{ $documentTitle }}</td>
                    </tr>
                    <tr>
                        <td class="label">Unit Pembangkit</td>
                        <td class="value">{{ $unitName }}</td>
                    </tr>
                    <tr>
                        <td class="label">Periode</td>
                        <td class="value">{{ $period }}</td>
                    </tr>
                    <tr>
                        <td class="label">Status Dokumen</td>
                        <td class="value">{{ $workflowStatus }}</td>
                    </tr>
                    <tr>
                        <td class="label">Peran</td>
                        <td class="value">{{ $signerRole }}</td>
                    </tr>
                    <tr>
                        <td class="label">Jabatan</td>
                        <td class="value">{{ $signerPosition }}</td>
                    </tr>
                    <tr>
                        <td class="label">Pejabat</td>
                        <td class="value">{{ $signerName }}</td>
                    </tr>
                    @if($signedAt)
                        <tr>
                            <td class="label">Waktu Pengesahan</td>
                            <td class="value">{{ $signedAt }}</td>
                        </tr>
                    @endif
                    @if($note)
                        <tr>
                            <td class="label">Catatan</td>
                            <td class="value">{{ $note }}</td>
                        </tr>
                    @endif
                </table>
            </div>

            @if(count($chain) > 0)
                <div class="section">
                    <h2 class="section-title">Rantai Pengesahan</h2>
                    @foreach($chain as $item)
                        <div class="chain-item">
                            <div>
                                <div style="font-weight:600;">{{ $item['caption'] }} — {{ $item['position'] }}</div>
                                <div style="color:#475569; font-size:12.5px;">{{ $item['name'] }}</div>
                            </div>
                            <span class="chain-state {{ $item['signed'] ? 'ok' : 'wait' }}">
                                {{ $item['signed'] ? 'Sah (' . $item['signed_at'] . ')' : 'Belum disahkan' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="footer">
            Diverifikasi melalui sistem Digitalisasi Unit UP Kendari · Halaman publik resmi
        </div>
    </div>
</body>
</html>
