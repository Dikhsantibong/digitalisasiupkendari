<?php

namespace App\Services\Reports;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * Captures what a controller's PDF action would print — the view, its data and
 * the paper orientation — instead of rendering it, so a compiled report can
 * embed exactly the same page (see OperasiPengusahaanBook).
 *
 * While {@see record()} runs, `Pdf::loadView()` (the dompdf facade resolves
 * `dompdf.wrapper` from the container on every call) returns this recorder;
 * `setPaper()` notes the orientation and `stream()` / `download()` return an
 * empty response. The real wrapper is restored afterwards.
 */
class PdfViewRecorder
{
    private ?string $view = null;

    /** @var array<string, mixed> */
    private array $data = [];

    private string $orientation = 'portrait';

    /**
     * Run the action and return what it would print, or null when it printed
     * nothing through Pdf::loadView().
     *
     * @param  Closure(): mixed  $action
     * @return array{view: string, data: array<string, mixed>, orientation: string}|null
     */
    public static function record(Closure $action): ?array
    {
        $recorder = new self;
        App::instance('dompdf.wrapper', $recorder);

        try {
            $action();
        } finally {
            App::forgetInstance('dompdf.wrapper');
        }

        return $recorder->view === null
            ? null
            : ['view' => $recorder->view, 'data' => $recorder->data, 'orientation' => $recorder->orientation];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $mergeData
     */
    public function loadView(string $view, array $data = [], array $mergeData = [], ?string $encoding = null): static
    {
        $this->view = $view;
        $this->data = [...$data, ...$mergeData];

        return $this;
    }

    /**
     * @param  string|array<int, float>  $paper
     */
    public function setPaper(string|array $paper, string $orientation = 'portrait'): static
    {
        $this->orientation = strtolower($orientation) === 'landscape' ? 'landscape' : 'portrait';

        return $this;
    }

    /**
     * @param  array<string, mixed>|string  $attribute
     */
    public function setOption(array|string $attribute, mixed $value = null): static
    {
        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function setOptions(array $options, bool $mergeWithDefaults = false): static
    {
        return $this;
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        return new Response('');
    }

    public function download(string $filename = 'document.pdf'): Response
    {
        return new Response('');
    }

    public function output(array $options = []): string
    {
        return '';
    }
}
