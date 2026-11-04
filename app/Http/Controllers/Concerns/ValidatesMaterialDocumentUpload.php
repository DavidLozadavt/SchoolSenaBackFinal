<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
            'documento.max' => 'El archivo supera el máximo permitido de 50 MB.',
            'documento.uploaded' => 'El servidor no pudo recibir completamente el archivo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function materialVideoValidationMessages(): array
    {
        return [
            'video.max' => 'El archivo supera el máximo permitido de 50 MB.',
            'video.uploaded' => 'El servidor no pudo recibir completamente el archivo.',
        ];
    }

    protected function materialVideoAllowedExtensionsMessage(): string
    {
        return 'Formato de video no permitido. Solo se permiten: MP4, WebM, MOV o AVI.';
    }

    /** @return list<string> */
    protected function materialVideoAllowedExtensions(): array
    {
        return ['mp4', 'webm', 'mov', 'avi'];
    }

    /** @var list<string> */
    protected function materialDocumentoAllowedExtensions(): array
    {
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'sql', 'txt', 'csv', 'zip', 'rar'];
    }

    protected function materialDocumentoAllowedExtensionsMessage(): string
    {
        return 'El formato seleccionado no está permitido.';
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
            'application/CDFV2',
            'application/x-ole-storage',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ];
    }

    /**
     * MIME types válidos para Word .doc (OLE / legacy).
     *
     * @return list<string>
     */
    protected function materialDocumentoWordDocAllowedMimes(): array
    {
        return [
            'application/msword',
            'application/vnd.ms-word',
            'application/x-msword',
            'application/CDFV2',
            'application/x-ole-storage',
            'application/octet-stream',
        ];
    }

    /**
     * MIME types válidos para Word .docx (OOXML; a menudo se detecta como ZIP).
     *
     * @return list<string>
     */
    protected function materialDocumentoWordDocxAllowedMimes(): array
    {
        return [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-word.document.macroenabled.12',
            'application/msword',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ];
    }

    /**
     * MIME types válidos para Word (doc/docx) — unión para mensajes/helpers.
     *
     * @return list<string>
     */
    protected function materialDocumentoWordAllowedMimes(): array
    {
        return array_values(array_unique(array_merge(
            $this->materialDocumentoWordDocAllowedMimes(),
            $this->materialDocumentoWordDocxAllowedMimes()
        )));
    }

    /**
     * Documento base de actividad: solo PDF / Word (.doc, .docx), máx. 50 MB.
     */
    protected function assertActividadDocumentoFile(ValidatorInstance $validator, ?UploadedFile $file, string $attribute = 'documento'): void
    {
        if (! $file) {
            return;
        }

        $allowed = ['pdf', 'doc', 'docx'];
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $message = 'Tipo de archivo no permitido. Solo se permiten: PDF, Word (.doc, .docx).';

        if ($ext === '' || ! in_array($ext, $allowed, true)) {
            $validator->errors()->add($attribute, $message);

            return;
        }

        if ($ext === 'doc' || $ext === 'docx') {
            $mime = strtolower((string) ($file->getMimeType() ?? ''));
            $allowedMimes = $ext === 'doc'
                ? $this->materialDocumentoWordDocAllowedMimes()
                : $this->materialDocumentoWordDocxAllowedMimes();

            if ($mime !== '' && ! in_array($mime, $allowedMimes, true)) {
                $validator->errors()->add($attribute, $message);
            }

            return;
        }

        // PDF: validación secundaria estándar.
        $secondary = Validator::make([$attribute => $file], [
            $attribute => 'mimes:pdf',
        ]);

        if ($secondary->fails()) {
            $validator->errors()->add($attribute, $message);
        }
    }

    /**
     * Valida Word por extensión + MIME allowlist (evita fallos de mimes: de Laravel/Symfony).
     *
     * @return bool true si el archivo es Word y ya se validó (ok o error agregado).
     */
    protected function assertWordFileByExtensionAndMime(
        ValidatorInstance $validator,
        UploadedFile $file,
        string $attribute,
        string $errorMessage
    ): bool {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== 'doc' && $ext !== 'docx') {
            return false;
        }

        $mime = strtolower((string) ($file->getMimeType() ?? ''));
        $allowedMimes = $ext === 'doc'
            ? $this->materialDocumentoWordDocAllowedMimes()
            : $this->materialDocumentoWordDocxAllowedMimes();

        if ($mime !== '' && ! in_array($mime, $allowedMimes, true)) {
            $validator->errors()->add($attribute, $errorMessage);
        }

        return true;
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

    /**
     * Video de biblioteca: extensión + MIME allowlist (no mimes: de Laravel).
     *
     * @return array<string, string>
     */
    protected function materialVideoFileRules(bool $nullable = true): array
    {
        return [
            'video' => ($nullable ? 'nullable|' : 'required|').'file|max:'.$this->materialDocumentoMaxKilobytes(),
        ];
    }

    /** @return list<string> */
    protected function materialDocumentoExcelXlsAllowedMimes(): array
    {
        return [
            'application/vnd.ms-excel',
            'application/msexcel',
            'application/x-msexcel',
            'application/CDFV2',
            'application/x-ole-storage',
            'application/octet-stream',
        ];
    }

    /** @return list<string> */
    protected function materialDocumentoExcelXlsxAllowedMimes(): array
    {
        return [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel.sheet.macroenabled.12',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ];
    }

    /** @return list<string> */
    protected function materialDocumentoPdfAllowedMimes(): array
    {
        return [
            'application/pdf',
            'application/x-pdf',
            'application/acrobat',
            'application/octet-stream',
        ];
    }

    /** @return list<string> */
    protected function materialDocumentoZipAllowedMimes(): array
    {
        return [
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
            'multipart/x-zip',
            'application/octet-stream',
        ];
    }

    /** @return list<string> */
    protected function materialDocumentoRarAllowedMimes(): array
    {
        return [
            'application/vnd.rar',
            'application/x-rar',
            'application/x-rar-compressed',
            'application/octet-stream',
        ];
    }

    /** @return list<string> */
    protected function materialVideoAllowedMimes(string $ext): array
    {
        return match ($ext) {
            'mp4' => [
                'video/mp4',
                'application/mp4',
                'video/x-m4v',
                'video/quicktime',
                'application/octet-stream',
            ],
            'webm' => [
                'video/webm',
                'application/octet-stream',
            ],
            'mov' => [
                'video/quicktime',
                'video/mp4',
                'application/mp4',
                'application/octet-stream',
            ],
            'avi' => [
                'video/x-msvideo',
                'video/avi',
                'video/msvideo',
                'application/octet-stream',
            ],
            default => ['application/octet-stream'],
        };
    }

    protected function assertMimeInList(
        ValidatorInstance $validator,
        UploadedFile $file,
        array $allowedMimes,
        string $attribute,
        string $errorMessage
    ): void {
        $mime = strtolower((string) ($file->getMimeType() ?? ''));
        if ($mime !== '' && ! in_array($mime, $allowedMimes, true)) {
            $validator->errors()->add($attribute, $errorMessage);
        }
    }

    protected function archivoTieneCabeceraMp4(?UploadedFile $file): bool
    {
        if (! $file) {
            return false;
        }
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            return false;
        }
        $head = @file_get_contents($path, false, null, 0, 12);
        if ($head === false || strlen($head) < 8) {
            return false;
        }

        return substr($head, 4, 4) === 'ftyp';
    }

    protected function assertMaterialVideoFile(ValidatorInstance $validator, ?UploadedFile $file, string $attribute = 'video'): void
    {
        if (! $file || ! $file->isValid()) {
            return;
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '' || ! in_array($ext, $this->materialVideoAllowedExtensions(), true)) {
            $validator->errors()->add($attribute, $this->materialVideoAllowedExtensionsMessage());

            return;
        }

        $mime = strtolower((string) ($file->getMimeType() ?? ''));
        $allowedMimes = $this->materialVideoAllowedMimes($ext);
        $mimeOk = $mime === '' || in_array($mime, $allowedMimes, true);

        if ($ext === 'mp4') {
            $tieneFtyp = $this->archivoTieneCabeceraMp4($file);
            if (! $mimeOk && ! $tieneFtyp) {
                $validator->errors()->add($attribute, $this->materialVideoAllowedExtensionsMessage());

                return;
            }
            if (in_array($mime, ['text/plain', 'application/json', 'text/html'], true) && ! $tieneFtyp) {
                $validator->errors()->add($attribute, $this->materialVideoAllowedExtensionsMessage());
            }

            return;
        }

        if (! $mimeOk) {
            $validator->errors()->add($attribute, $this->materialVideoAllowedExtensionsMessage());
        }
    }

    protected function assertMaterialDocumentoFile(ValidatorInstance $validator, ?UploadedFile $file, string $attribute = 'documento'): void
    {
        if (! $file || ! $file->isValid()) {
            return;
        }

        $allowed = $this->materialDocumentoAllowedExtensions();
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $msg = $this->materialDocumentoAllowedExtensionsMessage();

        if ($ext === '' || ! in_array($ext, $allowed, true)) {
            $validator->errors()->add($attribute, $msg);

            return;
        }

        if ($ext === 'sql') {
            $this->assertMimeInList($validator, $file, [
                'text/plain',
                'text/x-sql',
                'application/sql',
                'application/x-sql',
                'application/octet-stream',
            ], $attribute, 'El archivo SQL no tiene un tipo válido.');

            return;
        }

        if ($ext === 'txt') {
            $this->assertMimeInList($validator, $file, [
                'text/plain',
                'text/txt',
                'application/octet-stream',
            ], $attribute, 'El formato seleccionado no está permitido.');

            return;
        }

        if ($ext === 'csv') {
            $this->assertMimeInList($validator, $file, [
                'text/csv',
                'text/plain',
                'application/csv',
                'application/vnd.ms-excel',
                'application/octet-stream',
            ], $attribute, 'El archivo CSV no tiene un tipo válido.');

            return;
        }

        if ($ext === 'ppt' || $ext === 'pptx') {
            $this->assertMimeInList($validator, $file, $this->materialDocumentoPowerPointAllowedMimes(), $attribute, $msg);

            return;
        }

        if ($this->assertWordFileByExtensionAndMime($validator, $file, $attribute, $msg)) {
            return;
        }

        if ($ext === 'xls') {
            $this->assertMimeInList($validator, $file, $this->materialDocumentoExcelXlsAllowedMimes(), $attribute, $msg);

            return;
        }

        if ($ext === 'xlsx') {
            $this->assertMimeInList($validator, $file, $this->materialDocumentoExcelXlsxAllowedMimes(), $attribute, $msg);

            return;
        }

        if ($ext === 'pdf') {
            $this->assertMimeInList($validator, $file, $this->materialDocumentoPdfAllowedMimes(), $attribute, $msg);

            return;
        }

        if ($ext === 'zip') {
            $this->assertMimeInList($validator, $file, $this->materialDocumentoZipAllowedMimes(), $attribute, $msg);

            return;
        }

        if ($ext === 'rar') {
            $this->assertMimeInList($validator, $file, $this->materialDocumentoRarAllowedMimes(), $attribute, $msg);
        }
    }

    protected function phpUploadErrorName(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_OK => 'UPLOAD_ERR_OK',
            UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
            UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
            UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
            UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
            UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
            UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
            UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
            default => 'UPLOAD_ERR_UNKNOWN_'.$code,
        };
    }

    /**
     * Mensaje de usuario a partir del código PHP real. No ignora isValid().
     */
    protected function mensajeErrorUploadPhp(UploadedFile $file, string $field = 'archivo'): string
    {
        $esVideo = $field === 'video';

        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $esVideo
                ? 'El video supera los 50 MB permitidos.'
                : 'El documento supera los 50 MB permitidos.',
            UPLOAD_ERR_PARTIAL => 'El servidor no pudo recibir completamente el archivo.',
            UPLOAD_ERR_NO_FILE => $esVideo ? 'No se recibió el video.' : 'No se recibió el documento.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'No fue posible escribir el archivo temporalmente en el servidor.',
            UPLOAD_ERR_EXTENSION => 'El servidor rechazó el archivo.',
            default => 'El servidor no pudo recibir completamente el archivo.',
        };
    }

    protected function inspectMaterialUploads(Request $request): void
    {
        foreach (['documento', 'video'] as $field) {
            $file = $request->file($field);
            if ($file instanceof UploadedFile) {
                $this->inspectMaterialUploadField($field, $file);
            }
        }
    }

    protected function inspectMaterialUploadField(string $field, UploadedFile $file): void
    {
        $payload = [
            'field' => $field,
            'hasFile' => $file->isValid(),
            'isValid' => $file->isValid(),
            'error' => $file->getError(),
            'errorName' => $this->phpUploadErrorName($file->getError()),
            'errorMessage' => $file->isValid() ? 'UPLOAD_ERR_OK' : $file->getErrorMessage(),
            'clientName' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getClientMimeType(),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];

        if (! $file->isValid()) {
            Log::warning('Biblioteca upload PHP inválido', $payload);

            return;
        }

        if (config('app.debug')) {
            Log::info('Biblioteca upload PHP', $payload);
        }
    }

    /**
     * Mensajes `*.uploaded` según el UPLOAD_ERR_* real. Se fusionan ANTES de validate().
     *
     * @return array<string, string>
     */
    protected function phpUploadValidationMessages(Request $request): array
    {
        $out = [];
        foreach (['documento', 'video'] as $field) {
            $file = $request->file($field);
            if (! $file instanceof UploadedFile || $file->isValid()) {
                continue;
            }
            $out[$field.'.uploaded'] = $this->mensajeErrorUploadPhp($file, $field);
        }

        return $out;
    }
}
