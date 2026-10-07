import React from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SignatoryOption } from '../types';

type Props = {
    managerOptions: SignatoryOption[];
    managerId: string;
    onManagerChange: (id: string) => void;
    managerName: string;
    setManagerName: (name: string) => void;
    managerTitle: string;
    setManagerTitle: (title: string) => void;
    selectedManager?: SignatoryOption;

    tlOptions: SignatoryOption[];
    tlId: string;
    onTlChange: (id: string) => void;
    tlName: string;
    setTlName: (name: string) => void;
    tlTitle: string;
    setTlTitle: (title: string) => void;
    selectedTl?: SignatoryOption;
};

export function SignersCard({
    managerOptions,
    managerId,
    onManagerChange,
    managerName,
    setManagerName,
    managerTitle,
    setManagerTitle,
    selectedManager,

    tlOptions,
    tlId,
    onTlChange,
    tlName,
    setTlName,
    tlTitle,
    setTlTitle,
    selectedTl,
}: Props) {
    return (
        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
            <div className="border-b border-border pb-2">
                <h4 className="text-sm font-semibold text-foreground">
                    Penandatangan Berita Acara
                </h4>
                <p className="text-[12px] text-muted-foreground">
                    Pilih pejabat penandatangan Manajer dan TL Operasi. Tanda tangan digital otomatis tersemat jika terdaftar.
                </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                {/* Left: Manajer */}
                <div className="p-3 rounded-md border border-border bg-muted/20 space-y-3">
                    <div className="font-semibold text-foreground text-xs flex items-center justify-between">
                        <span>Pihak Mengetahui / Menyetujui (Manajer)</span>
                        {selectedManager?.has_signature && (
                            <span className="text-[10px] text-emerald-600 font-medium">✓ Ada TTD Digital</span>
                        )}
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">Pilih Pejabat</Label>
                        <Select value={managerId} onValueChange={onManagerChange}>
                            <SelectTrigger className="h-8 text-xs">
                                <SelectValue placeholder="Pilih dari daftar pegawai..." />
                            </SelectTrigger>
                            <SelectContent>
                                {managerOptions.map((opt) => (
                                    <SelectItem key={opt.id} value={String(opt.id)} className="text-xs">
                                        {opt.name} {opt.position ? `(${opt.position})` : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1">
                            <Label className="text-[10px] text-muted-foreground">Jabatan</Label>
                            <Input
                                value={managerTitle}
                                onChange={(e) => setManagerTitle(e.target.value)}
                                placeholder="Manajer ULPLTD / Unit"
                                className="h-7 text-xs"
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-[10px] text-muted-foreground">Nama Lengkap</Label>
                            <Input
                                value={managerName}
                                onChange={(e) => setManagerName(e.target.value)}
                                placeholder="Nama pejabat..."
                                className="h-7 text-xs font-semibold"
                            />
                        </div>
                    </div>
                </div>

                {/* Right: Team Leader Operasi */}
                <div className="p-3 rounded-md border border-border bg-muted/20 space-y-3">
                    <div className="font-semibold text-foreground text-xs flex items-center justify-between">
                        <span>Pihak Pemeriksa (TL Operasi)</span>
                        {selectedTl?.has_signature && (
                            <span className="text-[10px] text-emerald-600 font-medium">✓ Ada TTD Digital</span>
                        )}
                    </div>
                    <div className="space-y-1">
                        <Label className="text-[11px] text-muted-foreground">Pilih Pejabat</Label>
                        <Select value={tlId} onValueChange={onTlChange}>
                            <SelectTrigger className="h-8 text-xs">
                                <SelectValue placeholder="Pilih dari daftar pegawai..." />
                            </SelectTrigger>
                            <SelectContent>
                                {tlOptions.map((opt) => (
                                    <SelectItem key={opt.id} value={String(opt.id)} className="text-xs">
                                        {opt.name} {opt.position ? `(${opt.position})` : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1">
                            <Label className="text-[10px] text-muted-foreground">Jabatan</Label>
                            <Input
                                value={tlTitle}
                                onChange={(e) => setTlTitle(e.target.value)}
                                placeholder="Team Leader Operasi"
                                className="h-7 text-xs"
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-[10px] text-muted-foreground">Nama Lengkap</Label>
                            <Input
                                value={tlName}
                                onChange={(e) => setTlName(e.target.value)}
                                placeholder="Nama pejabat..."
                                className="h-7 text-xs font-semibold"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
