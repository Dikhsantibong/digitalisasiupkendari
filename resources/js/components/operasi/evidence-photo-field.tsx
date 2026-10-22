import { Camera, ImagePlus, X } from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';
import { cn } from '@/lib/utils';

/**
 * One eviden photo (e.g. the kondisi awal or kondisi akhir of a finding): the
 * saved photo or the newly chosen file as a thumbnail, with a button to take /
 * choose a photo and one to remove it. On phones the picker offers the camera
 * and the gallery.
 */
export function EvidencePhotoField({
    label,
    url,
    file,
    onPick,
    onRemove,
    disabled = false,
    size = 'md',
}: {
    label: string;
    /** The saved photo, when there is one and no new file replaces it. */
    url: string | null;
    file: File | null;
    onPick: (file: File) => void;
    onRemove: () => void;
    disabled?: boolean;
    size?: 'sm' | 'md';
}) {
    const input = useRef<HTMLInputElement>(null);
    const preview = useMemo(() => (file ? URL.createObjectURL(file) : null), [file]);

    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    const src = preview ?? url;
    const box = size === 'sm' ? 'size-12' : 'size-20';

    return (
        <div className="flex items-center gap-2">
            <input
                ref={input}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(event) => {
                    const picked = event.target.files?.[0];

                    if (picked) {
                        onPick(picked);
                    }

                    event.target.value = '';
                }}
            />
            {src ? (
                <div className={cn('relative shrink-0 overflow-hidden rounded-md border border-border bg-muted', box)}>
                    <a href={src} target="_blank" rel="noreferrer" title={`Lihat foto ${label.toLowerCase()}`}>
                        <img src={src} alt={label} className="size-full object-cover" />
                    </a>
                    {!disabled && (
                        <button
                            type="button"
                            onClick={onRemove}
                            className="absolute top-0.5 right-0.5 flex size-5 items-center justify-center rounded-full bg-black/60 text-white"
                            aria-label={`Hapus foto ${label.toLowerCase()}`}
                        >
                            <X className="size-3" />
                        </button>
                    )}
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    disabled={disabled}
                    className={cn(
                        'flex shrink-0 flex-col items-center justify-center gap-0.5 rounded-md border border-dashed border-border text-[10px] text-muted-foreground transition hover:bg-muted/50 disabled:opacity-50',
                        box,
                    )}
                    aria-label={`Unggah foto ${label.toLowerCase()}`}
                >
                    <ImagePlus className="size-4" />
                    {size === 'md' && 'Foto'}
                </button>
            )}
            {src && !disabled && size === 'md' && (
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    className="inline-flex items-center gap-1 text-[12px] text-primary hover:underline"
                >
                    <Camera className="size-3.5" />
                    Ganti
                </button>
            )}
        </div>
    );
}
