import { useEffect, useRef } from 'react';
import contentCss from 'tinymce/skins/content/default/content.min.css?inline';

/** An A4 page at 96 dpi: the width a document is laid out at before it is scaled to fit. */
const PAGE_WIDTH_PX = 794;

/**
 * The document as it reads — the same styling as the rich text editor, but a
 * plain iframe: no editor to load, so a long report (hundreds of KB of HTML)
 * opens at once. Narrow screens get the A4 page scaled to the width, like the
 * editor's `fitPage`.
 */
export function DocumentReadView({
    html,
    contentStyles,
}: {
    html: string;
    contentStyles: string;
}) {
    const frame = useRef<HTMLIFrameElement>(null);

    const fit = () => {
        const doc = frame.current?.contentDocument;

        if (!frame.current || !doc?.body) {
            return;
        }

        const body = doc.body;
        body.style.removeProperty('zoom');
        body.style.removeProperty('width');
        const available = doc.documentElement.clientWidth;

        if (available < PAGE_WIDTH_PX) {
            body.style.width = `${PAGE_WIDTH_PX}px`;
            const style = getComputedStyle(body);
            const needed =
                body.getBoundingClientRect().width +
                parseFloat(style.marginLeft) +
                parseFloat(style.marginRight);
            body.style.zoom = String(Math.min(1, available / needed));
            doc.documentElement.style.overflowX = 'auto';
        }

        frame.current.style.height = `${doc.documentElement.scrollHeight + 24}px`;
    };

    useEffect(() => {
        window.addEventListener('resize', fit);

        return () => window.removeEventListener('resize', fit);
    });

    const srcDoc =`<!doctype html><html><head><meta charset="utf-8"><style>${contentCss}
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #000; margin: 12px; }
        table { border-collapse: collapse; }
        ${contentStyles}</style></head><body>${html}</body></html>`;

    return (
        <iframe
            ref={frame}
            title="Isi dokumen"
            srcDoc={srcDoc}
            // Same origin (to measure the height) but no scripts in the document.
            sandbox="allow-same-origin"
            onLoad={fit}
            className="block min-h-[640px] w-full rounded-sm bg-white"
        />
    );
}
