import React from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    docNumber: string;
    setDocNumber: (val: string) => void;
    docTitle: string;
    setDocTitle: (val: string) => void;
    revision: string;
    setRevision: (val: string) => void;
    revisionDate: string;
    setRevisionDate: (val: string) => void;
    hari: string;
    setHari: (val: string) => void;
    tanggalTerbilang: string;
    setTanggalTerbilang: (val: string) => void;
    bulanTerbilang: string;
    setBulanTerbilang: (val: string) => void;
    tahunTerbilang: string;
    setTahunTerbilang: (val: string) => void;
    tanggalPenuh: string;
    setTanggalPenuh: (val: string) => void;
    printPlaceDate: string;
    setPrintPlaceDate: (val: string) => void;
};

export function DocumentMetadataCard({
    docNumber,
    setDocNumber,
    docTitle,
    setDocTitle,
    revision,
    setRevision,
    revisionDate,
    setRevisionDate,
    hari,
    setHari,
    tanggalTerbilang,
    setTanggalTerbilang,
    bulanTerbilang,
    setBulanTerbilang,
    tahunTerbilang,
    setTahunTerbilang,
    tanggalPenuh,
    setTanggalPenuh,
    printPlaceDate,
    setPrintPlaceDate,
}: Props) {
    return (
        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
            <div className="border-b border-border pb-2">
                <h4 className="text-sm font-semibold text-foreground">
                    Metadata Dokumen &amp; Narasi Pembuka
                </h4>
                <p className="text-[12px] text-muted-foreground">
                    Sesuaikan nomor surat, judul resmi dokumen, dan penanggalan terbilang untuk kop surat.
                </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                <div className="space-y-1">
                    <Label className="text-[11px] text-muted-foreground">Nomor Dokumen</Label>
                    <Input
                        value={docNumber}
                        onChange={(e) => setDocNumber(e.target.value)}
                        placeholder="Contoh: 021/OPS/BA-HSD"
                        className="h-8 text-xs font-mono"
                    />
                </div>
                <div className="space-y-1 lg:col-span-2">
                    <Label className="text-[11px] text-muted-foreground">Judul Dokumen (Kop Surat)</Label>
                    <Input
                        value={docTitle}
                        onChange={(e) => setDocTitle(e.target.value)}
                        placeholder="BERITA ACARA PEMERIKSAAN..."
                        className="h-8 text-xs font-semibold"
                    />
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">Revisi</Label>
                        <Input
                            value={revision}
                            onChange={(e) => setRevision(e.target.value)}
                            placeholder="00"
                            className="h-8 text-xs text-center"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">Tgl Revisi</Label>
                        <Input
                            type="date"
                            value={revisionDate}
                            onChange={(e) => setRevisionDate(e.target.value)}
                            className="h-8 text-xs"
                        />
                    </div>
                </div>
            </div>

            {/* Narrative Dates */}
            <div className="pt-2 border-t border-border/60">
                <div className="text-[11px] font-semibold text-muted-foreground mb-2">
                    Narasi Tanggal Terbilang (Contoh: &ldquo;Pada hari ini <u>{hari || '...'}</u> tanggal <u>{tanggalTerbilang || '...'}</u> bulan <u>{bulanTerbilang || '...'}</u> tahun <u>{tahunTerbilang || '...'}</u>...&rdquo;)
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-2.5 text-xs">
                    <div className="space-y-1">
                        <Label className="text-[10px] text-muted-foreground">Hari</Label>
                        <Input
                            value={hari}
                            onChange={(e) => setHari(e.target.value)}
                            placeholder="Senin"
                            className="h-7 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[10px] text-muted-foreground">Tgl Terbilang</Label>
                        <Input
                            value={tanggalTerbilang}
                            onChange={(e) => setTanggalTerbilang(e.target.value)}
                            placeholder="Satu"
                            className="h-7 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[10px] text-muted-foreground">Bulan</Label>
                        <Input
                            value={bulanTerbilang}
                            onChange={(e) => setBulanTerbilang(e.target.value)}
                            placeholder="Agustus"
                            className="h-7 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[10px] text-muted-foreground">Tahun Terbilang</Label>
                        <Input
                            value={tahunTerbilang}
                            onChange={(e) => setTahunTerbilang(e.target.value)}
                            placeholder="Dua Ribu Dua Puluh Enam"
                            className="h-7 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[10px] text-muted-foreground">Tanggal Lengkap</Label>
                        <Input
                            value={tanggalPenuh}
                            onChange={(e) => setTanggalPenuh(e.target.value)}
                            placeholder="01 Agustus 2026"
                            className="h-7 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[10px] text-muted-foreground">Tempat &amp; Tgl Cetak</Label>
                        <Input
                            value={printPlaceDate}
                            onChange={(e) => setPrintPlaceDate(e.target.value)}
                            placeholder="Kendari, 01 Agustus 2026"
                            className="h-7 text-xs"
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
