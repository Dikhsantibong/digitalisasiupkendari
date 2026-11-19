<?php

namespace App\Http\Controllers\Logistik;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesLogistikInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Logistik\LogistikFormRows;
use App\Services\Notifications\EventNotifier;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use App\Support\LogistikForms\LogistikForm;
use App\Support\LogistikForms\LogistikForms;
use App\Support\LogistikForms\UnsafeForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every Logistik & Gudang table form ({@see LogistikForms}): Laporan
 * Pendukung, Peralatan/Material/Tools, Kondisi Stok and Unsafe Action &
 * Condition. Rows are saved per unit & month ({@see LogistikFormRows}); before
 * anything is saved the form starts from its default rows. Each section always
 * shows at least its default + blank rows, like the paper form. PDF from
 * resources/views/logistik/input/form-pdf.blade.php, Excel from the page.
 */
class FormController extends Controller
{
    use HandlesLogistikInput;
    use RendersReportPdf;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly LogistikFormRows $formRows,
    ) {}

    public function index(Request $request, string $form): Response
    {
        $definition = $this->definition($form);
        [$units, $unit, $month, $year] = $this->logistikReadTarget($request);

        // Each form has its own page: resources/js/pages/logistik/input/{form}/index.tsx.
        return Inertia::render("logistik/input/{$definition->key()}/index", [
            'form' => $definition->toArray(),
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => $this->logistikFilterOptions($units),
            'rows' => $this->formRows->rows($definition, $unit, $month, $year),
            'summary' => $this->formRows->summary($definition, $unit, $month, $year),
            'has_saved' => $this->formRows->query($definition, $unit, $month, $year)->exists(),
            'can_write' => $request->user()->hasPermissionTo(PermissionName::LogistikInputWrite),
        ]);
    }

    public function store(Request $request, string $form, EventNotifier $events): RedirectResponse
    {
        $definition = $this->definition($form);
        [$unit, $month, $year] = $this->logistikWriteTarget($request);

        // Unsafe Action & Condition temuan also land in the K3 module: tell K3 about the new ones.
        $unsafe = $definition instanceof UnsafeForm;
        $previous = $unsafe ? $this->unsafeKeys($definition, $unit, $month, $year) : [];

        $this->formRows->save($request, $definition, $unit, $month, $year);

        if ($unsafe) {
            $new = [];
            foreach ($this->unsafeKeys($definition, $unit, $month, $year) as $key => $temuan) {
                if (! array_key_exists($key, $previous)) {
                    $new[] = $temuan;
                }
            }
            $events->logistikUnsafeFound($unit, $month, $year, $new, $request->user());
        }

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan {$definition->title()} {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$definition->title()} berhasil disimpan."]);

        return back();
    }

    public function pdf(Request $request, string $form): HttpResponse
    {
        $definition = $this->definition($form);
        [, $unit, $month, $year] = $this->logistikReadTarget($request);
        [$view, $data] = $this->pdfView($definition, $unit, $month, $year);

        $filename = sprintf('%s_%s_%02d_%d.pdf', preg_replace('/[^A-Za-z0-9]+/', '_', $definition->title()), str_replace(' ', '_', $unit->name), $month, $year);

        return $this->streamReportPdf($request, $view, $data, $filename, $definition->orientation());
    }

    /**
     * The PDF view and its data — reusable by the Laporan Logistik.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(LogistikForm $definition, Unit $unit, int $month, int $year): array
    {
        return ['logistik.input.form-pdf', [
            'form' => $definition,
            'unit' => $unit,
            'periodLabel' => Indonesian::monthName($month).' Tahun '.$year,
            'rows' => $this->formRows->rows($definition, $unit, $month, $year, embedImages: true),
            'summary' => $this->formRows->summary($definition, $unit, $month, $year),
            ...JadwalPdf::logos(),
        ]];
    }

    private function definition(string $form): LogistikForm
    {
        $definition = LogistikForms::find($form);
        abort_if($definition === null, 404);

        return $definition;
    }

    /**
     * The saved unsafe temuan of the period keyed by kategori, temuan and
     * lokasi — what tells a new temuan from one saved before (rows are
     * re-created on every save).
     *
     * @return array<string, string> key => temuan
     */
    private function unsafeKeys(LogistikForm $definition, Unit $unit, int $month, int $year): array
    {
        $keys = [];
        foreach ($this->formRows->query($definition, $unit, $month, $year)->get() as $row) {
            $temuan = trim((string) ($row->data['temuan'] ?? ''));
            if ($temuan !== '') {
                $keys[md5(strtolower(implode('|', [$row->data['kategori'] ?? '', $temuan, trim((string) ($row->data['lokasi'] ?? ''))])))] = $temuan;
            }
        }

        return $keys;
    }
}
