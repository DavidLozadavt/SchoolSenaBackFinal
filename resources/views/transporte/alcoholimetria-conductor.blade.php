<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Planilla Alcoholimetría</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Courier New", Courier, monospace;
            font-size: 9px;
            color: #000;
            line-height: 1.2;
        }

        .ticket {
            width: 220px;
            padding: 6px;
            box-sizing: border-box;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .small {
            font-size: 8px;
        }

        .very-small {
            font-size: 7.5px;
        }

        .dashed {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }

        .espacio {
            height: 3px;
        }

        pre {
            margin: 0;
            font-family: "Courier New", Courier, monospace;
        }
    </style>
</head>

<body>
    <div class="ticket">

        {{-- <div class="center small bold">COOP. RÁPIDO TAMBO</div>
        <div class="center small">NIT. 891.500.194-9</div>
        <div class="center small">CLL 4 17-49 P2 TEL: 8373117 POPAYÁN</div>

        <div class="espacio"></div> --}}

        <div class="center small bold">COOP. RÁPIDO TAMBO</div>
        <div class="center small">NIT. 891.500.194-9</div>
        <div class="center small">CLL 4 17-49 P2 TEL: 8373117 POPAYÁN</div>

        <div class="dashed"></div>

        <div class="center very-small bold"># VÁLIDO SOLO PARA COMPRA DE SALIDA DESDE #</div>
        <div class="center very-small bold"># TERMINAL O PRUEBA DE ALCOHOLIMETRÍA #</div>

        <div class="dashed"></div>

        <pre>
    Fecha........: {{ $fecha }}
    Planilla No..: {{ $planilla }}


    Agencia......: 01 TERMINAL
    Trayecto.....: {{ $trayecto }}
    Despachador..: {{ $despachador }}
    Hora Salida..: {{ $hora }}
    Vehículo.....: {{ $vehiculo }}  Placas: {{ $placa }}
    Motorista....: {{ $motorista }}
    Propietario..: {{ $propietario }}
        </pre>

        <div class="dashed"></div>

        <div class="center very-small bold"># VÁLIDO SOLO PARA COMPRA DE SALIDA DESDE #</div>
        <div class="center very-small bold"># TERMINAL O PRUEBA DE ALCOHOLIMETRÍA #</div>

        <div class="espacio"></div>
        <br>
        <br>
        <br>
        <br>
        <br>
        <div class="center very-small">DESPACHADOR</div>
        <div class="dashed"></div>
        <div class="espacio"></div>

    </div>
</body>

</html>
