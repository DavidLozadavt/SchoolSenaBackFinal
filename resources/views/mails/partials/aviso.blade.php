{{-- Recuadro informativo. Variables: $texto. Opcional: $titulo, $tono = 'info' | 'alerta' | 'exito'. --}}
@php
    $tonos = [
        'info'   => ['fondo' => '#fafafa', 'borde' => '#eeeeee', 'titulo' => '#3949ab', 'texto' => '#444444'],
        'alerta' => ['fondo' => '#fff8e1', 'borde' => '#ffe082', 'titulo' => '#e65100', 'texto' => '#5d4037'],
        'exito'  => ['fondo' => '#e8f5e9', 'borde' => '#a5d6a7', 'titulo' => '#2e7d32', 'texto' => '#1b5e20'],
    ];
    $t = $tonos[$tono ?? 'info'] ?? $tonos['info'];
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0;">
    <tr>
        <td style="padding:18px 22px; background-color:{{ $t['fondo'] }}; border:1px solid {{ $t['borde'] }}; border-radius:12px; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:{{ $t['texto'] }};">
            @if(!empty($titulo))
                <strong style="display:block; margin-bottom:4px; color:{{ $t['titulo'] }};">{{ $titulo }}</strong>
            @endif
            {{ $texto }}
        </td>
    </tr>
</table>
