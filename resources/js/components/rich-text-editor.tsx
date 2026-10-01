import { Editor } from '@tinymce/tinymce-react';
// Type only (erased at build), so it does not affect the side-effect import order below.
import type { Editor as TinyMceEditor } from 'tinymce';
// Self-hosted TinyMCE (GPL) — no API key, no CDN. Import order matters: core first.
import 'tinymce/tinymce.min.js';
import 'tinymce/icons/default/icons.min.js';
import 'tinymce/themes/silver/theme.min.js';
import 'tinymce/models/dom/model.min.js';
import 'tinymce/skins/ui/oxide/skin.min.css';
import 'tinymce/plugins/advlist/plugin.min.js';
import 'tinymce/plugins/autolink/plugin.min.js';
import 'tinymce/plugins/lists/plugin.min.js';
import 'tinymce/plugins/link/plugin.min.js';
import 'tinymce/plugins/charmap/plugin.min.js';
import 'tinymce/plugins/searchreplace/plugin.min.js';
import 'tinymce/plugins/visualblocks/plugin.min.js';
import 'tinymce/plugins/code/plugin.min.js';
import 'tinymce/plugins/table/plugin.min.js';
import 'tinymce/plugins/wordcount/plugin.min.js';
import 'tinymce/plugins/pagebreak/plugin.min.js';
import 'tinymce/plugins/autoresize/plugin.min.js';
import contentCss from 'tinymce/skins/content/default/content.min.css?inline';

/** An A4 page at 96 dpi: the width a document is laid out at before it is scaled to fit. */
const PAGE_WIDTH_PX = 794;

/**
 * Narrow screens (phones): lay the document out at A4 width — as it prints —
 * and scale it down to the editor's width, like a page preview, instead of
 * reflowing the kop & tables into a narrow column. Wide screens are left as is.
 */
function fitPageToWidth(editor: TinyMceEditor): void {
    const doc = editor.getDoc();
    const body = editor.getBody();

    if (!doc || !body) {
        return;
    }

    body.style.removeProperty('zoom');
    body.style.removeProperty('width');
    doc.documentElement.style.removeProperty('overflow-x');
    const available = doc.documentElement.clientWidth;

    if (available >= PAGE_WIDTH_PX) {
        editor.execCommand('mceAutoResize');

        return;
    }

    body.style.width = `${PAGE_WIDTH_PX}px`;
    // Scale the A4 page (with the body's own margins) to the width; a table wider
    // than the page (e.g. a 31-day jadwal) scrolls sideways inside the editor.
    const margins = parseFloat(getComputedStyle(body).marginLeft) + parseFloat(getComputedStyle(body).marginRight);
    const needed = body.getBoundingClientRect().width + margins;
    body.style.zoom = String(Math.min(1, available / needed));
    doc.documentElement.style.overflowX = 'auto';
    editor.execCommand('mceAutoResize');
}

/**
 * A self-hosted, Word-like rich text editor used to fine-tune generated
 * documents before they are saved and exported. GPL licensed, no external
 * scripts.
 */
export function RichTextEditor({
    value,
    onChange,
    disabled = false,
    extraContentStyle = '',
    autoGrow = false,
    fitPage = false,
}: {
    value: string;
    onChange: (html: string) => void;
    disabled?: boolean;
    /** Extra CSS injected into the editing surface (e.g. the document's own styles). */
    extraContentStyle?: string;
    /** Grow the editor to fit the whole document instead of scrolling inside a fixed box. */
    autoGrow?: boolean;
    /** A printable document: on narrow screens show it as a scaled A4 page (see {@link fitPageToWidth}). */
    fitPage?: boolean;
}) {
    return (
        <Editor
            licenseKey="gpl"
            value={value}
            onEditorChange={(html) => onChange(html)}
            disabled={disabled}
            init={{
                skin: false,
                content_css: false,
                relative_urls: false,
                remove_script_host: false,
                convert_urls: false,
                content_style: `${contentCss}
                    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #000; margin: 12px; }
                    table { border-collapse: collapse; }
                    ${extraContentStyle}`,
                // With autoGrow the editor expands to the content height (whole
                // document visible, no inner scrollbar); otherwise a fixed box.
                height: autoGrow ? undefined : 640,
                min_height: autoGrow ? 640 : undefined,
                autoresize_bottom_margin: 24,
                setup: (editor: TinyMceEditor) => {
                    if (!fitPage) {
                        return;
                    }

                    const fit = () => fitPageToWidth(editor);
                    editor.on('init SetContent ResizeWindow', fit);
                },
                menubar: 'edit view insert format table',
                // Phones: no floating table toolbar over the scaled page; the main toolbar scrolls sideways.
                mobile: {
                    table_toolbar: '',
                    toolbar_mode: 'scrolling',
                },
                branding: false,
                promotion: false,
                statusbar: true,
                extended_valid_elements:
                    'svg[*],defs[*],pattern[*],desc[*],metadata[*],g[*],mask[*],path[*],line[*],marker[*],rect[*],circle[*],ellipse[*],polygon[*],polyline[*],linearGradient[*],radialGradient[*],stop[*],text[*],tspan[*],use[*],img[*]',
                custom_elements:
                    '~svg,~defs,~pattern,~desc,~metadata,~g,~mask,~path,~line,~marker,~rect,~circle,~ellipse,~polygon,~polyline,~linearGradient,~radialGradient,~stop,~text,~tspan,~use',
                allow_svg_data_urls: true,
                plugins: [
                    'advlist',
                    'autolink',
                    'lists',
                    'link',
                    'charmap',
                    'searchreplace',
                    'visualblocks',
                    'code',
                    'table',
                    'wordcount',
                    'pagebreak',
                    ...(autoGrow ? ['autoresize'] : []),
                ],
                toolbar:
                    'undo redo | blocks fontfamily fontsize | ' +
                    'bold italic underline strikethrough | forecolor backcolor | ' +
                    'alignleft aligncenter alignright alignjustify | ' +
                    'bullist numlist outdent indent | table | ' +
                    'removeformat pagebreak | searchreplace | code',
            }}
        />
    );
}
