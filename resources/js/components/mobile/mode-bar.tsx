import { Eye, Pencil } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * View / edit switch of the phone schedule layouts: the phone opens a
 * schedule read-only, and "Ubah Jadwal" switches to the input layout.
 */
export function MobileModeBar({ editing, onChange }: { editing: boolean; onChange: (editing: boolean) => void }) {
    return (
        <div
            className={`flex items-center gap-3 rounded-xl border px-3 py-2 ${editing ? 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10' : 'border-border bg-muted/40'}`}
        >
            <p className="min-w-0 flex-1 text-[12px] text-muted-foreground">
                {editing ? 'Mode ubah jadwal — perubahan disimpan dengan tombol Simpan.' : 'Menampilkan jadwal per tanggal.'}
            </p>
            {editing ? (
                <Button type="button" variant="outline" size="sm" onClick={() => onChange(false)} className="shrink-0 gap-1.5">
                    <Eye className="size-4" />
                    Lihat Jadwal
                </Button>
            ) : (
                <Button type="button" size="sm" onClick={() => onChange(true)} className="shrink-0 gap-1.5">
                    <Pencil className="size-4" />
                    Ubah Jadwal
                </Button>
            )}
        </div>
    );
}
