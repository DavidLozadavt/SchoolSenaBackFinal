# Módulo Seguimiento de Aspirantes — Integración autónoma con WhatsApp Cloud API (Meta)

Este módulo es **completamente independiente**. No usa `INTELLITAXI_API_BASE`, `company_id`,
`TelecomManager`, `telecom_configs` de otro backend ni ningún endpoint del proyecto Tax Belalcázar.
Todas las credenciales se administran en **esta** base de datos y el envío llama **directamente**
a la API de Meta.

---

## 1. Archivos creados

| Archivo | Propósito |
|---|---|
| `database/migrations/2026_07_16_120000_create_telecom_configs_table.php` | Crea la tabla local `telecom_configs` con las credenciales de WhatsApp Cloud API. |
| `app/Models/TelecomConfig.php` | Modelo Eloquent de la configuración. Método `activa()` devuelve la config vigente. Oculta `accessToken`/`appSecret` en JSON. |
| `app/Services/WhatsappCloudService.php` | Servicio que lee **solo** la config local y llama a `graph.facebook.com`. Métodos `enviarTexto()` y `enviarPlantilla()`. |
| `app/Http/Controllers/TelecomConfigController.php` | CRUD de la configuración + endpoint `probar` para test de envío. |
| `app/Http/Controllers/WhatsappWebhookController.php` | Webhook propio: `verify()` (GET) y `receive()` (POST) para recibir eventos de Meta directamente. |

## 2. Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Http/Controllers/SeguimientoAspiranteController.php` | `enviarWhatsApp()` ahora envía realmente vía `WhatsappCloudService`, armando las variables de plantilla `nombre/programa/ficha/centro`. |
| `routes/api.php` | Rutas **públicas** del webhook `webhooks/meta`, imports de los nuevos controladores y CRUD `telecom-config` bajo `auth:api`. |
| `database/migrations/..._create_seguimiento_aspirantes_table.php` | Se agregaron columnas `fecha_respuesta` y `wa_message_id`; se corrigió el import faltante de `Log` (bug preexistente). |

> No se modificó ningún archivo de otros módulos ni configuración de proyectos externos.

---

## 3. Estructura de la tabla `telecom_configs`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Identificador. |
| `provider` | string, default `meta` | Proveedor del canal. |
| `whatsappEnabled` | boolean, default `true` | Estado del canal de WhatsApp. |
| `nombre` | string, null | Nombre descriptivo (ej. "WhatsApp Institucional"). |
| `accessToken` | text | Token permanente/de sistema de Meta. |
| `phoneNumberId` | string | ID del número de teléfono (Phone Number ID). |
| `businessAccountId` | string, null | WhatsApp Business Account ID (WABA). |
| `appId` | string, null | App ID de Meta. |
| `verifyToken` | string | Token de verificación del webhook (lo defines tú). |
| `appSecret` | string, null | Opcional. Para validar la firma `X-Hub-Signature-256`. |
| `webhookUrl` | string, null | URL pública del callback registrada en Meta. |
| `graphVersion` | string, default `v23.0` | Versión del Graph API a usar. |
| `activo` | boolean, default `true` | Solo una configuración activa a la vez. |
| `created_at` / `updated_at` | timestamp | Auditoría. |

> La tabla `seguimiento_aspirantes` recibió además: `fecha_respuesta` (dateTime), `wa_message_id` (ID del mensaje enviado, para correlacionar estados), `estado_envio` (`sent`/`delivered`/`read`/`failed`) y `error_envio` (motivo si falla).

> **Credenciales 100% en BD:** ni `WhatsappCloudService` ni el webhook usan `env()`/`config()`. `verifyToken` y `appSecret` se leen desde `telecom_configs` (BD).

Ejecutar la migración:

```bash
php artisan migrate
```

---

## 4. Flujo completo

### Envío (saliente)
1. El frontend llama `POST /api/seguimiento-aspirantes/enviar-whatsapp` con `ids` + `mensaje` (o `plantilla`).
2. `SeguimientoAspiranteController::enviarWhatsApp` instancia `WhatsappCloudService`.
3. El servicio lee `TelecomConfig::activa()` (config **local**).
4. Por cada aspirante, normaliza el celular (Colombia → prefijo `57`) y hace:
   ```
   POST https://graph.facebook.com/v23.0/{phoneNumberId}/messages
   Authorization: Bearer {accessToken}
   ```
5. Si Meta responde OK: el aspirante pasa a `estado=Enviado`, se actualiza `ultimo_envio` y `cantidad_envios++`.
6. Se devuelve un resumen `{ enviados, fallidos, errores[] }`.

> Para **contacto en frío** se debe enviar `plantilla` (plantilla aprobada en Meta). El `mensaje` de texto libre solo funciona dentro de la ventana de 24h de atención.

### Recepción (entrante) — Webhook
1. Meta valida la URL con un `GET` (handshake). `verify()` compara `hub.verify_token` contra `verifyToken` de la config activa y devuelve el `hub.challenge`.
2. Cuando un aspirante responde, Meta hace `POST` a la misma URL.
3. `receive()` **valida la firma** `X-Hub-Signature-256` con `appSecret` (si está configurado), recorre `entry[].changes[].value.messages[]` y extrae `phone (from)`, `button_id`, `text` y `message_id`.
4. Busca el aspirante por los últimos 10 dígitos del celular y actualiza `respuesta`, `fecha_respuesta`, `wa_message_id` y `estado`.
5. Lógica SI/NO: si la respuesta (botón o texto) es "Sí/si/interesado" → `estado=SI`; si es "No/no_interes" → `estado=NO`; en otro caso `estado=Respondido`. Siempre responde `200`.

