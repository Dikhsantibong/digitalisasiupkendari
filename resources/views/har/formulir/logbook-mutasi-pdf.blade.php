{{-- PDF (A4 portrait) Formulir Logbook Mutasi Harian Tim Pemeliharaan — satu lembar per tanggal. Data: Har\LogbookMutasiController::pdfView(). --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Logbook Mutasi Harian - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 12mm 14mm 12mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #000; margin: 0; }
        .lbm-sheet .kop { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .lbm-sheet .kop td { vertical-align: middle; border: 1px solid #000; }
        .lbm-sheet .kop .logo { width: 22%; padding: 4px 6px; text-align: center; }
        .lbm-sheet .kop .logo img { max-height: 38px; max-width: 130px; }
        .lbm-sheet .kop .line { text-align: center; font-weight: bold; font-size: 9.5px; padding: 2px 4px; }
        .lbm-sheet .tgl { width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; }
        .lbm-sheet .tgl td { padding: 3px 6px; font-weight: bold; }
        .lbm-sheet .grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .lbm-sheet .grid th, .lbm-sheet .grid td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        .lbm-sheet .grid th { font-weight: bold; text-align: center; background: #f2f2f2; }
        .lbm-sheet .grid .section td { font-weight: bold; background: #d9e2f3; }
        .lbm-sheet .grid td { height: 14px; }
        .lbm-sheet .c { text-align: center; }
        .lbm-sheet .no { width: 22px; text-align: center; }
        .lbm-break { page-break-before: always; }
    </style>
</head>
<body>
    @php
        // Belum ada logbook: cetak satu lembar kosong berkop (tanpa data dummy).
        $sheets = $logbooks !== [] ? $logbooks : [['hari_tanggal' => '', 'absensi' => [], 'apd' => [], 'rutin' => [], 'non_rutin' => [], 'kondisi_k3' => []]];
    @endphp
    @foreach($sheets as $logbook)
        @if(! $loop->first)
            <div class="lbm-break"></div>
        @endif
        <div class="lbm-sheet">
            @include('har.formulir.partials.daily-meeting-kop', ['title' => 'LOGBOOK MUTASI HARIAN TIM PEMELIHARAAN'])
            <table class="tgl"><tr><td>HARI / TANGGAL : {{ strtoupper($logbook['hari_tanggal']) }}</td></tr></table>

            <table class="grid">
                <thead>
                    <tr><th class="no">NO</th><th style="width: 38%;">NAMA</th><th style="width: 24%;">JABATAN</th><th>KETERANGAN</th><th style="width: 12%;">PARAF</th></tr>
                </thead>
                <tbody>
                    <tr class="section"><td colspan="5">A. ABSENSI</td></tr>
                    @forelse($logbook['absensi'] as $row)
                        <tr><td class="no">{{ $loop->iteration }}</td><td>{{ $row['nama'] }}</td><td class="c">{{ $row['jabatan'] }}</td><td class="c">{{ $row['keterangan'] }}</td><td class="c">{{ $row['paraf'] }}</td></tr>
                    @empty
                        <tr><td class="no">1</td><td></td><td></td><td></td><td></td></tr>
                    @endforelse
                </tbody>
            </table>

            <table class="grid">
                <thead><tr><th class="no">NO</th><th style="width: 50%;">URAIAN</th><th>KETERANGAN</th></tr></thead>
                <tbody>
                    @foreach([
                        ['B. KESIAPAN APD', $logbook['apd'], 'item'],
                        ['C. JOB HARIAN RUTIN', $logbook['rutin'], 'uraian'],
                        ['D. JOB HARIAN NON RUTIN', $logbook['non_rutin'], 'uraian'],
                        ['E. KONDISI K3 (UNSAFE ACTION & UNSAFE CONDITION)', $logbook['kondisi_k3'], 'uraian'],
                    ] as [$label, $rows, $key])
                        <tr class="section"><td colspan="3">{{ $label }}</td></tr>
                        @forelse($rows as $row)
                            <tr><td class="no">{{ $loop->iteration }}</td><td>{{ $row[$key] }}</td><td>{{ $row['keterangan'] }}</td></tr>
                        @empty
                            <tr><td class="no">1</td><td></td><td></td></tr>
                        @endforelse
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
