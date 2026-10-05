<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentDownloadController extends Controller
{
    public function __invoke(Attachment $attachment, AttachmentService $attachmentService, Request $request): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $attachmentService->download($attachment);
    }
}
