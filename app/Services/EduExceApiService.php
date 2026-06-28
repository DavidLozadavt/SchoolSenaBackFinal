<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EduExceApiService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('EDUEXCE_API_URL', 'http://127.0.0.1:3333'), '/');
        $this->apiKey = env('EDUEXCE_INTEGRATION_API_KEY', env('SCHOOL_INTEGRATION_API_KEY', 'School_EduExce_Integration_2026'));
    }

    private function client()
    {
        return Http::withHeaders([
            'X-Integration-Token' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30);
    }

    public function provisionarInstitucion(array $payload): array
    {
        $response = $this->client()->post("{$this->baseUrl}/integracion/institucion/provisionar", $payload);

        if (!$response->successful()) {
            $error = $response->json('error') ?? $response->body();
            Log::error('EduExce provisionarInstitucion falló', ['status' => $response->status(), 'error' => $error]);
            throw new \RuntimeException(is_string($error) ? $error : 'Error al provisionar institución en EduExce');
        }

        return $response->json();
    }

    public function provisionarEstudiante(array $payload): array
    {
        $response = $this->client()->post("{$this->baseUrl}/integracion/estudiante/provisionar", $payload);

        if (!$response->successful()) {
            $error = $response->json('error') ?? $response->body();
            $code = $response->json('code');
            Log::error('EduExce provisionarEstudiante falló', ['status' => $response->status(), 'error' => $error, 'code' => $code]);
            throw new \RuntimeException(is_string($error) ? $error : 'Error al provisionar estudiante en EduExce');
        }

        return $response->json();
    }

    public function desactivarInstitucion(int $externalSchoolId): array
    {
        $response = $this->client()->post("{$this->baseUrl}/integracion/institucion/desactivar", [
            'external_school_id' => $externalSchoolId,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error') ?? 'Error al desactivar institución en EduExce');
        }

        return $response->json();
    }

    public function reactivarInstitucion(int $externalSchoolId): array
    {
        $response = $this->client()->post("{$this->baseUrl}/integracion/institucion/reactivar", [
            'external_school_id' => $externalSchoolId,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error') ?? 'Error al reactivar institución en EduExce');
        }

        return $response->json();
    }

    public function resumenInstitucion(int $externalSchoolId): array
    {
        $response = $this->client()->get("{$this->baseUrl}/integracion/institucion/{$externalSchoolId}/resumen");

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error') ?? 'Error al obtener estadísticas de EduExce');
        }

        return $response->json();
    }

    public function listarInstituciones(string $origen = 'nativas'): array
    {
        $response = $this->client()->get("{$this->baseUrl}/integracion/instituciones", [
            'origen' => $origen,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error') ?? 'Error al listar instituciones EduExce');
        }

        return $response->json();
    }

    public function desactivarInstitucionEduexce(int $idInstitucion): array
    {
        $response = $this->client()->post("{$this->baseUrl}/integracion/institucion/{$idInstitucion}/desactivar");

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error') ?? 'Error al desactivar institución EduExce');
        }

        return $response->json();
    }

    public function reactivarInstitucionEduexce(int $idInstitucion): array
    {
        $response = $this->client()->post("{$this->baseUrl}/integracion/institucion/{$idInstitucion}/reactivar");

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error') ?? 'Error al reactivar institución EduExce');
        }

        return $response->json();
    }

    public function obtenerInstitucionEduexce(int $idInstitucion): array
    {
        $response = $this->client()->get("{$this->baseUrl}/integracion/institucion/eduexce/{$idInstitucion}");

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error') ?? 'Error al obtener institución EduExce');
        }

        return $response->json();
    }

    public function vincularInstitucionSchool(int $idInstitucion, int $externalSchoolId): array
    {
        $response = $this->client()->post("{$this->baseUrl}/integracion/institucion/vincular-school", [
            'id_institucion' => $idInstitucion,
            'external_school_id' => $externalSchoolId,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error') ?? 'Error al vincular institución con School');
        }

        return $response->json();
    }

    public function generarSsoSchool(int $externalSchoolId): array
    {
        $response = $this->client()->post("{$this->baseUrl}/integracion/sso/school", [
            'external_school_id' => $externalSchoolId,
        ]);

        if (!$response->successful()) {
            $body = $response->json();
            $error = is_array($body)
                ? ($body['error'] ?? $body['message'] ?? null)
                : null;
            $error = is_string($error) && trim($error) !== ''
                ? $error
                : 'Error al generar acceso al panel EduExce';
            throw new \RuntimeException($error);
        }

        return $response->json();
    }
}
