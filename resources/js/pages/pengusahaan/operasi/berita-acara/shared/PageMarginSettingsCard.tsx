import React from 'react';
import { SlidersHorizontal } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    showSettings: boolean;
    setShowSettings: (val: boolean | ((prev: boolean) => boolean)) => void;
    marginTop: number;
    setMarginTop: (val: number) => void;
    marginBottom: number;
    setMarginBottom: (val: number) => void;
    marginLeft: number;
    setMarginLeft: (val: number) => void;
    marginRight: number;
    setMarginRight: (val: number) => void;
    lineSpacing: string;
    setLineSpacing: (val: string) => void;
};

export function PageMarginSettingsCard({
    showSettings,
    setShowSettings,
    marginTop,
    setMarginTop,
    marginBottom,
    setMarginBottom,
    marginLeft,
    setMarginLeft,
    marginRight,
    setMarginRight,
    lineSpacing,
    setLineSpacing,
}: Props) {
    return (
        <div className="rounded-lg border border-border bg-card">
            <button
                type="button"
                onClick={() => setShowSettings((prev) => !prev)}
                className="w-full flex items-center justify-between p-3.5 text-xs font-semibold text-foreground hover:bg-muted/30 transition-colors"
            >
                <span className="flex items-center gap-2">
                    <SlidersHorizontal className="size-4 text-primary" />
                    Pengaturan Margin Kertas &amp; Spasi PDF
                </span>
                <span className="text-[11px] text-muted-foreground">
                    {showSettings ? 'Tutup Pengaturan' : 'Ubah Margin (mm) / Spasi'}
                </span>
            </button>

            {showSettings && (
                <div className="p-4 border-t border-border grid grid-cols-2 md:grid-cols-5 gap-3 text-xs bg-muted/10">
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">Margin Atas (mm)</Label>
                        <Input
                            type="number"
                            min={5}
                            max={50}
                            value={marginTop}
                            onChange={(e) => setMarginTop(Number(e.target.value) || 15)}
                            className="h-7 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">Margin Bawah (mm)</Label>
                        <Input
                            type="number"
                            min={5}
                            max={50}
                            value={marginBottom}
                            onChange={(e) => setMarginBottom(Number(e.target.value) || 15)}
                            className="h-7 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">Margin Kiri (mm)</Label>
                        <Input
                            type="number"
                            min={5}
                            max={50}
                            value={marginLeft}
                            onChange={(e) => setMarginLeft(Number(e.target.value) || 15)}
                            className="h-7 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">Margin Kanan (mm)</Label>
                        <Input
                            type="number"
                            min={5}
                            max={50}
                            value={marginRight}
                            onChange={(e) => setMarginRight(Number(e.target.value) || 15)}
                            className="h-7 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">Spasi Baris</Label>
                        <Input
                            type="number"
                            step="0.05"
                            min={1}
                            max={2}
                            value={lineSpacing}
                            onChange={(e) => setLineSpacing(e.target.value || '1.15')}
                            className="h-7 text-xs"
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
