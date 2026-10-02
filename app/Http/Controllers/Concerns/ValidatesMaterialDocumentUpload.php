<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Validación de documentos de material (actividad / biblioteca RAP).
 * Misma lógica que entregas del aprendiz en ActividadController (extensión + MIME, SQL especial).
 */
trait ValidatesMaterialDocumentUpload
{
    protected function materialDocumentoMaxKilobytes(): int
    {
        return 51200;
    }

    /**
     * @return array<string, string>
     */
    protected function materialDocumentoValidationMessages(): array
    {
        return [
            'documento.max' => 'El archivo no puede superar los 50 MB.',
        ];
    }

    /** @var list<string> */
    protected function materialDocumentoAllowedExtensions(): array
    {
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'sql', 'zip', 'rar'];
    }

    protected function materialDocumentoAllowedExtensionsMessage(): string
    {
        return 'Tipo de archivo no permitido. Solo se permiten: PDF, Word, Excel, PowerPoint, SQL, ZIP o RAR.';
    }

    /**
     * MIME types válidos para PowerPoint (ppt/pptx).
     *
     * Algunos navegadores/servidores detectan variantes (slideshow, macro-enabled, etc.).
     *
     * @return list<string>
     */
    protected function materialDocumentoPowerPointAllowedMimes(): array
    {
        return [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-powerpoint.presentation.macroenabled.12',
            'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
            'application/vnd.ms-powerpoint.slideshow.macroenabled.12',
            'application/octet-stream',
        ];
    }

    /**
     * MIME types válidos para Word (doc/docx).
     *
     * @return list<string>
     */
    protected function materialDocumentoWordAllowedMimes(): array
    {
        return [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/octet-stream',
        ];
    }

    /**
     * Reglas base para upload de documento (sin mimes: se valida en after).
     *
     * @return array<string, string>
     */
    protected function materialDocumentoFileRules(bool $nullable = true): array
    {
        return [
            'documento' => ($nullable ? 'nullable|' : 'required|').'file|max:'.$this->materialDocumentoMaxKilobytes(),
        ];
    }

    protected function assertMaterialDocumentoFile(ValidatorInstance $validator, ?UploadedFile $file, string $attribute = 'documento'): void
    {
        if (! $file) {
            return;
        }

        $allowed = $this->materialDocumentoAllowedExtensions();
        $ext = strtolower((string) $file->getClientOriginalExtension());

        if ($ext === '' || ! in_array($ext, $allowed, true)) {
            $validator->errors()->add($attribute, $this->materialDocumentoAllowedExtensionsMessage());

            return;
        }

        if ($ext === 'sql') {
            $mime = strtolower((string) ($file->getMimeType() ?? ''));
            $allowedSqlMimes = [
                'text/plain',
                'text/x-sql',
                'application/sql',
                'application/x-sql',
                'application/octet-stream',
            ];

            if ($mime !== '' && ! in_array($mime, $allowedSqlMimes, true)) {
                $validator->errors()->add($attribute, 'El archivo SQL no tiene un tipo válido.');
            }

            return;
        }

        if ($ext === 'ppt' || $ext === 'pptx') {
            $mime = strtolower((string) ($file->getMimeType() ?? ''));
            $allowed = $this->materialDocumentoPowerPointAllowedMimes();
            if ($mime !== '' && ! in_array($mime, $allowed, true)) {
                $validator->errors()->add($attribute, $this->materialDocumentoAllowedExtensionsMessage());
            }

            return;
        }

        if ($ext === 'doc' || $ext === 'docx') {
            $mime = strtolower((string) ($file->getMimeType() ?? ''));
            $allowed = $this->materialDocumentoWordAllowedMimes();
            if ($mime !== '' && ! in_array($mime, $allowed, true)) {
                $validator->errors()->add($attribute, $this->materialDocumentoAllowedExtensionsMessage());
            }

            return;
        }

        $secondary = Validator::make([$attribute => $file], [
            $attribute => 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar',
        ]);

        if ($secondary->fails()) {
            $validator->errors()->add($attribute, $this->materialDocumentoAllowedExtensionsMessage());
        }
    }
}
