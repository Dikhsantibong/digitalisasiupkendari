<?php

namespace App\Http\Controllers;

use App\Services\Reports\ApprovalInbox;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Persetujuan: everything that waits for the user's decision — reports at
 * their step of the workflow (Koordinator / Team Leader / Manager UL), the
 * operators' pengajuan (e.g. the Project Leader) — what they signed, and the
 * Laporan Project they sign "Mengetahui" in. Open to accounts that approve
 * something ({@see ApprovalInbox::applies()}).
 */
class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalInbox $inbox) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(ApprovalInbox::applies($user), 403);

        return Inertia::render('laporan/persetujuan', [
            'employee' => $user->employee?->only(['name', 'position']),
            'queue' => $this->inbox->queue($user),
            'requests' => $this->inbox->requests($user),
            'history' => $this->inbox->history($user),
            'acknowledgements' => $this->inbox->acknowledgements($user),
            'decides_requests' => ApprovalInbox::decidesRequests($user),
        ]);
    }
}
