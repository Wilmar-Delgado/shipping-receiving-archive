<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class UploadDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'files' => ['required', 'array', 'min:1', 'max:'.config('archive.upload_max_files')],
            'files.*' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(config('archive.upload_max_kb')), 'extensions:pdf,jpg,jpeg,png', function ($attribute, $file, $fail) {
                if ($file instanceof UploadedFile && $file->getSize() === 0) {
                    $fail('Empty files cannot be uploaded.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Choose at least one PDF, JPG, or PNG file.',
            'files.max' => 'Upload no more than '.config('archive.upload_max_files').' files at a time.',
            'files.*.uploaded' => 'A file could not be uploaded. Check its size and try again.',
            'files.*.max' => 'Each file must be no larger than '.(config('archive.upload_max_kb') / 1024).' MB.',
            'files.*.mimes' => 'Only PDF, JPG, and PNG files are supported.',
            'files.*.extensions' => 'Files must have a PDF, JPG, JPEG, or PNG extension.',
        ];
    }
}
