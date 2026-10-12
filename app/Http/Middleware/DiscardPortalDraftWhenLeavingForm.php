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
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $isRequester = $request->session()->get('portal.actor.role') === 'user';
        $preservesDraft = $this->preservesDraft($request);

        if ($isRequester && $request->session()->has('portal.draft') && ! $preservesDraft) {
            $this->deletePendingFiles($request->session()->get('portal.draft', []));
            $request->session()->forget(['portal.draft', 'portal.completed']);
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

        return in_array($routeName, ['requests.document', 'requests.document.download'], true)
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
