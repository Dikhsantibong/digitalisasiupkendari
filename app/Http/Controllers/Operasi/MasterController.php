<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\ManagesMasterResources;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\Master\MasterRegistry;
use App\Services\Operasi\Master\OperasiMasterRegistry;

/**
 * The generic CRUD screen for every operasi master (feeders, tanks, lubricants,
 * calibration factors, status codes). The shared engine lives in
 * {@see ManagesMasterResources}; this controller only names the registry,
 * permissions, and page.
 */
class MasterController extends Controller
{
    use ManagesMasterResources;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly OperasiMasterRegistry $registry,
    ) {}

    protected function masterRegistry(): MasterRegistry
    {
        return $this->registry;
    }

    protected function masterViewPermission(): PermissionName
    {
        return PermissionName::OperasiMasterViewAny;
    }

    protected function masterManagePermission(): PermissionName
    {
        return PermissionName::OperasiMasterManage;
    }

    protected function masterPage(): string
    {
        return 'operasi/master/index';
    }
}
