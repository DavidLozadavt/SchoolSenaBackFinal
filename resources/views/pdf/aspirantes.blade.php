<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 1.2cm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8px;
        }

        h2 {
            text-align: center;
            margin-bottom: 4px;
        }

        p.subtitle {
            text-align: center;
            color: #666;
            margin-top: 0;
            margin-bottom: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #999;
            padding: 3px 5px;
            text-align: left;
            word-wrap: break-word;
        }

        th {
            background-color: #16a34a;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <h2>Seguimiento de Aspirantes</h2>
    <p class="subtitle">Generado el {{ now()->format('d/m/Y H:i') }} — {{ count($filas) }} registro(s)</p>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    @foreach ($fila as $valor)
                        <td>{{ $valor }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
