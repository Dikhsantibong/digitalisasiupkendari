{{-- PDF (A4 portrait) Formulir Daily Meeting Pemeliharaan — per meeting: lembar 1 daftar hadir, lembar 2 foto eviden. Data: Har\DailyMeetingController::pdfView(). --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Hadir Meeting Pemeliharaan - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 12mm 14mm 12mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #000; margin: 0; }
        .kop { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .kop td { vertical-align: middle; border: 1px solid #000; }
        .kop .logo { width: 22%; padding: 4px 6px; text-align: center; }
        .kop .logo img { max-height: 38px; max-width: 130px; }
        .kop .line { text-align: center; font-weight: bold; font-size: 9.5px; padding: 2px 4px; }
        .info { width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; }
        .info td { padding: 2px 6px; font-size: 9px; }
        .info .label { width: 60px; }
        .hadir { width: 100%; border-collapse: collapse; }
        .hadir th, .hadir td { border: 1px solid #000; padding: 3px 5px; }
        .hadir th { font-weight: bold; text-align: center; background: #f2f2f2; }
        .hadir td { height: 22px; }
        .c { text-align: center; }
        .ttd-no { font-size: 7px; vertical-align: top; }
        .sign { width: 100%; margin-top: 26px; }
        .sign td { width: 50%; text-align: center; vertical-align: top; }
        .sign .space { height: 60px; }
        .sign .name { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .page-break { page-break-before: always; }
        .eviden-title { text-align: center; font-weight: bold; font-size: 11px; margin: 10px 0 6px 0; }
        .photos { width: 100%; border-collapse: collapse; }
        .photos td { width: 50%; padding: 4px; text-align: center; vertical-align: top; border: 1px solid #000; }
        .photos img { max-width: 250px; max-height: 190px; }
        .empty { text-align: center; padding: 30px; border: 1px dashed #666; color: #555; }
    </style>
</head>
<body>
    @forelse($meetings as $meeting)
        @if(! $loop->first)
            <div class="page-break"></div>
        @endif

        @include('har.formulir.partials.daily-meeting-kop', ['title' => 'DAFTAR HADIR MEETING PEMELIHARAAN PEMBANGKIT'])
        <table class="info">
            <tr><td class="label">Acara</td><td>: {{ $meeting['acara'] }}</td></tr>
            <tr><td class="label">Hari/Tgl</td><td>: {{ $meeting['hari_tanggal'] }}</td></tr>
            <tr><td class="label">Waktu</td><td>: {{ $meeting['waktu'] }}</td></tr>
            <tr><td class="label">Tempat</td><td>: {{ $meeting['tempat'] }}</td></tr>
        </table>

        <table class="hadir">
            <thead>
                <tr>
                    <th style="width: 22px;">No</th>
                    <th>NAMA</th>
                    <th style="width: 22%;">ASAL/PERUSAHAAN</th>
                    <th style="width: 20%;">JABATAN</th>
                    <th style="width: 24%;">TANDA TANGAN</th>
                </tr>
            </thead>
            <tbody>
                @foreach(range(0, max($minRows, count($meeting['peserta'])) - 1) as $index)
                    @php $peserta = $meeting['peserta'][$index] ?? null; @endphp
                    <tr>
                        <td class="c">{{ $index + 1 }}</td>
                        <td>{{ $peserta['nama'] ?? '' }}</td>
                        <td class="c">{{ $peserta['asal'] ?? '' }}</td>
                        <td class="c">{{ $peserta['jabatan'] ?? '' }}</td>
                        <td class="ttd-no" style="text-align: {{ $index % 2 === 0 ? 'left' : 'right' }};">{{ $peserta ? $index + 1 : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="sign">
            <tr>
                <td>{{ $signers['kiri']['jabatan'] }}</td>
                <td>{{ $signers['kanan']['jabatan'] }}</td>
            </tr>
            <tr><td class="space"></td><td class="space"></td></tr>
            <tr>
                <td class="name">{{ $signers['kiri']['nama'] ?: '(....................................)' }}</td>
                <td class="name">{{ $signers['kanan']['nama'] ?: '(....................................)' }}</td>
            </tr>
        </table>

        {{-- Lembar 2: foto eviden meeting --}}
        <div class="page-break"></div>
        @include('har.formulir.partials.daily-meeting-kop', ['title' => 'EVIDEN FOTO MEETING PEMELIHARAAN PEMBANGKIT'])
        <div class="eviden-title">{{ $meeting['acara'] }} — {{ $meeting['hari_tanggal'] }}</div>
        @if($meeting['eviden_images'] === [])
            <div class="empty">Belum ada foto eviden untuk meeting ini.</div>
        @else
            <table class="photos">
                @foreach(array_chunk($meeting['eviden_images'], 2) as $pair)
                    <tr>
                        @foreach($pair as $image)
                            <td><img src="{{ $image }}" alt="Eviden meeting"></td>
                        @endforeach
                        @if(count($pair) < 2)
                            <td style="border: none;"></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @endif
    @empty
        @include('har.formulir.partials.daily-meeting-kop', ['title' => 'DAFTAR HADIR MEETING PEMELIHARAAN PEMBANGKIT'])
        <div class="empty" style="margin-top: 10px;">Belum ada daily meeting tersimpan untuk periode ini.</div>
    @endforelse
</body>
</html>
