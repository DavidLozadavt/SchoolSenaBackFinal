<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <style>
        .invoice-box {
            max-width: 1000px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            line-height: 24px;
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #555;
        }

        .invoice-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .invoice-header img {
            width: 150px;
            max-width: 100%;
        }

        .invoice-header .info {
            margin-top: 10px;
        }

        .separator {
            border-top: 2px solid #000000;
            margin: 1px 0;

        }

        .invoice-details {
            margin-bottom: 5px;
        }

        .invoice-details .row {
            margin-bottom: 10px;
        }

        .invoice-detalis .left,
        .invoice-details .right {
            padding: 2px 0;
            font-size: 14px;
        }

        .invoice-details .right {
            text-align: right;
        }

        .invoice-details .left p,
        .invoice-details .right p {
            margin: 0;
        }

        .table {
            margin-top: 5px;

        }

        .table thead th {
            border-bottom: 2px solid #eee;
        }

        .table tbody td {
            border-bottom: 1px solid #eee;
        }

        .table td p {
            margin: 0;
            padding: 2px 0;

        }
    </style>
</head>

<body>
    <div class="invoice-box">
        <div class="invoice-header">
            <img src="{{$company->rutaLogoUrl}}" alt="Logo">
            <div class="info">
                <strong>{{$company->razonSocial}}</strong><br>
                NIT: {{$company->nit}}-{{$company->digitoVerificacion}}<br>
                {{$company->direccion}}<br>
                TEL: {{$company->telefono}}<br>
                {{$company->email}}
            </div>
        </div>
        <div class="separator"></div>

        <table class="table" style="border: none; margin-bottom:1px">
            <tbody>
                <tr>
                    <td style="width: 50%; padding:0%;" style="border: none; margin-bottom:1px">
                        <div class="table-responsive">
                            <table class="table table-sm" style="border: none;">

                                <p>Recibo de Venta N°: <strong>{{$factura->numeroFactura}}</strong></p>
                                <p>Fecha: {{$pago->fechaReg}}</p>
                                <p>Vendedor: {{$caja->usuario->persona->nombre1}} {{$caja->usuario->persona->apellido1}}</p>

                            </table>
                        </div>
                    </td>
                    <td style="width: 50%; padding:0%;" style="border: none;">
                        <div class="table-responsive">
                            <table class="table table-borderless table-sm" style="border: none;">


                                <p>Tienda: {{$caja->puntoVenta->sede->nombreSede}}</p>
                                <p>Caja: {{$caja->puntoVenta->nombre}}</p>


                            </table>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>



        <div class="separator"></div>
        <div class="invoice-details" style="margin-top: 10px;">
            <div class="row">
                <div class="col-8 left">
                    <p>Cliente: {{$tercero->nombre}}</p>
                    <p>Identificación: {{$tercero->identificacion}}</p>
                    <p>Tipo Identificación: {{$tercero->tipoIdentificacion->detalle}}</p>
                    <p>Dirección: {{$tercero->direccion}}</p>
                    <p>País: Colombia</p>
                    <p>Correo: {{$tercero->email}}</p>
                </div>
            </div>
        </div>


        <table class="table">
            <thead>
                <tr>
                    <th>Cantidad</th>
                    <th>Detalle</th>
                    <th>Valor Unitario</th>
                    <th>IVA</th>
                    <th>Valor Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detalles as $detalle)
                <tr>
                    <td>1</td>
                    <td>{{ $detalle->servicio->nombre }}</td>
                    <td>$ {{number_format($detalle->valor, 0, ',', '.')}}</td>
                    <td>$ {{number_format($detalle->valorIvaServicio, 0, ',', '.')}}</td>
                    <td>$ {{number_format($detalle->valor + $detalle->valorIvaServicio, 0, ',', '.')}}</td>

                </tr>
                @endforeach
            </tbody>
        </table>


        <div class="separator"></div>

        <table class="table" style="border: none; margin-bottom:1px">
            <tbody>
                <tr>
                    <td style="width: 50%; padding:0%;" style="border: none; margin-bottom:1px">
                        <div class="table-responsive">
                            <table class="table table-sm" style="border: none;">

                                <p>Total Bruto:</p>
                                <p>Descuentos:</p>
                                <p>Sub Total:</p>
                                <p>I.V.A:</p>
                                <p>Total:</p>

                            </table>
                        </div>
                    </td>
                    <td style="width: 50%; padding:0%;" style="border: none;">
                        <div class="table-responsive">
                            <table class="table table-borderless table-sm" style="border: none;">


                                <p>$ {{ number_format($factura->valor, 0, ',', '.')}}</p>
                                <p>$ 0.00</p>
                                <p>$ {{ number_format($factura->valor, 0, ',', '.')}}</p>
                                <p>$ {{ number_format($factura->valorIva, 0, ',', '.')}}</p>
                                <p>$ {{ number_format($factura->valorMasIva, 0, ',', '.')}}</p>



                            </table>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="separator"></div>




        <table class="table" style="border: none; margin-bottom:1px">
            <tbody>
                <tr>
                    <td style="width: 50%; padding:0%;" style="border: none; margin-bottom:1px">
                        <div class="table-responsive">
                            <table class="table table-sm" style="border: none;">

                                <p>Forma de Pago:</p>
                                <p>Medio de Pago:</p>

                            </table>
                        </div>
                    </td>
                    <td style="width: 50%; padding:0%;" style="border: none;">
                        <div class="table-responsive">
                            <table class="table table-borderless table-sm" style="border: none;">

                                <p>{{$transaccion->tipoPago->detalleTipoPago}}</p>
                                <p>{{$pago->medioPago->detalleMedioPago}}</p>


                            </table>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>




    </div>
</body>

</html>