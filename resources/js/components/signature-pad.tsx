import React, { useEffect, useRef, useState } from 'react';
import { RotateCcw, Undo2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Point = { x: number; y: number };

type SignaturePadProps = {
    onChange: (dataUrl: string | null) => void;
    className?: string;
};

function getCanvasCoords(canvas: HTMLCanvasElement, e: React.MouseEvent<HTMLCanvasElement> | React.TouchEvent<HTMLCanvasElement>): Point {
    const rect = canvas.getBoundingClientRect();
    let clientX = 0;
    let clientY = 0;

    if ('touches' in e && e.touches.length > 0) {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    } else if ('clientX' in e) {
        clientX = (e as React.MouseEvent<HTMLCanvasElement>).clientX;
        clientY = (e as React.MouseEvent<HTMLCanvasElement>).clientY;
    }

    return {
        x: clientX - rect.left,
        y: clientY - rect.top,
    };
}

function drawStrokes(ctx: CanvasRenderingContext2D, strokes: Point[][], scale: number) {
    ctx.save();
    ctx.strokeStyle = '#0f172a';
    ctx.fillStyle = '#0f172a';
    ctx.lineWidth = 3.0 * scale;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    for (const stroke of strokes) {
        if (stroke.length === 0) continue;
        if (stroke.length === 1) {
            ctx.beginPath();
            ctx.arc(stroke[0].x * scale, stroke[0].y * scale, 1.5 * scale, 0, Math.PI * 2);
            ctx.fill();
            continue;
        }

        ctx.beginPath();
        ctx.moveTo(stroke[0].x * scale, stroke[0].y * scale);

        if (stroke.length === 2) {
            ctx.lineTo(stroke[1].x * scale, stroke[1].y * scale);
            ctx.stroke();
            continue;
        }

        for (let i = 1; i < stroke.length - 1; i++) {
            const midX = ((stroke[i].x + stroke[i + 1].x) / 2) * scale;
            const midY = ((stroke[i].y + stroke[i + 1].y) / 2) * scale;
            ctx.quadraticCurveTo(stroke[i].x * scale, stroke[i].y * scale, midX, midY);
        }

        const last = stroke[stroke.length - 1];
        ctx.lineTo(last.x * scale, last.y * scale);
        ctx.stroke();
    }
    ctx.restore();
}

function exportCroppedSignature(strokes: Point[][]): string | null {
    if (strokes.length === 0) return null;

    const strokeBuffer = 4;
    const padding = 16;
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;
    let totalPoints = 0;

    for (const stroke of strokes) {
        for (const p of stroke) {
            totalPoints++;
            if (p.x < minX) minX = p.x;
            if (p.y < minY) minY = p.y;
            if (p.x > maxX) maxX = p.x;
            if (p.y > maxY) maxY = p.y;
        }
    }

    if (totalPoints === 0 || !Number.isFinite(minX)) return null;

    minX = Math.max(0, minX - strokeBuffer);
    minY = Math.max(0, minY - strokeBuffer);
    maxX = maxX + strokeBuffer;
    maxY = maxY + strokeBuffer;

    const rawWidth = Math.max(40, maxX - minX);
    const rawHeight = Math.max(30, maxY - minY);

    // Supersample at 3x for ultra-sharp high-DPI export
    const exportScale = 3;
    const outCanvas = document.createElement('canvas');
    outCanvas.width = Math.round((rawWidth + padding * 2) * exportScale);
    outCanvas.height = Math.round((rawHeight + padding * 2) * exportScale);

    const outCtx = outCanvas.getContext('2d');
    if (!outCtx) return null;

    outCtx.imageSmoothingEnabled = true;
    outCtx.imageSmoothingQuality = 'high';
    outCtx.save();
    outCtx.translate(Math.round((padding - minX) * exportScale), Math.round((padding - minY) * exportScale));
    drawStrokes(outCtx, strokes, exportScale);
    outCtx.restore();

    return outCanvas.toDataURL('image/png');
}

export function SignaturePad({ onChange, className }: SignaturePadProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const strokesRef = useRef<Point[][]>([]);
    const currentStrokeRef = useRef<Point[]>([]);
    const isDrawingRef = useRef(false);
    const [strokeCount, setStrokeCount] = useState(0);

    const getDpr = () => {
        const canvas = canvasRef.current;
        if (!canvas || !canvas.clientWidth) return Math.max(window.devicePixelRatio || 1, 2.5);
        return canvas.width / canvas.clientWidth;
    };

    const redrawCanvas = () => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        drawStrokes(ctx, strokesRef.current, getDpr());
    };

    const setupCanvasResolution = () => {
        const canvas = canvasRef.current;
        const container = containerRef.current;
        if (!canvas || !container) return;

        const cssWidth = Math.round(container.clientWidth) || 500;
        const cssHeight = 180;
        const dpr = Math.max(window.devicePixelRatio || 1, 2.5);

        const targetWidth = Math.round(cssWidth * dpr);
        const targetHeight = Math.round(cssHeight * dpr);

        if (canvas.width === targetWidth && canvas.height === targetHeight) {
            return;
        }

        canvas.width = targetWidth;
        canvas.height = targetHeight;

        redrawCanvas();
    };

    useEffect(() => {
        setupCanvasResolution();

        const container = containerRef.current;
        if (!container) return;

        let prevWidth = Math.round(container.clientWidth);
        const observer = new ResizeObserver((entries) => {
            for (const entry of entries) {
                const width = Math.round(entry.contentRect.width);
                if (width > 0 && Math.abs(width - prevWidth) >= 4) {
                    prevWidth = width;
                    setupCanvasResolution();
                }
            }
        });
        observer.observe(container);

        return () => observer.disconnect();
    }, []);

    const handleStart = (e: React.MouseEvent<HTMLCanvasElement> | React.TouchEvent<HTMLCanvasElement>) => {
        e.preventDefault();
        const canvas = canvasRef.current;
        if (!canvas) return;

        const pt = getCanvasCoords(canvas, e);
        isDrawingRef.current = true;
        currentStrokeRef.current = [pt];

        const ctx = canvas.getContext('2d');
        if (ctx) {
            const dpr = getDpr();
            ctx.save();
            ctx.fillStyle = '#0f172a';
            ctx.beginPath();
            ctx.arc(pt.x * dpr, pt.y * dpr, 1.5 * dpr, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        }
    };

    const handleMove = (e: React.MouseEvent<HTMLCanvasElement> | React.TouchEvent<HTMLCanvasElement>) => {
        if (!isDrawingRef.current) return;
        e.preventDefault();

        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const pt = getCanvasCoords(canvas, e);
        const stroke = currentStrokeRef.current;
        stroke.push(pt);

        const dpr = getDpr();

        if (stroke.length === 2) {
            ctx.save();
            ctx.strokeStyle = '#0f172a';
            ctx.lineWidth = 3.0 * dpr;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            ctx.moveTo(stroke[0].x * dpr, stroke[0].y * dpr);
            ctx.lineTo(stroke[1].x * dpr, stroke[1].y * dpr);
            ctx.stroke();
            ctx.restore();
        } else if (stroke.length >= 3) {
            const p0 = stroke[stroke.length - 3];
            const p1 = stroke[stroke.length - 2];
            const p2 = stroke[stroke.length - 1];

            const mid1X = ((p0.x + p1.x) / 2) * dpr;
            const mid1Y = ((p0.y + p1.y) / 2) * dpr;
            const mid2X = ((p1.x + p2.x) / 2) * dpr;
            const mid2Y = ((p1.y + p2.y) / 2) * dpr;

            ctx.save();
            ctx.strokeStyle = '#0f172a';
            ctx.lineWidth = 3.0 * dpr;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            ctx.moveTo(mid1X, mid1Y);
            ctx.quadraticCurveTo(p1.x * dpr, p1.y * dpr, mid2X, mid2Y);
            ctx.stroke();
            ctx.restore();
        }
    };

    const handleEnd = (e: React.MouseEvent<HTMLCanvasElement> | React.TouchEvent<HTMLCanvasElement>) => {
        if (!isDrawingRef.current) return;
        e.preventDefault();
        isDrawingRef.current = false;

        if (currentStrokeRef.current.length > 0) {
            strokesRef.current.push([...currentStrokeRef.current]);
            currentStrokeRef.current = [];
            setStrokeCount(strokesRef.current.length);

            // Re-render whole canvas cleanly to ensure smooth joining
            redrawCanvas();

            // Export high-DPI trimmed transparent signature
            const exported = exportCroppedSignature(strokesRef.current);
            onChange(exported);
        }
    };

    const handleClear = () => {
        strokesRef.current = [];
        currentStrokeRef.current = [];
        isDrawingRef.current = false;
        setStrokeCount(0);
        redrawCanvas();
        onChange(null);
    };

    const handleUndo = () => {
        if (strokesRef.current.length === 0) return;
        strokesRef.current.pop();
        setStrokeCount(strokesRef.current.length);
        redrawCanvas();
        const exported = exportCroppedSignature(strokesRef.current);
        onChange(exported);
    };

    return (
        <div className={`flex flex-col gap-2 ${className ?? ''}`}>
            <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-foreground">
                    Goreskan tanda tangan pada area di bawah:
                </span>
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={strokeCount === 0}
                        className="h-7 text-xs text-muted-foreground hover:text-foreground gap-1"
                        onClick={handleUndo}
                    >
                        <Undo2 className="size-3" />
                        Urungkan
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={strokeCount === 0}
                        className="h-7 text-xs text-muted-foreground hover:text-foreground gap-1"
                        onClick={handleClear}
                    >
                        <RotateCcw className="size-3" />
                        Bersihkan
                    </Button>
                </div>
            </div>

            <div
                ref={containerRef}
                className="relative w-full max-w-full h-[180px] overflow-hidden rounded-md border-2 border-slate-300 dark:border-slate-700 bg-white shadow-inner"
            >
                {/* Visual guideline */}
                <div className="pointer-events-none absolute bottom-9 left-6 right-6 flex items-center justify-end border-b border-dashed border-slate-300">
                    <span className="pb-0.5 text-[10px] text-slate-400 select-none">
                        Garis tanda tangan
                    </span>
                </div>

                <canvas
                    ref={canvasRef}
                    className="relative block w-full h-full cursor-crosshair touch-none select-none"
                    onMouseDown={handleStart}
                    onMouseMove={handleMove}
                    onMouseUp={handleEnd}
                    onMouseLeave={handleEnd}
                    onTouchStart={handleStart}
                    onTouchMove={handleMove}
                    onTouchEnd={handleEnd}
                />
            </div>

            <p className="text-[11px] text-muted-foreground">
                Gunakan mouse, stylus, atau jari pada layar sentuh. Goresan diproses dengan resolusi tinggi (bebas pixel pecah) dan otomatis tersimpan.
            </p>
        </div>
    );
}
