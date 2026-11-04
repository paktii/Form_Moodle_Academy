<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DiscardPortalDraftWhenLeavingForm
{
    private const FORM_ROUTES = [
        'requests.form',
        'requests.save-step',
        'requests.clear-step',
        'website-requests.form',
        'website-requests.save-step',
        'website-requests.clear-step',
        'website-contact-change-requests.form',
        'website-contact-change-requests.save-step',
        'website-contact-change-requests.clear-step',
        'emeeting-requests.form',
        'emeeting-requests.save-step',
        'emeeting-requests.clear-step',
        'website-storage-requests.form',
        'website-storage-requests.save-step',
        'website-storage-requests.clear-step',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $role = $request->session()->get('portal.actor.role');
        $tabRole = $request->input('_portal_role');
        if (config('course-workflow.dev_role_switcher')
            && is_string($tabRole)
            && in_array($tabRole, $request->session()->get('portal.available_roles', []), true)) {
            $role = $tabRole;
        }
        $isRequester = $role === 'user';
        $preservesDraft = $this->preservesDraft($request);

        if ($isRequester && ! $preservesDraft) {
            foreach (['portal.draft', 'portal.website_draft', 'portal.website_contact_change_draft', 'portal.emeeting_draft', 'portal.website_storage_draft'] as $draftKey) {
                if ($request->session()->has($draftKey)) {
                    $this->deletePendingFiles($request->session()->get($draftKey, []));
                }
            }
            $request->session()->forget(['portal.draft', 'portal.completed', 'portal.website_draft', 'portal.website_completed', 'portal.website_contact_change_draft', 'portal.website_contact_change_completed', 'portal.emeeting_draft', 'portal.emeeting_completed', 'portal.website_storage_draft', 'portal.website_storage_completed']);
        }

        $response = $next($request);

        if ($isRequester && $preservesDraft && $request->isMethod('GET')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    private function preservesDraft(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (in_array($routeName, self::FORM_ROUTES, true)) {
            return true;
        }

        return in_array($routeName, ['requests.document', 'requests.document.download', 'website-requests.document', 'website-requests.document.download', 'website-contact-change-requests.document', 'website-contact-change-requests.document.download', 'emeeting-requests.document', 'emeeting-requests.document.download', 'website-storage-requests.document', 'website-storage-requests.document.download'], true)
            && $request->route('id') === 'draft';
    }

    private function deletePendingFiles(array $draft): void
    {
        $additionalFiles = isset($draft['additional_files']) && is_array($draft['additional_files'])
            ? $draft['additional_files']
            : (isset($draft['additional_path']) ? [$draft] : []);

        foreach ($additionalFiles as $file) {
            $this->deletePendingFile($file, 'additional');
        }

        $this->deletePendingFile($draft, 'signed');
    }

    private function deletePendingFile(array $file, string $prefix): void
    {
        if (($file[$prefix.'_pending'] ?? false) && isset($file[$prefix.'_path'])) {
            Storage::disk('local')->delete($file[$prefix.'_path']);
        }
    }
}
