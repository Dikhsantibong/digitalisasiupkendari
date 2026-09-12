<?php

namespace App\Http\Controllers\Concerns;

/**
 * Inlines the letterhead logo as a base64 data URI before a report is handed to
 * dompdf, which cannot fetch files over HTTP. Shared by every report/document
 * PDF (HAR, K3, Operasi laporan & Berita Acara) so the logo renders identically.
 *
 * The match is deliberately loose: the rich-text editor rewrites the image's
 * `src` to an absolute URL (e.g. http://host/logo/sidebar-logo.png) once the
 * document has been opened and saved, so a plain string replace on the relative
 * path would miss it. This replaces any src ending in `logo/sidebar-logo.png`.
 */
trait EmbedsReportLogo
{
    protected function embedAssets(string $html): string
    {
        $path = public_path('logo/sidebar-logo.png');

        if (! is_file($path)) {
            return $html;
        }

        $dataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));

        return (string) preg_replace(
            '#src=(["\'])[^"\']*logo/sidebar-logo\.png\1#i',
            'src="'.$dataUri.'"',
            $html,
        );
    }
}
