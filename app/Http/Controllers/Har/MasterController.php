<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\ManagesMasterResources;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\Har\Master\HarMasterRegistry;
use App\Services\Master\MasterRegistry;

/**
 * The generic CRUD screen for pemeliharaan master data (types, cycles, WO
 * statuses, work groups, SR categories). Shares the engine in
 * {@see ManagesMasterResources}; only names the registry, permissions, and page.
 */
class MasterController extends Controller
{
    use ManagesMasterResources;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly HarMasterRegistry $registry,
    ) {}

    protected function masterRegistry(): MasterRegistry
    {
        return $this->registry;
    }

    protected function masterViewPermission(): PermissionName
    {
        return PermissionName::HarMasterViewAny;
    }

    protected function masterManagePermission(): PermissionName
    {
        return PermissionName::HarMasterManage;
    }

    protected function masterPage(): string
    {
        return 'har/master/index';
    }
}
