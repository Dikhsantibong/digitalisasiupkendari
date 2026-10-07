import React from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatNum, type PelumasRow } from '../types';

type Props = {
    pelumasRows: PelumasRow[];
    pelumasTotals: PelumasRow;
    onAddPelumasRow: () => void;
    onUpdatePelumasRow: (index: number, field: keyof PelumasRow, val: string | number) => void;
    onRemovePelumasRow: (index: number) => void;
    catatan: string;
    setCatatan: (val: string) => void;
};

export function PelumasEditorSection({
    pelumasRows,
    pelumasTotals,
    onAddPelumasRow,
    onUpdatePelumasRow,
    onRemovePelumasRow,
    catatan,
    setCatatan,
}: Props) {
    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h4 className="text-sm font-semibold text-foreground">
                        Tabel Pemeriksaan Fisik Minyak Pelumas
                    </h4>
                    <p className="text-[12px] text-muted-foreground">
                        Pemeriksaan saldo awal, penerimaan, pemakaian, pengiriman, saldo administrasi, dan stok fisik pelumas.
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={onAddPelumasRow}
                    className="h-7 text-xs gap-1"
                >
                    <Plus className="size-3.5" />
                    Tambah Jenis Pelumas
                </Button>
            </div>

            <div className="overflow-x-auto rounded-md border border-border">
                <table className="w-full text-xs text-left border-collapse">
                    <thead className="bg-muted/60 text-foreground font-semibold border-b border-border text-[11px]">
                        <tr>
                            <th className="p-2 w-8 text-center">No</th>
                            <th className="p-2 min-w-[140px]">Jenis Pelumas</th>
                            <th className="p-2 w-20">Satuan</th>
                            <th className="p-2 min-w-[100px] text-right">Persediaan Awal</th>
                            <th className="p-2 min-w-[100px] text-right">Penerimaan</th>
                            <th className="p-2 min-w-[100px] text-right bg-muted/40 font-bold">Stock</th>
                            <th className="p-2 min-w-[100px] text-right">Pemakaian</th>
                            <th className="p-2 min-w-[100px] text-right">Pengiriman</th>
                            <th className="p-2 min-w-[100px] text-right bg-muted/40 font-bold">Saldo Adm</th>
                            <th className="p-2 min-w-[100px] text-right">Stock Fisik</th>
                            <th className="p-2 min-w-[100px] text-right font-bold">Selisih</th>
                            <th className="p-2 w-10 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {pelumasRows.length === 0 ? (
                            <tr>
                                <td colSpan={12} className="p-4 text-center text-muted-foreground">
                                    Belum ada data master pelumas. Klik &ldquo;Tambah Jenis Pelumas&rdquo; untuk menambahkan.
                                </td>
                            </tr>
                        ) : (
                            pelumasRows.map((row, idx) => (
                                <tr key={idx} className="hover:bg-muted/10">
                                    <td className="p-2 text-center text-muted-foreground">{idx + 1}</td>
                                    <td className="p-2">
                                        <Input
                                            value={row.jenis}
                                            onChange={(e) =>
                                                onUpdatePelumasRow(idx, 'jenis', e.target.value)
                                            }
                                            className="h-7 text-xs font-semibold"
                                        />
                                    </td>
                                    <td className="p-2">
                                        <Input
                                            value={row.satuan}
                                            onChange={(e) =>
                                                onUpdatePelumasRow(idx, 'satuan', e.target.value)
                                            }
                                            className="h-7 text-xs"
                                        />
                                    </td>
                                    <td className="p-2">
                                        <Input
                                            type="number"
                                            step="any"
                                            value={row.awal}
                                            onChange={(e) =>
                                                onUpdatePelumasRow(idx, 'awal', Number(e.target.value) || 0)
                                            }
                                            className="h-7 text-xs font-mono text-right"
                                        />
                                    </td>
                                    <td className="p-2">
                                        <Input
                                            type="number"
                                            step="any"
                                            value={row.penerimaan}
                                            onChange={(e) =>
                                                onUpdatePelumasRow(idx, 'penerimaan', Number(e.target.value) || 0)
                                            }
                                            className="h-7 text-xs font-mono text-right"
                                        />
                                    </td>
                                    <td className="p-2 text-right font-mono font-bold bg-muted/20">
                                        {formatNum(row.stock)}
                                    </td>
                                    <td className="p-2">
                                        <Input
                                            type="number"
                                            step="any"
                                            value={row.pemakaian}
                                            onChange={(e) =>
                                                onUpdatePelumasRow(idx, 'pemakaian', Number(e.target.value) || 0)
                                            }
                                            className="h-7 text-xs font-mono text-right"
                                        />
                                    </td>
                                    <td className="p-2">
                                        <Input
                                            type="number"
                                            step="any"
                                            value={row.pengiriman}
                                            onChange={(e) =>
                                                onUpdatePelumasRow(idx, 'pengiriman', Number(e.target.value) || 0)
                                            }
                                            className="h-7 text-xs font-mono text-right"
                                        />
                                    </td>
                                    <td className="p-2 text-right font-mono font-bold bg-muted/20">
                                        {formatNum(row.administrasi)}
                                    </td>
                                    <td className="p-2">
                                        <Input
                                            type="number"
                                            step="any"
                                            value={row.fisik_liter}
                                            onChange={(e) =>
                                                onUpdatePelumasRow(idx, 'fisik_liter', Number(e.target.value) || 0)
                                            }
                                            className="h-7 text-xs font-mono text-right font-semibold"
                                        />
                                    </td>
                                    <td className={`p-2 text-right font-mono font-bold ${
                                        row.selisih < 0 ? 'text-destructive' : row.selisih > 0 ? 'text-emerald-600' : 'text-foreground'
                                    }`}>
                                        {row.selisih > 0 ? `+${formatNum(row.selisih)}` : formatNum(row.selisih)}
                                    </td>
                                    <td className="p-2 text-center">
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            onClick={() => onRemovePelumasRow(idx)}
                                            className="size-7 text-destructive hover:bg-destructive/10"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                            ))
                        )}

                        {/* Summary TOT Row */}
                        {pelumasRows.length > 0 && (
                            <tr className="bg-muted/40 font-bold border-t-2 border-border text-foreground">
                                <td colSpan={3} className="p-2 text-center tracking-wide">
                                    TOTAL
                                </td>
                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.awal)}</td>
                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.penerimaan)}</td>
                                <td className="p-2 text-right font-mono bg-muted/60">{formatNum(pelumasTotals.stock)}</td>
                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.pemakaian)}</td>
                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.pengiriman)}</td>
                                <td className="p-2 text-right font-mono bg-muted/60">{formatNum(pelumasTotals.administrasi)}</td>
                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.fisik_liter)}</td>
                                <td className={`p-2 text-right font-mono font-bold ${
                                    pelumasTotals.selisih < 0 ? 'text-destructive' : pelumasTotals.selisih > 0 ? 'text-emerald-600' : ''
                                }`}>
                                    {pelumasTotals.selisih > 0 ? `+${formatNum(pelumasTotals.selisih)}` : formatNum(pelumasTotals.selisih)}
                                </td>
                                <td></td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Catatan Pelumas */}
            <div className="rounded-lg border border-border bg-card p-4 space-y-2 text-xs">
                <Label className="font-semibold text-foreground">
                    Catatan: * Keterangan atau Catatan Tambahan Pelumas:
                </Label>
                <textarea
                    rows={3}
                    value={catatan}
                    onChange={(e) => setCatatan(e.target.value)}
                    placeholder="Tuliskan catatan teknis kondisi minyak pelumas bila ada..."
                    className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />
            </div>
        </div>
    );
}
