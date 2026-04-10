<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppSettingsRequest;
use App\Models\AppSetting;
use App\Services\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private AppSettingsService $appSettingsService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/settings', [
            'settings' => [
                'app_name' => $this->appSettingsService->appName(),
                'announcement' => AppSetting::query()
                    ->where('key', AppSettingsService::ANNOUNCEMENT_KEY)
                    ->value('value') ?? '',
                'announcement_enabled' => $this->appSettingsService->announcementEnabled(),
                'announcement_type' => $this->appSettingsService->announcementType(),
                'announcement_dismissable' => $this->appSettingsService->announcementDismissable(),
                'announcement_icon' => $this->appSettingsService->announcementIcon(),
                'telemetry_enabled' => $this->appSettingsService->telemetryEnabled(),
                'allocations_enabled' => $this->appSettingsService->allocationsEnabled(),
                'allocations_limit' => $this->appSettingsService->allocationsLimit(),
                'theme' => $this->appSettingsService->theme(),
            ],
            'themes' => $this->appSettingsService->availableThemes(),
        ]);
    }

    public function update(UpdateAppSettingsRequest $request): RedirectResponse
    {
        $this->appSettingsService->setAppName($request->validated('app_name'));
        $this->appSettingsService->setAnnouncement($request->validated('announcement'));
        $this->appSettingsService->setAnnouncementEnabled($request->boolean('announcement_enabled'));
        $this->appSettingsService->setAnnouncementType($request->validated('announcement_type') ?? 'information');
        $this->appSettingsService->setAnnouncementDismissable($request->boolean('announcement_dismissable'));
        $this->appSettingsService->setAnnouncementIcon(
            $request->validated('announcement_icon') ??
                $this->appSettingsService->announcementIcon(),
        );
        $this->appSettingsService->setTelemetryEnabled($request->boolean('telemetry_enabled'));
        $this->appSettingsService->setAllocationsEnabled($request->boolean('allocations_enabled'));
        $this->appSettingsService->setAllocationsLimit((int) $request->validated('allocations_limit', 0));

        if ($request->has('theme')) {
            $this->appSettingsService->setTheme($request->validated('theme') ?? 'magma');
        }

        return Redirect::back()->with('success', 'Settings updated.');
    }
}
