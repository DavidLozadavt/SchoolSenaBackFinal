<?php

namespace App\Jobs;

use App\Models\FacturaElectronica;
use App\Models\Transporte\Ticket;
use App\Services\FactusClient;
use App\Util\KeyUtil;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CreateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public Ticket $ticket;

    /**
     * Número máximo de intentos del job
     */
    public $tries = 5;

    /**
     * Tiempo máximo de ejecución en segundos
     */
    public $timeout = 120;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    public function handle(FactusClient $factus)
    {
        DB::beginTransaction();

        try {
            $referenceCode = 'ticket-' . $this->ticket->numeroTicket;
            if (FacturaElectronica::where('reference_code', $referenceCode)->exists()) {
                return;
            }

            $cliente = $this->ticket->tercero;

            if (!$cliente || !$cliente->identificacion) {
                Log::warning("Ticket {$this->ticket->id} sin cliente válido. Usando cliente de prueba.");
                $clienteData = [
                    'identification' => '22222222',
                    'names' => 'Consumidor Final (Prueba)',
                    'phone' => '0000000',
                    'address' => 'Colombia',
                    'email' => 'demo@correo.com',
                    'identification_document_id' => 3,
                ];
            } else {
                $clienteData = [
                    'identification' => $cliente->identificacion,
                    'dv' => $cliente->digitoVerficacion ?? null,
                    'names' => $cliente->nombre,
                    'phone' => $cliente->telefono,
                    'address' => $cliente->direccion,
                    'email' => $cliente->email,
                    'identification_document_id' => $cliente->idTipoIdentificacion ?? null,
                ];
            }

            $payload = [
                'numbering_range_id' => 8,
                'reference_code' => $referenceCode,
                'date' => now()->toDateString(),
                'time' => now()->format('H:i:s'),
                'customer' => $clienteData,
                'items' => [[
                    'code_reference' => 'TICKET-' . $this->ticket->id,
                    'name' => "Tiquete de viaje {$this->ticket->ruta->ciudadOrigen->descripcion} → {$this->ticket->ruta->ciudadDestino->descripcion}",
                    'quantity' => 1,
                    'discount_rate' => 0,
                    'price' => $this->ticket->ruta->precio,
                    'tax_rate' => 19.00,
                    'unit_measure_id' => 70,
                    'standard_code_id' => 1,
                    'is_excluded' => 0,
                    'tribute_id' => 1,
                ]],
                'payments' => [[
                    'payment_method_id' => 10,
                    'payment_due_date' => now()->toDateString(),
                    'payment_amount' => $this->ticket->valor,
                ]],
            ];

            $response = $factus->validateInvoice($payload);
            $data = $response['data'] ?? [];
            $bill = $data['bill'] ?? [];
            $status = strtolower($response['status'] ?? 'pending');

            $factura = FacturaElectronica::create([
                'ticket_id'       => $this->ticket->id,
                'reference_code'  => $bill['reference_code'] ?? ('ticket-' . $this->ticket->numeroTicket),
                'factus_id'       => $bill['id'] ?? null,
                'prefix'          => $data['numbering_range']['prefix'] ?? null,
                'number'          => $bill['number'] ?? null,
                'cufe'            => $bill['cufe'] ?? null,
                'status'          => $status === 'created' ? 'validated' : 'error',
                'email_status'    => 'sent',
                'pdf_path'        => $bill['public_url'] ?? null,
                'xml_path'        => $bill['xml'] ?? null,
                'qr_url'          => $bill['qr'] ?? $bill['qr_image'] ?? null,
                'error_code'      => isset($bill['errors']) ? implode(',', array_keys($bill['errors'])) : null,
                'error_message'   => isset($bill['errors']) ? json_encode($bill['errors']) : null,
                'validated_at'    => isset($bill['validated'])
                    ? \Carbon\Carbon::createFromFormat('d-m-Y h:i:s A', $bill['validated'])
                    : now(),
                'sent_at'          => now(),
                'idEmpresa'        => $this->ticket->idEmpresa,
            ]);

            if (!empty($bill['qr_image'])) {
                try {
                    $base64String = $bill['qr_image'];
                    $base64String = preg_replace('/^data:image\/[^;]+;base64,\s*/', '', $base64String);
                    $base64String = trim($base64String);
                    $base64String = str_replace(' ', '+', $base64String);
                    $imageData = base64_decode($base64String, true);
                    if ($imageData === false || empty($imageData)) {
                        throw new \Exception('Error al decodificar base64: datos inválidos');
                    }
                    if (substr($imageData, 0, 8) !== "\x89PNG\r\n\x1a\n") {
                        throw new \Exception('Los datos decodificados no son una imagen PNG válida');
                    }
                    $fileName = 'qr_factura_' . $this->ticket->id . '_' . time() . '.png';
                    $filePath = 'qr-factura-electronica/' . $fileName;
                    $saved = Storage::disk('public')->put($filePath, $imageData);
                    if (!$saved) {
                        throw new \Exception('No se pudo guardar el archivo en el disco');
                    }
                    $factura->qr_image = 'storage/' . $filePath;
                    $factura->save();
                    // Log::info("✅ QR guardado correctamente: {$factura->qr_image} (" . strlen($imageData) . " bytes)");
                } catch (\Throwable $e) {
                    // Log::error("⚠️ Error al guardar QR de factura {$factura->id}: {$e->getMessage()}");
                    // Log::error("Base64 original (primeros 100 chars): " . substr($bill['qr_image'], 0, 100));
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            // Detectar si es el error 409 de Factus (factura pendiente)
            $errorCode = method_exists($e, 'getCode') ? $e->getCode() : 0;
            $errorMessage = $e->getMessage();
            
            if ($errorCode === 409 || strpos($errorMessage, 'pendiente por enviar') !== false) {
                Log::warning("⏸️ Factura para ticket {$this->ticket->id} pospuesta por conflicto en Factus", [
                    'ticket_id' => $this->ticket->id,
                    'error' => $errorMessage,
                ]);

                // Guardar como pendiente para reintentar más tarde
                FacturaElectronica::updateOrCreate(
                    [
                        'ticket_id' => $this->ticket->id,
                        'reference_code' => 'ticket-' . $this->ticket->numeroTicket,
                    ],
                    [
                        'status' => 'pending',
                        'error_code' => 'FACTUS_409',
                        'error_message' => 'Factus tiene facturas pendientes. Se reintentará automáticamente.',
                        'idEmpresa' => $this->ticket->idEmpresa,
                    ]
                );

                // Reintentar el job después de 2 minutos
                $this->release(120);
                return;
            }

            // Para otros errores, guardar como error
            FacturaElectronica::create([
                'ticket_id' => $this->ticket->id,
                'reference_code' => 'ticket-' . $this->ticket->numeroTicket,
                'status' => 'error',
                'error_code' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
                'idEmpresa' => $this->ticket->idEmpresa,
            ]);

            Log::error("❌ Error generando factura para ticket {$this->ticket->id}: {$e->getMessage()}");
        }
    }
}
