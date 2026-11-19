<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Logistik\LogistikFormRows;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use App\Support\LogistikForms\InputLogistikForm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akses 2 — Input Logistik of Pemeliharaan (user, 2026-11-19): the stock of
 * tools & material with Dasar Stok and Nomor Surat / Kontrak
 * ({@see InputLogistikForm}), kept by the TL Pemeliharaan / TL Ophar and the
 * Staf Pemeliharaan (har.pengusahaan.view / .write) — not by the Project
 * Leader or the Koordinator. Same table engine & page as the Logistik forms.
 */
class InputLogistikController extends Controller
{
    use RendersReportPdf;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly LogistikFormRows $formRows,
    ) {}

    public function index(Request $request): Response
    {
        $form = new InputLogistikForm;
        [$units, $unit, $month, $year] = $this->readTarget($request);
        $now = Carbon::now();

        return Inertia::render('pengusahaan/har/input-logistik/index', [
            'form' => $form->toArray(),
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values()->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'rows' => $this->formRows->rows($form, $unit, $month, $year),
            'summary' => $this->formRows->summary($form, $unit, $month, $year),
            'has_saved' => $this->formRows->query($form, $unit, $month, $year)->exists(),
            'can_write' => $request->user()->hasPermissionTo(PermissionName::HarPengusahaanWrite),
            'urls' => [
                'index' => route('har.pengusahaan.input-logistik.index'),
                'store' => route('har.pengusahaan.input-logistik.store'),
                'pdf' => route('har.pengusahaan.input-logistik.pdf'),
                'back' => route('har.pengusahaan.index', 'input'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarPengusahaanWrite), 403);

        $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);
        $month = (int) $request->integer('month');
        $year = (int) $request->integer('year');

        $form = new InputLogistikForm;
        $this->formRows->save($request, $form, $unit, $month, $year);

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan {$form->title()} {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$form->title()} berhasil disimpan."]);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        $form = new InputLogistikForm;
        [, $unit, $month, $year] = $this->readTarget($request);

        return $this->streamReportPdf($request, 'logistik.input.form-pdf', [
            'form' => $form,
            'unit' => $unit,
            'periodLabel' => Indonesian::monthName($month).' Tahun '.$year,
            'rows' => $this->formRows->rows($form, $unit, $month, $year, embedImages: true),
            'summary' => $this->formRows->summary($form, $unit, $month, $year),
            ...JadwalPdf::logos(),
        ], sprintf('Input_Logistik_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), $month, $year), $form->orientation());
    }

    /**
     * Gate a read (page or PDF) and resolve the unit & period.
     *
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    private function readTarget(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarPengusahaanView), 403);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail((int) ($request->integer('unit_id') ?: $units->first()->id));
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();

        return [
            $units,
            $unit,
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            (int) ($request->integer('year') ?: $now->year),
        ];
    }
}
