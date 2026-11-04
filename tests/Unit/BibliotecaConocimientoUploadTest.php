<?php

namespace Tests\Unit;

use App\Http\Controllers\Concerns\ValidatesMaterialDocumentUpload;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\TestCase;

class BibliotecaConocimientoUploadHarness
{
    use ValidatesMaterialDocumentUpload;

    public function validateDocumento(UploadedFile $file): array
    {
        $v = Validator::make(['documento' => $file], $this->materialDocumentoFileRules(true), $this->materialDocumentoValidationMessages());
        $v->after(function ($validator) use ($file) {
            $this->assertMaterialDocumentoFile($validator, $file);
        });
        $v->passes();

        return $v->errors()->all();
    }

    public function validateVideo(UploadedFile $file): array
    {
        $v = Validator::make(['video' => $file], $this->materialVideoFileRules(true), $this->materialVideoValidationMessages());
        $v->after(function ($validator) use ($file) {
            $this->assertMaterialVideoFile($validator, $file);
        });
        $v->passes();

        return $v->errors()->all();
    }

    public function mensajePhp(UploadedFile $file, string $field): string
    {
        return $this->mensajeErrorUploadPhp($file, $field);
    }

    public function nombreErrorPhp(int $code): string
    {
        return $this->phpUploadErrorName($code);
    }
}

class BibliotecaConocimientoUploadTest extends TestCase
{
    private BibliotecaConocimientoUploadHarness $harness;

