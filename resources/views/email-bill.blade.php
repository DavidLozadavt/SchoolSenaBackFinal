<!doctype html>
<html lang="en-US">

<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <title>Appointment Reminder Email Template</title>
    <meta name="description" content="Appointment Reminder Email Template">
</head>
<style>
    a:hover {
        text-decoration: underline !important;
    }
</style>

<body marginheight="0" topmargin="0" marginwidth="0" style="margin: 0px; background-color: #f2f3f8;" leftmargin="0">

    <table cellspacing="0" border="0" cellpadding="0" width="100%" bgcolor="#f2f3f8" style="@import url(https://fonts.googleapis.com/css?family=Rubik:300,400,500,700|Open+Sans:300,400,600,700); font-family: 'Open Sans', sans-serif;">
        <tr>
            <td>
                <table style="background-color: #f2f3f8; max-width:670px; margin:0 auto;" width="100%" border="0" align="center" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="height:80px;">&nbsp;</td>
                    </tr>
                    <!-- Logo -->
                    <tr>
                        <td style="text-align:center;">
                            <a href="https://virtualt.org/landing/" title="logo" target="_blank">
                                <img width="auto" height="100" src="https://admin.virtualt.org/default/logoweb.png" title="logo" alt="logo">
                            </a>
                        </td>

                    </tr>
                    <tr>
                        <td style="height:20px;">&nbsp;</td>
                    </tr>
                    <!-- Email Content -->
                    <tr>
                        <td>
                            <table width="95%" border="0" align="center" cellpadding="0" cellspacing="0" style="max-width:670px; background:#fff; border-radius:3px;-webkit-box-shadow:0 6px 18px 0 rgba(0,0,0,.06);-moz-box-shadow:0 6px 18px 0 rgba(0,0,0,.06);box-shadow:0 6px 18px 0 rgba(0,0,0,.06);padding:0 40px;">
                                <tr>
                                    <td style="height:40px;">&nbsp;</td>
                                </tr>
                                <!-- Title -->
                                <tr>
                                    <td style="padding:0 15px; text-align:center;">
                                        <h1 style="color:#1e1e2d; font-weight:400; margin:0;font-size:32px;font-family:'Rubik',sans-serif;">{{ $subject }}</h1>
                                        <span style="display:inline-block; vertical-align:middle; margin:29px 0 26px; border-bottom:1px solid #cecece; 
                                        width:100px;"></span>
                                    </td>


                                    <!-- Details Table -->

                                <tr>
                                    <td>
                                        <table cellpadding="0" cellspacing="0" style="width: 100%; border: 1px solid #ededed">
                                            <tbody>
                                                <tr>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; border-right: 1px solid #ededed; width: 35%; font-weight:500; color:rgba(0,0,0,.64)">
                                                        Codigo del Plan:
                                                    </td>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; color: #455056;">
                                                        {{ $plan->id }}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; border-right: 1px solid #ededed; width: 35%; font-weight:500; color:rgba(0,0,0,.64)">
                                                        Nombre del Plan:
                                                    </td>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; color: #455056;">
                                                        {{ $plan->nombrePlan }}
                                                    </td>
                                                </tr>


                                                <tr>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; border-right: 1px solid #ededed; width: 35%; font-weight:500; color:rgba(0,0,0,.64)">
                                                        Valor:
                                                    </td>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; color: #455056;">
                                                        {{ $plan->valor }}
                                                    </td>
                                                </tr>



                                                <tr>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; border-right: 1px solid #ededed; width: 35%; font-weight:500; color:rgba(0,0,0,.64)">
                                                        Numero de Usuarios:
                                                    </td>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; color: #455056;">
                                                        {{ $plan->numeroUsuarios }}
                                                    </td>
                                                </tr>

                                                <tr>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; border-right: 1px solid #ededed; width: 35%; font-weight:500; color:rgba(0,0,0,.64)">
                                                        Fecha de Incio:
                                                    </td>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; color: #455056;">
                                                        {{ $vinculacion->fechaEstadoInicial }}
                                                    </td>
                                                </tr>



                                                <tr>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; border-right: 1px solid #ededed; width: 35%; font-weight:500; color:rgba(0,0,0,.64)">
                                                        Fecha Final:
                                                    </td>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; color: #455056;">
                                                        {{ $vinculacion->fechaEstadoFinal }}
                                                    </td>
                                                </tr>



                                                <tr>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; border-right: 1px solid #ededed; width: 35%; font-weight:500; color:rgba(0,0,0,.64)">
                                                        Duración:
                                                    </td>
                                                    <td style="padding: 10px; border-bottom: 1px solid #ededed; color: #455056;">
                                                        {{ $plan->periodoMeses }} Meses
                                                    </td>
                                                </tr>



                                            </tbody>
                                        </table>


                                        <footer style="margin-top: 20px; font-size: 14px;">
                                            <p>Se te notificará cuando tu plan esté cerca de culminar.</p>

                                            <p>Si tienes alguna duda, por favor no dudes en contactarnos:</p>
                                            <ul>
                                                <li>Correo electrónico: <a href="mailto:correo@example.com">virtualtsoftware@virtualt.org</a></li>
                                                <li>Teléfono: +57 315 6614275 </li>
                                                <li>Visita nuestra página web: <a href="https://virtualt.org/landing/">https://virtualt.org/landing/</a></li>
                                            </ul>
                                        </footer>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="height:40px;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:20px;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="text-align:center;">
                            <p style="font-size:14px; color:#455056bd; line-height:18px; margin:0 0 0;">&copy; <strong> https://virtualt.org </strong></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>