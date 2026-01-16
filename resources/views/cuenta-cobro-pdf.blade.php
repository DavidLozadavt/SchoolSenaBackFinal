<?php
$pathToImage = public_path('/default/logoweb.png');
$imageData = base64_encode(file_get_contents($pathToImage));
$imageBase64 = 'data:image/png;base64,' . $imageData;


$pathToImageFirma = public_path('/default/firmaG.png');
$imageDataFirma = base64_encode(file_get_contents($pathToImageFirma));
$imageBase64Firma = 'data:image/png;base64,' . $imageDataFirma;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
        }

        .invoice-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .invoice-header h1 {
            font-size: 24px;
            margin: 0;
        }

        .invoice-header p {
            font-size: 14px;
            margin: 0;
        }

        .imagen {
            margin-right: 20px;
        }

        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .invoice-info p {
            font-size: 14px;
            margin: 0;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-table th,
        .invoice-table td {
            padding: 5px;
            border: 1px solid #ccc;
            text-align: left;
        }

        .invoice-table th {
            background-color: #f0f0f0;
        }

        .invoice-total {
            text-align: left;
            font-weight: bold;
            margin-top: 20px;
        }

        .invoice-footer {
            text-align: left;
            margin-top: 20px;
        }

        .invoice-footer p {
            font-size: 12px;
            margin: 0;
        }

        .receipt-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .receipt-recipient,
        .receipt-details {
            margin: 0;
        }


        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.1;
            width: 500px;
            height: auto;
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <img src="{{ $imageBase64 }}" class="watermark">

        <div class="invoice-header" style="display: flex; align-items: center; margin-top:100px">
            <div>
                <img src="{{ $imageBase64 }}" style="margin-right: 20px;">

            </div>
            <div>
                <p>VIRTUAL TECHNOLOGY</p>
                <p>Prestación de servicios de TI</p>
                <p>NIT. 10296037-9</p>
                <p>CEL: 3156614275</p>
            </div>
        </div>


        <div style="margin-top: 50px" class="invoice-info">
        <caption style="caption-side: top; font-size: 1.1em; font-weight: bold; margin-bottom: 10px;">
                INFORMACIÓN DE CONTACTO
            </caption>
            <div>
                <p>Cliente: {{$tercero->nombre}}</p>
                <p>NIT: {{$tercero->identificacion}}</p>
                <p>Telefono: {{$tercero->telefono}}</p>
            </div>
            <div>
                <p>Popayán {{ $fechaActual }}</p>

            </div>
            <caption style="caption-side: top; font-size: 1.1em; font-weight: bold; margin-bottom: 10px;">
                CUENTA DE COBRO
            </caption>
        </div>

        <div class="invoice-table">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>CANTIDAD</th>
                        <th>DETALLE</th>
                        <th>VALOR UNIT.</th>
                        <th>VALOR TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>{{$contrato->descripcion}} Correspondiente al {{$pago->porcentaje}}% del valor total de {{ number_format($transaccion->valor, 0, ',', '.')}} COP</td>
                        <td>{{ number_format($pago2->valor, 0, ',', '.') }}</td>

                        <td>{{ number_format($pago2->valor, 0, ',', '.') }}</td>

                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">TOTAL</th>
                        <td>{{ number_format($pago2->valor, 0, ',', '.') }}</td>

                    </tr>
                </tfoot>
            </table>
        </div>


        <div class="invoice-total">

        </div>

        <div style="margin-top: 30px" class="invoice-footer">
            <p>Información de cuenta</p>
            <p>BANCOLOMBIA</p>
            <p>Tipo de Cuenta: Ahorros</p>
            <p>Nro Cuenta: {{$nCuenta}}</p>
            <p>Titular: David Eduardo Lozada Cerón</p>
        </div>


        <div>

            <div>
                <img class="imagen" src="{{ $imageBase64Firma }}">
                <p class="mb-1">DAVID EDUARDO LOZADA CERÓN</p>
                <hr style="width: 300px; margin-right:400px">
                <p class="receipt-details mb-1">C.C 10296037 de Popayán Cauca</p>
                <p>Gerente</p>
            </div>

            <div>

                <p class="mb-1">Recibí conforme:</p>
                <hr style="width: 300px; margin-right:400px">

            </div>
        </div>

    </div>
</body>

</html>