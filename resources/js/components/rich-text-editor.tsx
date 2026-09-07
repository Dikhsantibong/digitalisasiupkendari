import { Editor } from '@tinymce/tinymce-react';
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
import contentCss from 'tinymce/skins/content/default/content.min.css?inline';

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
}: {
    value: string;
    onChange: (html: string) => void;
    disabled?: boolean;
    /** Extra CSS injected into the editing surface (e.g. the document's own styles). */
    extraContentStyle?: string;
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
                content_style: `${contentCss}
                    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #000; margin: 12px; }
                    table { border-collapse: collapse; }
                    ${extraContentStyle}`,
                height: 640,
                menubar: 'edit view insert format table',
                branding: false,
                promotion: false,
                statusbar: true,
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
