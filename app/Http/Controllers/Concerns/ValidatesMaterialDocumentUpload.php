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
     * Reglas base para upload de documento (sin mimes: se valida en after).
     *
     * @return array<string, string>
     */
    protected function materialDocumentoFileRules(bool $nullable = true): array
    {
        return [
            'documento' => ($nullable ? 'nullable|' : 'required|').'file|max:10240',
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

        $secondary = Validator::make([$attribute => $file], [
            $attribute => 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar',
        ]);

        if ($secondary->fails()) {
            $validator->errors()->add($attribute, $this->materialDocumentoAllowedExtensionsMessage());
        }
    }
}
