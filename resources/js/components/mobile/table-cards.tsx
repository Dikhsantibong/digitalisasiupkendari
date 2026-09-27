import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

/**
 * Phone layout for pages whose inputs are hand-built <table>s: when enabled,
 * every table inside is shown as one card per row (see `.mobile-cards` in
 * app.css) — no horizontal table. Each cell gets its column label from the
 * table header (colspan / rowspan headers are joined, e.g. "APD · Layak"), and
 * the inputs, selects and buttons in the cells stay exactly the same elements,
 * so the page's behaviour is untouched. A table opts out with `data-keep-table`.
 * Disabled (desktop, tablet) it renders its children as they are.
 */
export function MobileTableCards({ enabled, children }: { enabled: boolean; children: ReactNode }) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const root = ref.current;

        if (!enabled || !root) {
            return;
        }

        let frame = 0;
        const label = () => {
            frame = 0;
            root.querySelectorAll('table:not([data-keep-table])').forEach((table) => labelTable(table as HTMLTableElement));
        };
        const schedule = () => {
            if (!frame) {
                frame = requestAnimationFrame(label);
            }
        };

        label();
        const observer = new MutationObserver(schedule);
        observer.observe(root, { childList: true, subtree: true });

        return () => {
            observer.disconnect();
            cancelAnimationFrame(frame);
            root.querySelectorAll('table[data-mc]').forEach((table) => table.removeAttribute('data-mc'));
        };
    }, [enabled]);

    return (
        <div ref={ref} className={enabled ? 'mobile-cards contents' : 'contents'}>
            {children}
        </div>
    );
}

/** Column labels of a table from its <thead> (or first header row), honouring colspan & rowspan. */
function columnLabels(table: HTMLTableElement): string[] {
    const headRows = Array.from(table.tHead?.rows ?? []);
    const grid: string[][] = [];

    headRows.forEach((row, r) => {
        grid[r] ??= [];
        let c = 0;

        Array.from(row.cells).forEach((cell) => {
            while (grid[r][c] !== undefined) {
                c++;
            }

            const text = (cell.textContent ?? '').replace(/\s+/g, ' ').trim();

            for (let dr = 0; dr < cell.rowSpan; dr++) {
                grid[r + dr] ??= [];

                for (let dc = 0; dc < cell.colSpan; dc++) {
                    grid[r + dr][c + dc] = dr === 0 ? text : '\u0000';
                }
            }

            c += cell.colSpan;
        });
    });

    const width = Math.max(0, ...grid.map((row) => row.length));

    return Array.from({ length: width }, (_, c) => {
        const parts: string[] = [];

        grid.forEach((row) => {
            const text = row[c];

            if (text && text !== '\u0000' && parts[parts.length - 1] !== text) {
                parts.push(text);
            }
        });

        return parts.join(' · ');
    });
}

function labelTable(table: HTMLTableElement) {
    const labels = columnLabels(table);
    const width = labels.length;

    Array.from(table.tBodies)
        .flatMap((body) => Array.from(body.rows))
        .concat(Array.from(table.tFoot?.rows ?? []))
        .forEach((row) => {
            const cells = Array.from(row.cells);
            let c = 0;
            let visible = 0;

            cells.forEach((cell) => {
                const text = cell.colSpan > 1 ? '' : (labels[c] ?? '');

                if (cell.getAttribute('data-label') !== text) {
                    cell.setAttribute('data-label', text);
                }

                if (cell.textContent?.trim() || cell.querySelector('input, select, textarea, button, img')) {
                    visible++;
                }

                c += cell.colSpan;
            });

            // A single cell spanning the table (group title, empty state, total) reads as a heading.
            const isSection = cells.length <= 2 && width > 2 && cells.some((cell) => cell.colSpan >= width - 1);

            row.toggleAttribute('data-mc-section', isSection);
            row.toggleAttribute('data-mc-empty', visible === 0);
        });

    if (!table.hasAttribute('data-mc')) {
        table.setAttribute('data-mc', '');
    }
}
