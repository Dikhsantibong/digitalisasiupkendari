<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Inertia\DeferProp;
use Inertia\Inertia;

/**
 * A report document page (DocumentEditor) opens at once: its heavy body —
 * built from every input of the month — loads right after as one deferred
 * group, and is built a single time for all of its props.
 */
trait DefersReportDocument
{
    /** The deferred group the client (DocumentEditor) waits for. */
    public const DOCUMENT_GROUP = 'document';

    /**
     * @param  Closure(): array{document_number: string, content: string, content_styles: string, letterhead: string, grid: array<string, mixed>}  $document
     * @return array<string, DeferProp>
     */
    protected function deferredDocument(Closure $document): array
    {
        $built = null;
        $resolve = function (string $key) use (&$built, $document): mixed {
            $built ??= $document();

            return $built[$key];
        };

        $props = [];
        foreach (['document_number', 'content', 'content_styles', 'letterhead', 'grid'] as $key) {
            $props[$key] = Inertia::defer(fn (): mixed => $resolve($key), self::DOCUMENT_GROUP);
        }

        return $props;
    }
}
