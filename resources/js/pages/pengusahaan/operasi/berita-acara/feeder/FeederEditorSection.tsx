import React from 'react';
import {
    ImagePlus,
    Loader2,
    Plus,
    Trash2,
    Upload,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    formatNum,
    type AttachmentItem,
    type FeederRow,
    type FeederTotals,
} from '../types';

type Props = {
    feederRows: FeederRow[];
    feederTotals: FeederTotals;
    onAddFeederRow: () => void;
    onUpdateFeederName: (index: number, val: string) => void;
    onUpdateFeederReading: (
        index: number,
        direction: 'export' | 'import',
        field: 'awal' | 'akhir' | 'f_kali',
        val: number,
    ) => void;
    onUpdateFeederKeterangan: (index: number, val: string) => void;
    onRemoveFeederRow: (index: number) => void;
    catatan: string;
    setCatatan: (val: string) => void;

    // Attachments
    attachments: AttachmentItem[];
    isUploading: boolean;
    uploadError: string | null;
    onUploadImages: (e: React.ChangeEvent<HTMLInputElement>) => void;
    onUpdateAttachmentCaption: (index: number, caption: string) => void;
    onRemoveAttachment: (index: number) => void;
};

export function FeederEditorSection({
    feederRows,
    feederTotals,
    onAddFeederRow,
    onUpdateFeederName,
    onUpdateFeederReading,
    onUpdateFeederKeterangan,
    onRemoveFeederRow,
    catatan,
    setCatatan,

    attachments,
    isUploading,
    uploadError,
    onUploadImages,
    onUpdateAttachmentCaption,
    onRemoveAttachment,
}: Props) {
    return (
        <div className="space-y-6">
            {/* Section 1: Feeder Data Table */}
            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
                    <div>
                        <h4 className="text-sm font-semibold text-foreground">
                            Tabel Pemeriksaan kWh Meter Feeder
                        </h4>
                        <p className="text-[12px] text-muted-foreground">
                            Pemeriksaan stand awal, stand akhir, faktor kali, dan energi tersalur (Export &amp; Import) per outgoing feeder.
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={onAddFeederRow}
                        className="h-7 text-xs gap-1"
                    >
                        <Plus className="size-3.5" />
                        Tambah Feeder
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-md border border-border">
                    <table className="w-full text-xs text-left border-collapse">
                        <thead className="bg-muted/60 text-foreground font-semibold border-b border-border text-[11px]">
                            <tr>
                                <th rowSpan={2} className="p-2 w-10 text-center border-r border-border">NO</th>
                                <th rowSpan={2} className="p-2 min-w-[150px] border-r border-border">KWH FEEDER</th>
                                <th colSpan={5} className="p-2 text-center border-r border-border bg-muted/80">TERSALUR KWH</th>
                                <th rowSpan={2} className="p-2 min-w-[180px] border-r border-border">KETERANGAN</th>
                                <th rowSpan={2} className="p-2 w-12 text-center">AKSI</th>
                            </tr>
                            <tr className="border-t border-border bg-muted/40 text-[10px]">
                                <th className="p-1.5 w-16 text-center border-r border-border">TIPE</th>
                                <th className="p-1.5 min-w-[100px] text-right border-r border-border">AWAL</th>
                                <th className="p-1.5 min-w-[100px] text-right border-r border-border">AKHIR</th>
                                <th className="p-1.5 min-w-[85px] text-right border-r border-border">F. KALI</th>
                                <th className="p-1.5 min-w-[105px] text-right border-r border-border bg-muted/60 font-bold">HASIL</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {feederRows.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="p-6 text-center text-muted-foreground">
                                        Belum ada feeder yang terdaftar pada unit ini. Klik &ldquo;Tambah Feeder&rdquo; untuk menambahkan feeder.
                                    </td>
                                </tr>
                            ) : (
                                feederRows.map((row, idx) => (
                                    <React.Fragment key={idx}>
                                        {/* Row 1: Export */}
                                        <tr className="hover:bg-muted/10">
                                            <td rowSpan={2} className="p-2 text-center text-muted-foreground font-medium border-r border-border align-middle">
                                                {idx + 1}
                                            </td>
                                            <td rowSpan={2} className="p-2 border-r border-border align-middle">
                                                <Input
                                                    value={row.feeder_name}
                                                    onChange={(e) => onUpdateFeederName(idx, e.target.value)}
                                                    placeholder="Nama Feeder"
                                                    className="h-8 text-xs font-semibold"
                                                />
                                            </td>
                                            <td className="p-2 text-center border-r border-border">
                                                <Badge variant="outline" className="text-[10px] bg-primary/5 text-primary border-primary/30">
                                                    Export
                                                </Badge>
                                            </td>
                                            <td className="p-1.5 border-r border-border">
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    value={row.export.awal}
                                                    onChange={(e) => onUpdateFeederReading(idx, 'export', 'awal', Number(e.target.value) || 0)}
                                                    className="h-7 text-xs font-mono text-right"
                                                />
                                            </td>
                                            <td className="p-1.5 border-r border-border">
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    value={row.export.akhir}
                                                    onChange={(e) => onUpdateFeederReading(idx, 'export', 'akhir', Number(e.target.value) || 0)}
                                                    className="h-7 text-xs font-mono text-right"
                                                />
                                            </td>
                                            <td className="p-1.5 border-r border-border">
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    value={row.export.f_kali}
                                                    onChange={(e) => onUpdateFeederReading(idx, 'export', 'f_kali', Number(e.target.value) || 0)}
                                                    className="h-7 text-xs font-mono text-right"
                                                />
                                            </td>
                                            <td className="p-2 text-right font-mono font-bold border-r border-border bg-muted/20">
                                                {row.export.hasil > 0 ? formatNum(row.export.hasil) : '-'}
                                            </td>
                                            <td rowSpan={2} className="p-2 border-r border-border align-middle">
                                                <Input
                                                    value={row.keterangan || ''}
                                                    onChange={(e) => onUpdateFeederKeterangan(idx, e.target.value)}
                                                    placeholder="Keterangan status / beban feeder..."
                                                    className="h-8 text-xs"
                                                />
                                            </td>
                                            <td rowSpan={2} className="p-2 text-center align-middle">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    onClick={() => onRemoveFeederRow(idx)}
                                                    className="size-7 text-destructive hover:bg-destructive/10"
                                                    title="Hapus feeder ini"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            </td>
                                        </tr>
                                        {/* Row 2: Import */}
                                        <tr className="hover:bg-muted/10 border-b border-border/80">
                                            <td className="p-2 text-center border-r border-border">
                                                <Badge variant="outline" className="text-[10px] bg-muted text-muted-foreground border-border">
                                                    Import
                                                </Badge>
                                            </td>
                                            <td className="p-1.5 border-r border-border">
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    value={row.import.awal}
                                                    onChange={(e) => onUpdateFeederReading(idx, 'import', 'awal', Number(e.target.value) || 0)}
                                                    className="h-7 text-xs font-mono text-right"
                                                />
                                            </td>
                                            <td className="p-1.5 border-r border-border">
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    value={row.import.akhir}
                                                    onChange={(e) => onUpdateFeederReading(idx, 'import', 'akhir', Number(e.target.value) || 0)}
                                                    className="h-7 text-xs font-mono text-right"
                                                />
                                            </td>
                                            <td className="p-1.5 border-r border-border">
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    value={row.import.f_kali}
                                                    onChange={(e) => onUpdateFeederReading(idx, 'import', 'f_kali', Number(e.target.value) || 0)}
                                                    className="h-7 text-xs font-mono text-right"
                                                />
                                            </td>
                                            <td className="p-2 text-right font-mono font-bold border-r border-border bg-muted/20">
                                                {row.import.hasil > 0 ? formatNum(row.import.hasil) : '-'}
                                            </td>
                                        </tr>
                                    </React.Fragment>
                                ))
                            )}

                            {/* Summary Rows */}
                            {feederRows.length > 0 && (
                                <>
                                    <tr className="bg-muted/40 font-semibold border-t-2 border-border text-foreground">
                                        <td colSpan={6} className="p-2.5 text-center font-bold tracking-wide">
                                            JUMLAH EXPORT
                                        </td>
                                        <td className="p-2.5 text-right font-mono font-bold text-foreground border-r border-border">
                                            {feederTotals.jumlah_export > 0 ? formatNum(feederTotals.jumlah_export) : '-'}
                                        </td>
                                        <td colSpan={2}></td>
                                    </tr>
                                    <tr className="bg-muted/40 font-semibold border-t border-border text-foreground">
                                        <td colSpan={6} className="p-2.5 text-center font-bold tracking-wide">
                                            JUMLAH IMPORT
                                        </td>
                                        <td className="p-2.5 text-right font-mono font-bold text-foreground border-r border-border">
                                            {feederTotals.jumlah_import > 0 ? formatNum(feederTotals.jumlah_import) : '-'}
                                        </td>
                                        <td colSpan={2}></td>
                                    </tr>
                                    <tr className="bg-primary/10 font-bold border-t-2 border-primary/30 text-foreground">
                                        <td colSpan={6} className="p-2.5 text-center font-bold tracking-wide text-primary">
                                            TOTAL UNIT PLTD
                                        </td>
                                        <td className="p-2.5 text-right font-mono font-bold text-primary border-r border-border text-sm">
                                            {formatNum(feederTotals.total_unit)}
                                        </td>
                                        <td colSpan={2}></td>
                                    </tr>
                                </>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Section 2: Catatan Feeder */}
            <div className="rounded-lg border border-border bg-card p-4 space-y-2 text-xs">
                <Label className="font-semibold text-foreground">
                    Catatan: * Keterangan atau Catatan Tambahan:
                </Label>
                <textarea
                    rows={3}
                    value={catatan}
                    onChange={(e) => setCatatan(e.target.value)}
                    placeholder="Tuliskan catatan teknis kWh meter feeder bila ada (misal: pengalihan beban ke GI, penggantian CT/PT meter, dsb.)..."
                    className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />
            </div>

            {/* Section 3: Lampiran Gambar / Dokumentasi Foto Feeder */}
            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
                    <div>
                        <h4 className="text-sm font-semibold text-foreground flex items-center gap-2">
                            <ImagePlus className="size-4 text-primary" />
                            Lampiran Gambar &amp; Dokumentasi Foto (kWh Meter Feeder)
                        </h4>
                        <p className="text-[12px] text-muted-foreground">
                            Unggah foto dokumentasi fisik kWh meter feeder (stand awal/akhir) sebagai bukti eviden resmi berita acara.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <label className="cursor-pointer">
                            <input
                                type="file"
                                multiple
                                accept="image/png,image/jpeg,image/webp"
                                className="hidden"
                                onChange={onUploadImages}
                                disabled={isUploading}
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-8 text-xs gap-1.5 cursor-pointer pointer-events-none"
                                disabled={isUploading}
                            >
                                {isUploading ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Upload className="size-3.5" />
                                )}
                                {isUploading ? 'Mengunggah...' : 'Unggah Foto'}
                            </Button>
                        </label>
                    </div>
                </div>

                {uploadError && (
                    <div className="p-3 rounded-md bg-destructive/10 border border-destructive/20 text-destructive text-xs">
                        {uploadError}
                    </div>
                )}

                {attachments.length === 0 ? (
                    <div className="p-6 text-center border border-dashed border-border rounded-lg text-xs text-muted-foreground">
                        Belum ada foto dokumentasi yang diunggah. Klik &ldquo;Unggah Foto&rdquo; di atas untuk melampirkan eviden kWh meter feeder.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {attachments.map((item, idx) => (
                            <div key={item.id || idx} className="rounded-lg border border-border bg-muted/10 p-2.5 space-y-2 relative group">
                                <div className="relative aspect-4/3 w-full overflow-hidden rounded-md bg-muted">
                                    <img
                                        src={item.url}
                                        alt={item.caption || 'Lampiran'}
                                        className="h-full w-full object-cover"
                                    />
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="destructive"
                                        onClick={() => onRemoveAttachment(idx)}
                                        className="absolute top-1.5 right-1.5 size-7 opacity-90 hover:opacity-100 shadow-xs"
                                        title="Hapus foto ini"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-[10px] text-muted-foreground">Keterangan Foto</Label>
                                    <Input
                                        value={item.caption}
                                        onChange={(e) => onUpdateAttachmentCaption(idx, e.target.value)}
                                        placeholder="Contoh: kWh Meter Feeder KONDA"
                                        className="h-7 text-xs"
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
