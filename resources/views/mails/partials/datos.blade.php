{{--
    Tabla de datos etiqueta/valor. Variables: $filas = [['Etiqueta', 'Valor'], ...].
    Una fila con valor vacío (null o '') no se muestra.
    Opcional: $titulo (encabezado de sección), $colores = ['Etiqueta' => '#hex'] para resaltar un valor.
--}}
@if(!empty($titulo))
    @include('mails.partials.seccion', ['texto' => $titulo])
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0; background-color:#f8f9ff; border-radius:12px; overflow:hidden;">
    @php $visibles = array_values(array_filter($filas, fn ($f) => $f[1] !== null && $f[1] !== '')); @endphp
    @foreach($visibles as $i => [$etiqueta, $valor])
        @php $borde = $i < count($visibles) - 1 ? 'border-bottom:1px solid #e8eaf0;' : ''; @endphp
        <tr>
            <td width="40%" valign="top" style="padding:12px 16px; {{ $borde }} font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:13px; line-height:19px; font-weight:600; color:#888888;">{{ $etiqueta }}</td>
            <td valign="top" style="padding:12px 16px; {{ $borde }} font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:13px; line-height:19px; font-weight:bold; color:{{ $colores[$etiqueta] ?? '#1a1a2e' }};">{{ $valor }}</td>
        </tr>
    @endforeach
</table>
