@php
    /**
     * Tabel matriks Formulir Metode Pengujian Peralatan, dipakai PDF formulir
     * dan Laporan K3.
     *
     * @var list<array<string, mixed>> $rows
     * @var string $tableClass
     * @var string $centerClass
     */
    $tableClass ??= 'matrix-table';
    $centerClass ??= 'text-center';
    $testColumns = ['uji_visual', 'uji_fungsi', 'uji_beban', 'uji_hydro', 'ndt', 'uji_ultrasonic_thickness', 'uji_ketahanan'];
@endphp

<table class="{{ $tableClass }}">
    <thead>
        <tr>
            <th rowspan="2" style="width: 3%;">No.</th>
            <th rowspan="2" style="width: 11%;">Nama Peralatan</th>
            <th rowspan="2" style="width: 9%;">No. Pengesahan</th>
            <th rowspan="2" style="width: 11%;">Nama Kategori Alat</th>
            <th colspan="7">Metode Pemeriksaan</th>
            <th colspan="2">Sertifikasi</th>
            <th rowspan="2" style="width: 13%;">Keterangan</th>
        </tr>
        <tr>
            <th>Uji Visual</th>
            <th>Uji Fungsi</th>
            <th>Uji Beban</th>
            <th>Uji Hydro</th>
            <th>NDT</th>
            <th>Uji Ultrasonic Thickness</th>
            <th>Uji Ketahanan</th>
            <th>Terakhir</th>
            <th>Ulang</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                <td class="{{ $centerClass }}">{{ $row['no_urut'] ?? $loop->iteration }}</td>
                <td class="font-bold">{{ $row['nama_peralatan'] ?? '' }}</td>
                <td>{{ $row['no_pengesahan'] ?? '-' }}</td>
                <td>{{ $row['nama_kategori_alat'] ?? '-' }}</td>
                @foreach($testColumns as $column)
                    @php($value = ($row[$column] ?? '') ?: '-')
                    <td class="{{ $centerClass }} {{ $value === 'Memenuhi' ? 'ok' : ($value === 'Tidak Memenuhi' ? 'not-ok' : '') }}">{{ $value }}</td>
                @endforeach
                <td class="{{ $centerClass }}">{{ $row['sertifikasi_terakhir'] ?? '-' }}</td>
                <td class="{{ $centerClass }}">{{ $row['sertifikasi_ulang'] ?? '-' }}</td>
                <td>{{ $row['keterangan'] ?? '' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="14" class="{{ $centerClass }}" style="padding: 10px;">Belum ada data peralatan.</td>
            </tr>
        @endforelse
    </tbody>
</table>
