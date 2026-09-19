<?php

namespace App\Services\Reports;

use App\Http\Controllers\Concerns\EmbedsReportLogo;

/**
 * Turns a standalone PDF view (a full HTML document with its own `<style>`)
 * into a fragment that can live inside a larger editable report: the `<body>`
 * content plus its CSS scoped under one class, so several such fragments —
 * each with its own `.kop`, `table`, `body` rules — coexist in one document
 * (rich-text editor and dompdf alike). `@page` rules are dropped (the report
 * sets the page), and the letterhead logos inlined as data URIs go back to
 * their `/logo/...` paths so the saved document stays small; the PDF export
 * re-inlines them ({@see EmbedsReportLogo}).
 */
class ScopedHtmlFragment
{
    /** Logos the report PDF re-embeds from their `/logo/...` path. */
    private const LOGOS = ['sidebar-logo.png', 'logo.png', 'mkp.jpg', 'k3.png'];

    /** @var array<string, string>|null md5(base64 payload) => public path */
    private ?array $logoPaths = null;

    /**
     * @return array{body: string, css: string}
     */
    public function extract(string $html, string $scopeClass): array
    {
        preg_match_all('#<style[^>]*>(.*?)</style>#is', $html, $styles);
        $css = $this->scopeCss(implode("\n", $styles[1]), '.'.$scopeClass);

        $body = preg_match('#<body[^>]*>(.*)</body>#is', $html, $match) ? $match[1] : $html;
        $body = (string) preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $body);

        return ['body' => $this->unInlineLogos(trim($body)), 'css' => $css];
    }

    /**
     * Prefix every selector with the scope; `html`/`body` become the scope itself.
     */
    public function scopeCss(string $css, string $scope): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $css = (string) preg_replace('#@page[^{]*\{[^}]*\}#i', '', $css);

        return (string) preg_replace_callback('#([^{}]+)\{([^{}]*)\}#', function (array $rule) use ($scope): string {
            $selectors = array_map(function (string $selector) use ($scope): string {
                $selector = trim($selector);
                if (in_array($selector, ['html', 'body'], true)) {
                    return $scope;
                }
                if ($selector === '*') {
                    return "{$scope}, {$scope} *";
                }

                return $scope.' '.(string) preg_replace('#^(html|body)\s+#i', '', $selector);
            }, explode(',', $rule[1]));

            return implode(', ', $selectors).' {'.trim($rule[2]).'}'."\n";
        }, $css);
    }

    private function unInlineLogos(string $html): string
    {
        $this->logoPaths ??= collect(self::LOGOS)
            ->filter(fn (string $file): bool => is_file(public_path("logo/{$file}")))
            ->mapWithKeys(fn (string $file): array => [md5(base64_encode((string) file_get_contents(public_path("logo/{$file}")))) => "/logo/{$file}"])
            ->all();

        return (string) preg_replace_callback(
            '#data:image/[\w.+-]+;base64,([A-Za-z0-9+/=]+)#',
            fn (array $match): string => $this->logoPaths[md5($match[1])] ?? $match[0],
            $html,
        );
    }
}