    public static function setUpBeforeClass(): void
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->harness = new BibliotecaConocimientoUploadHarness();
    }

    public function test_columnas_de_biblioteca_existen_sin_migracion(): void
    {
        $this->assertTrue(Schema::hasTable('materialApoyoRap'));
        $this->assertTrue(Schema::hasColumn('materialApoyoRap', 'urlDocumento'));
        $this->assertTrue(Schema::hasColumn('materialApoyoRap', 'urlVideo'));
        $this->assertTrue(Schema::hasColumn('materialApoyoRap', 'urlAdicional'));
    }

    public function test_pdf_valido_se_acepta(): void
    {
        $file = $this->uploaded('guia.pdf', $this->pdfBytes());
        $this->assertSame([], $this->harness->validateDocumento($file));
    }

    public function test_docx_ooxml_se_acepta(): void
    {
        $file = $this->uploaded('guia.docx', $this->zipBytes());
        $this->assertSame([], $this->harness->validateDocumento($file));
    }

    public function test_xlsx_ooxml_se_acepta(): void
    {
        $file = $this->uploaded('datos.xlsx', $this->zipBytes());
        $this->assertSame([], $this->harness->validateDocumento($file));
    }

    public function test_pptx_ooxml_se_acepta(): void
    {
        $file = $this->uploaded('clase.pptx', $this->zipBytes());
        $this->assertSame([], $this->harness->validateDocumento($file));
    }

    public function test_sql_texto_se_acepta(): void
    {
        $file = $this->uploaded('script.sql', "SELECT 1;\n");
        $this->assertSame([], $this->harness->validateDocumento($file));
    }

    public function test_docx_falso_texto_se_rechaza(): void
    {
        $file = $this->uploaded('falso.docx', "esto no es word\n");
        $this->assertNotEmpty($this->harness->validateDocumento($file));
    }

    public function test_documento_y_video_juntos_pasan_validacion(): void
    {
        $doc = $this->uploaded('guia.docx', $this->zipBytes());
        $video = $this->uploaded('explicacion.mp4', $this->mp4Bytes());
        $this->assertSame([], $this->harness->validateDocumento($doc));
        $this->assertSame([], $this->harness->validateVideo($video));
    }

    public function test_zip_se_acepta(): void
    {
        $file = $this->uploaded('anexos.zip', $this->zipBytes());
        $this->assertSame([], $this->harness->validateDocumento($file));
    }

    public function test_txt_y_csv_se_aceptan(): void
    {
        $txt = $this->uploaded('notas.txt', "hola\n");
        $csv = $this->uploaded('datos.csv', "a,b\n1,2\n");
        $this->assertSame([], $this->harness->validateDocumento($txt));
        $this->assertSame([], $this->harness->validateDocumento($csv));
    }

    public function test_extension_no_permitida_se_rechaza(): void
    {
        $file = $this->uploaded('malware.exe', "MZ\n");
        $errors = $this->harness->validateDocumento($file);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('formato seleccionado no está permitido', implode(' ', $errors));
    }

    public function test_mp4_con_cabecera_ftyp_se_acepta(): void
    {
        $file = $this->uploaded('explicacion.mp4', $this->mp4Bytes());
        $this->assertSame([], $this->harness->validateVideo($file));
    }

    public function test_mp4_falso_texto_se_rechaza(): void
    {
        $file = $this->uploaded('falso.mp4', "esto no es un video\n");
        $errors = $this->harness->validateVideo($file);
        $this->assertNotEmpty($errors);
    }

    public function test_webm_mov_avi_estan_en_la_lista_oficial(): void
    {
        $this->assertSame(['mp4', 'webm', 'mov', 'avi'], $this->harnessExposedExtensions());
    }

    public function test_upload_err_ini_size_video_tiene_mensaje_especifico(): void
    {
        $file = $this->uploadedWithError('explicacion.mp4', UPLOAD_ERR_INI_SIZE);
        $this->assertFalse($file->isValid());
        $this->assertSame('UPLOAD_ERR_INI_SIZE', $this->harness->nombreErrorPhp($file->getError()));
        $this->assertSame(
            'El video supera los 50 MB permitidos.',
            $this->harness->mensajePhp($file, 'video')
        );
    }

    public function test_upload_err_cant_write_tiene_mensaje_especifico(): void
    {
        $file = $this->uploadedWithError('guia.pdf', UPLOAD_ERR_CANT_WRITE);
        $this->assertSame(
            'No fue posible escribir el archivo temporalmente en el servidor.',
            $this->harness->mensajePhp($file, 'documento')
        );
    }

    public function test_upload_err_partial_mantiene_mensaje_de_recepcion(): void
    {
        $file = $this->uploadedWithError('explicacion.mp4', UPLOAD_ERR_PARTIAL);
        $this->assertSame(
            'El servidor no pudo recibir completamente el archivo.',
            $this->harness->mensajePhp($file, 'video')
        );
    }

    public function test_max_laravel_es_50_mb_en_kb(): void
    {
        $ref = new \ReflectionClass($this->harness);
        $method = $ref->getMethod('materialDocumentoMaxKilobytes');
        $method->setAccessible(true);
        $this->assertSame(51200, $method->invoke($this->harness));
        $rules = (new \ReflectionClass($this->harness))
            ->getMethod('materialVideoFileRules');
        $rules->setAccessible(true);
        $this->assertStringContainsString('max:51200', $rules->invoke($this->harness, true)['video']);
    }

    /** @return list<string> */
    private function harnessExposedExtensions(): array
    {
        $ref = new \ReflectionClass($this->harness);
        $method = $ref->getMethod('materialVideoAllowedExtensions');
        $method->setAccessible(true);

        return $method->invoke($this->harness);
    }

    private function uploaded(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bibup_');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function uploadedWithError(string $name, int $error): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'biberr_');
        $this->assertNotFalse($path);
        file_put_contents($path, 'x');

        return new UploadedFile($path, $name, 'application/octet-stream', $error, true);
    }

    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<>\n%%EOF\n";
    }

    private function zipBytes(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bibzip_');
        $this->assertNotFalse($tmp);
        $zipFile = $tmp.'.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipFile, \ZipArchive::CREATE) === true);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->close();
        $bytes = (string) file_get_contents($zipFile);
        @unlink($zipFile);
        @unlink($tmp);

        return $bytes;
    }

    private function mp4Bytes(): string
    {
        return hex2bin('000000186674797069736f6d0000000169736f6d')
            .str_repeat("\0", 32);
    }
}
