<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocumentUploadService
{
    /** @param list<UploadedFile> $files
     * @return list<string> Duplicate warnings; duplicates are still stored.
     */
    public function upload(array $files, int $documentTypeId, User $uploader): array
    {
        $disk = Storage::disk('documents');
        $paths = [];
        try {
            return DB::transaction(function () use ($files, $documentTypeId, $uploader, $disk, &$paths) {
                $warnings = [];
                foreach ($files as $file) {
                    $checksum = hash_file('sha256', $file->getRealPath());
                    if ($checksum === false) {
                        throw new RuntimeException('Could not checksum the uploaded file.');
                    }
                    $name = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 255);
                    if (DocumentFile::where('role', 'original')->where('sha256', $checksum)->exists()) {
                        $warnings[] = $name.': an identical file already exists. This copy was uploaded too.';
                    }
                    $mime = $file->getMimeType();
                    $extension = match ($mime) {
                        'application/pdf' => 'pdf',
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        default => throw new RuntimeException('Unsupported file content.'),
                    };
                    $directory = (string) Str::uuid();
                    $filename = Str::uuid().'.'.$extension;
                    $path = $directory.'/'.$filename;
                    // Track before writing so a partial storage write is cleaned up too.
                    $paths[] = $path;
                    if ($disk->putFileAs($directory, $file, $filename, ['visibility' => 'private']) !== $path) {
                        throw new RuntimeException('Could not store the uploaded file.');
                    }
                    $document = Document::create(['document_type_id' => $documentTypeId, 'uploaded_by' => $uploader->id, 'status' => 'inbox']);
                    $document->files()->create([
                        'role' => 'original', 'disk' => 'documents', 'path' => $path,
                        'original_name' => $name, 'mime' => $mime, 'size' => $file->getSize(),
                        'sha256' => $checksum, 'sort_order' => 0,
                    ]);
                }

                return $warnings;
            });
        } catch (Throwable $exception) {
            foreach ($paths as $path) {
                try {
                    if (! $disk->delete($path)) {
                        report(new RuntimeException('Failed to clean up an unsuccessful archive upload.'));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            throw $exception;
        }
    }
}
