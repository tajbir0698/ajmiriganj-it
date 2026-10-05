<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    protected const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    protected const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

    protected const MAX_IMAGE_WIDTH = 1600;

    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * Store an uploaded file to the private disk and create an Attachment record.
     */
    public function store(UploadedFile $file, Model $attachable, ?User $user = null): Attachment
    {
        $this->validate($file);

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('%s_%s.%s', date('Ymd_His'), Str::random(12), strtolower($extension));
        $relativeDir = 'attachments/'.strtolower(class_basename($attachable));
        $relativePath = $relativeDir.'/'.$filename;

        if (str_starts_with($mime, 'image/')) {
            $image = $this->imageManager->decodePath($file->getRealPath());
            if ($image->width() > self::MAX_IMAGE_WIDTH) {
                $image->scale(width: self::MAX_IMAGE_WIDTH);
            }
            $encoded = (string) $image->encode();
            Storage::disk('private')->put($relativePath, $encoded);
            $size = strlen($encoded);
        } else {
            Storage::disk('private')->putFileAs($relativeDir, $file, $filename);
            $size = $file->getSize();
        }

        return Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'file_path' => $relativePath,
            'original_name' => $originalName,
            'mime' => $mime,
            'size' => $size,
            'uploaded_by' => $user?->id ?? auth()->id(),
        ]);
    }

    /**
     * Store multiple files.
     *
     * @param  array<UploadedFile|string>  $files
     * @return array<Attachment>
     */
    public function storeMany(array $files, Model $attachable, ?User $user = null): array
    {
        $attachments = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $attachments[] = $this->store($file, $attachable, $user);
            } elseif (is_string($file)) {
                // When Filament uploads to temporary storage
                $tempPath = Storage::disk('private')->exists($file)
                    ? Storage::disk('private')->path($file)
                    : (Storage::disk('public')->exists($file) ? Storage::disk('public')->path($file) : $file);

                if (file_exists($tempPath)) {
                    $uploaded = new UploadedFile(
                        $tempPath,
                        basename($file),
                        mime_content_type($tempPath) ?: null,
                        null,
                        true
                    );
                    $attachments[] = $this->store($uploaded, $attachable, $user);
                }
            }
        }

        return $attachments;
    }

    /**
     * Validate file type and size.
     */
    public function validate(UploadedFile $file): void
    {
        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException("File type [{$mime}] is not allowed. Only JPG, PNG, WebP, and PDF are supported.");
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new InvalidArgumentException('File size exceeds the maximum allowed limit of 5 MB.');
        }
    }

    /**
     * Stream an attachment from the private disk.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        if (! Storage::disk('private')->exists($attachment->file_path)) {
            abort(404, 'Attachment file not found on storage.');
        }

        return Storage::disk('private')->download($attachment->file_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
        ]);
    }
}