### Estados de entrega (statuses)
Meta también envía eventos `statuses` (no son mensajes del usuario). `receive()` los procesa en `procesarEstado()`:
- Correlaciona por `wa_message_id` (el ID guardado al enviar).
- Actualiza `estado_envio` = `sent` / `delivered` / `read` / `failed`.
- Si `failed`, guarda el motivo en `error_envio`.

> Sin FSM, sin sesiones, sin chatbot: es únicamente un procesador de campañas.

---

## 5. Ejemplo de configuración

Crear la configuración (autenticado):

```http
POST /api/telecom-config
Authorization: Bearer {tu_token_de_app}
Content-Type: application/json

{
  "provider": "meta",
  "whatsappEnabled": true,
  "nombre": "WhatsApp Institucional",
  "accessToken": "EAAG...token-permanente-de-Meta...",
  "phoneNumberId": "123456789012345",
  "businessAccountId": "987654321098765",
  "appId": "1122334455667788",
  "verifyToken": "school_sena_verify_2026",
  "appSecret": "opcional_app_secret",
  "webhookUrl": "https://TU-DOMINIO.com/api/webhooks/meta",
  "graphVersion": "v23.0",
  "activo": true
}
```

Probar el envío:

```http
POST /api/telecom-config/probar
{ "numero": "3001234567", "mensaje": "Prueba de conexión ✅" }
```

Endpoints CRUD disponibles (todos bajo `auth:api`):
- `GET    /api/telecom-config` — listar
- `GET    /api/telecom-config/activa` — config activa
- `POST   /api/telecom-config` — crear
- `PUT    /api/telecom-config/{id}` — actualizar
- `DELETE /api/telecom-config/{id}` — eliminar
- `POST   /api/telecom-config/probar` — test de envío

---

## Integración opcional con la IA "Lyra" (server-to-server)

El backend es autónomo y procesa SI/NO por sí mismo en `/api/webhooks/meta`. Si además se usa la IA Lyra como procesador, ésta ya **no** depende de un Telecom Manager ni de `company_id`: llama directamente a dos endpoints de este backend.

| Endpoint | Uso |
|---|---|
| `POST /api/sena/aspirante/update-response` | Lyra reporta la respuesta (`{ phone, response, message_id }`); se busca el aspirante por celular y se guarda SI/NO + `fecha_respuesta`. |
| `POST /api/sena/aspirante/send` | Lyra pide enviar un texto (`{ to, message }`); se envía vía WhatsApp Cloud API con la config local. |

> Cambios en Lyra: `config/aspirantes_config.py` (apunta a `SCHOOLSENA_API_BASE`, `company_id` opcional), `whatsapp_aspirantes.py` (filtro company_id opcional), `aspirantes_handler.py` y `aspirantes_whatsapp_service.py` (llaman a los endpoints de SchoolSena en lugar del Telecom Manager). Ya no se usan `INTELLITAXI_API_BASE`/`ASPIRANTES_COMPANY_ID`/`/admin/telecom/send`.

## Permiso GESTION_TELECOM_CONFIG

- **Constante:** `PermissionConst::GESTION_TELECOM_CONFIG` (`app/Permission/PermissionConst.php`).
- **Registro:** `PermissionSeeder` + `Permission::firstOrCreate(...)` en la migración `telecom_configs` (mismo mecanismo que el resto del proyecto).
- **Rol por defecto:** asignado únicamente a **Admin**.
- **Rutas protegidas** (bajo `auth:api` + `permission:GESTION_TELECOM_CONFIG`):
  ```
  GET    /api/telecom-config
  GET    /api/telecom-config/activa
  POST   /api/telecom-config
  PUT    /api/telecom-config/{id}
  DELETE /api/telecom-config/{id}
  POST   /api/telecom-config/probar
  ```
  El middleware `App\Http\Middleware\Permisions` devuelve **HTTP 403** a quien no tenga el permiso.
- **Menú:** el nodo de menú "TelecomConfig / Configuración WhatsApp" debe declarar `requiredPermissions: ['GESTION_TELECOM_CONFIG']`; al no estar en los permisos del usuario, el frontend lo oculta automáticamente (mismo patrón de menú dinámico del proyecto).

> El webhook público `/api/webhooks/meta` **no** lleva este permiso: lo llama Meta, no un usuario del panel.

## 6. URL definitiva del webhook para Meta

En el panel de Meta (WhatsApp → Configuration → Webhook) registra:

| Campo | Valor |
|---|---|
| **Callback URL** | `https://TU-DOMINIO.com/api/webhooks/meta` |
| **Verify Token** | el mismo valor guardado en `verifyToken` (ej. `school_sena_verify_2026`) |
| **Método GET** | validación (Meta envía `hub.mode`, `hub.verify_token`, `hub.challenge`) |
| **Método POST** | recepción de mensajes y estados |
| **Campos suscritos** | `messages` |

> La URL **debe ser pública y HTTPS**. En local (`http://127.0.0.1:8000/api/webhooks/meta`)
> Meta no puede alcanzarla; usa un túnel (ngrok/cloudflared) o el dominio de producción.
> Ejemplo con túnel: `https://xxxx.ngrok-free.app/api/webhooks/meta`.

**Rutas exactas expuestas por este backend:**
```
GET  /api/webhooks/meta   -> WhatsappWebhookController@verify   (validación)
POST /api/webhooks/meta   -> WhatsappWebhookController@receive  (recepción)
```
