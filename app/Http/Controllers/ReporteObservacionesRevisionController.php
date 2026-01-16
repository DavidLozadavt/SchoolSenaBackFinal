<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;


class ReporteObservacionesRevisionController extends Controller
{
    public function generatePDFObservaciones(Request $request)
    {
        $user = KeyUtil::user();
        $idCompany = KeyUtil::idCompany();
        $company = Company::where('id', $idCompany)->first();

        if (!$company) {
            $company = (object) [
                'razonSocial' => 'TAX BELALCAZAR',
                'nit' => '123456789-0'
            ];
        }
        $logoPath = null;
           if ($company->rutaLogoUrl) {
            $fileName = basename($company->rutaLogoUrl);
            $fullPath = public_path('storage/company/' . $fileName);
            
            if (file_exists($fullPath)) {
                $logoPath = $fullPath;
            }
        }

        $fechaActual = Carbon::now();
       $observaciones = collect([
        (object) [
            'fecha' => '2025-10-09',
            'vehiculo' => (object) ['placa' => 'TJT-320', 'numeroInterno' => '2005'],
            'conductor' => null,
            'revisor' => (object) ['nombre' => 'PABLO M. DESCANSE'],
            'observacion1' => 'Golpe bomper',
            'observacion2' => '',
            'diasDesde' => 19
        ],
        (object) [
            'fecha' => '2025-10-27',
            'vehiculo' => (object) ['placa' => 'SOZ-834', 'numeroInterno' => '2006'],
            'conductor' => null,
            'revisor' => (object) ['nombre' => 'NORBEY GONZALEZ M.'],
            'observacion1' => 'Se aproxima cambio de llanta',
            'observacion2' => '',
            'diasDesde' => 1
        ],
        (object) [
            'fecha' => '2025-10-14',
            'vehiculo' => (object) ['placa' => 'STR-099', 'numeroInterno' => '2010'],
            'conductor' => null,
            'revisor' => (object) ['nombre' => 'NORBEY GONZALEZ M.'],
            'observacion1' => 'Tapa A. Acondicionado',
            'observacion2' => '',
            'diasDesde' => 14
        ],
        (object) [
            'fecha' => '2025-10-18',
            'vehiculo' => (object) ['placa' => 'SQC-093', 'numeroInterno' => '2019'],
            'conductor' => null,
            'revisor' => (object) ['nombre' => 'NORBEY GONZALEZ M.'],
            'observacion1' => 'Golpe lateral derecho',
            'observacion2' => '',
            'diasDesde' => 10
        ],
        (object) [
            'fecha' => '2025-10-22',
            'vehiculo' => (object) ['placa' => 'WFU-800', 'numeroInterno' => '2021'],
            'conductor' => null,
            'revisor' => (object) ['nombre' => 'NORBEY GONZALEZ M.'],
            'observacion1' => 'Tapa A. Acondicionado',
            'observacion2' => '',
            'diasDesde' => 6
        ],
        (object) [
            'fecha' => '2025-09-18',
            'vehiculo' => (object) ['placa' => 'TJT-238', 'numeroInterno' => '2024'],
            'conductor' => (object) ['nombre' => 'HERMES SEVILLA HERNANDEZ', 'identificacion' => '10544684'],
            'revisor' => (object) ['nombre' => 'PABLO M. DESCANSE'],
            'observacion1' => 'Limpir techo interior',
            'observacion2' => 'Forrar decanzabrazos' . "\n" . 'Forrar cojineria' . "\n" . 'limpiar carteras',
            'diasDesde' => 40
        ],
        (object) [
            'fecha' => '2025-10-25',
            'vehiculo' => (object) ['placa' => 'TJT-380', 'numeroInterno' => '2026'],
            'conductor' => null,
            'revisor' => (object) ['nombre' => 'NORBEY GONZALEZ M.'],
            'observacion1' => 'Logo empresa parte lateral',
            'observacion2' => '',
            'diasDesde' => 3
        ],
        (object) [
            'fecha' => '2025-10-11',
            'vehiculo' => (object) ['placa' => 'XZL-279', 'numeroInterno' => '2030'],
            'conductor' => null,
            'revisor' => (object) ['nombre' => 'NORBEY GONZALEZ M.'],
            'observacion1' => 'Emblemas deteriorados',
            'observacion2' => '',
            'diasDesde' => 17
        ],
        (object) [
            'fecha' => '2025-09-13',
            'vehiculo' => (object) ['placa' => 'WLN-433', 'numeroInterno' => '2033'],
            'conductor' => (object) ['nombre' => 'EDIER EDINSON MUNOZ NOGUERA', 'identificacion' => '1058973412'],
            'revisor' => (object) ['nombre' => 'NORBEY GONZALEZ M.'],
            'observacion1' => 'Stop fisurado',
            'observacion2' => '',
            'diasDesde' => 45
        ],
        (object) [
            'fecha' => '2025-10-09',
            'vehiculo' => (object) ['placa' => 'TJT-386', 'numeroInterno' => '2043'],
            'conductor' => null,
            'revisor' => (object) ['nombre' => 'NORBEY GONZALEZ M.'],
            'observacion1' => 'Cartera internas por pintar',
            'observacion2' => '',
            'diasDesde' => 19
        ],
        (object) [
            'fecha' => '2025-09-13',
            'vehiculo' => (object) ['placa' => 'SHT-700', 'numeroInterno' => '2066'],
            'conductor' => (object) ['nombre' => 'JORGE ELIECER MUÑOZ PINO', 'identificacion' => '4696391'],
            'revisor' => (object) ['nombre' => 'PABLO M. DESCANSE'],
            'observacion1' => '1.Golpe lateral izquierdo' . "\n" . '2.Tapa A. Acondicionado' . "\n" . '3.Rin Negros',
            'observacion2' => '4.Silla asiento descosido' . "\n" . '5.Silla asiento suelta' . "\n" . '6.Tapa lateral asientos silla ingreso',
            'diasDesde' => 45
        ],
        (object) [
            'fecha' => '2025-10-18',
            'vehiculo' => (object) ['placa' => 'WCX-785', 'numeroInterno' => '2070'],
            'conductor' => null,
            'revisor' => (object) ['nombre' => 'NORBEY GONZALEZ M.'],
            'observacion1' => 'Fuga bajo el motor',
            'observacion2' => 'Rines Negros',
            'diasDesde' => 10
        ],
        (object) [
            'fecha' => '2025-10-14',
            'vehiculo' => (object) ['placa' => 'WMB-357', 'numeroInterno' => '2072'],
            'conductor' => null,
            'revisor' => (object) ['nombre' => 'NORBEY GONZALEZ M.'],
            'observacion1' => 'Cambiar cintas reflectivas trasera',
            'observacion2' => 'Forrar descansabrazos',
            'diasDesde' => 14
        ],
    ]);

    // DATOS FAKE - Vencimientos
    $vencimientos = collect([
        (object) [
            'fechaInicio' => '2025-10-21',
            'vencimiento' => '2025-10-29',
            'vigenciaDias' => 1,
            'detalle' => 'MEDIALUNA ESPEJO RETROVISOR IZQUIERDO',
            'vehiculo' => (object) ['numeroInterno' => '2057'],
            'usuario' => (object) ['nombre' => 'Admin Sistema']
        ],
        (object) [
            'fechaInicio' => '2025-09-21',
            'vencimiento' => '2025-11-01',
            'vigenciaDias' => 4,
            'detalle' => 'FORRAR DESCANSABRAZOS-TAPAS A. ACONDICIONADO',
            'vehiculo' => (object) ['numeroInterno' => '2059'],
            'usuario' => (object) ['nombre' => 'Admin Sistema']
        ],
        (object) [
            'fechaInicio' => '2025-09-21',
            'vencimiento' => '2025-10-21',
            'vigenciaDias' => -7,
            'detalle' => 'GOLPE-RINES-SILLAS-TAPA A.-TAPAS LATERALES SILLAS',
            'vehiculo' => (object) ['numeroInterno' => '2066'],
            'usuario' => (object) ['nombre' => 'Admin Sistema']
        ],
        (object) [
            'fechaInicio' => '2025-10-21',
            'vencimiento' => '2025-10-29',
            'vigenciaDias' => 1,
            'detalle' => 'FUGA BAJO EL MOTOR-RINES NEGROS',
            'vehiculo' => (object) ['numeroInterno' => '2070'],
            'usuario' => (object) ['nombre' => 'Admin Sistema']
        ],
        (object) [
            'fechaInicio' => '2025-10-21',
            'vencimiento' => '2025-10-30',
            'vigenciaDias' => 2,
            'detalle' => 'FORRAR DESCANSABRAZOS - CINTAS REFLECTIVAS TRASERA',
            'vehiculo' => (object) ['numeroInterno' => '2072'],
            'usuario' => (object) ['nombre' => 'Admin Sistema']
        ],
        (object) [
            'fechaInicio' => '2025-08-08',
            'vencimiento' => '2025-11-15',
            'vigenciaDias' => 18,
            'detalle' => 'EMBLEMAS DETERIORADOS-PINTAR COSTADO-TAPA A. ACOND',
            'vehiculo' => (object) ['numeroInterno' => '2095'],
            'usuario' => (object) ['nombre' => 'Admin Sistema']
        ],
    ]);

    // DATOS FAKE - Extintores
    $extintores = collect([
        (object) [
            'vehiculo' => (object) ['numeroInterno' => '2022', 'placa' => 'ABC-123'],
            'fechaVencimiento' => '2025-09-15',
            'observaciones' => 'Revisar presión'
        ],
        (object) [
            'vehiculo' => (object) ['numeroInterno' => '2023', 'placa' => 'DEF-456'],
            'fechaVencimiento' => '2025-09-20',
            'observaciones' => 'Cambio requerido'
        ],
        (object) [
            'vehiculo' => (object) ['numeroInterno' => '2030', 'placa' => 'XZL-279'],
            'fechaVencimiento' => '2025-09-25',
            'observaciones' => 'Verificar sello'
        ],
    ]);

        $data = [
            'company' => $company,
            'observaciones' => $observaciones,
            'vencimientos' => $vencimientos,
            'extintores' => $extintores,
            'fechaGeneracion' => $fechaActual->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'),
            'totalRegistros' => $observaciones->count(),
           'logoPath' => $logoPath,
        ];


      $pdf = PDF::loadView('transporte.observaciones-revision-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('debugKeepTemp', true);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="observaciones_vehiculos.pdf"');
    }
}
