# Fase 6 — Pruebas móviles

Dos partes: **tests automáticos** (`cd app && npm test`, 36 casos, corren sin celular) y **pruebas en dispositivos reales** (lo que ningún test puede confirmar: sensores, push, permisos del sistema).

## Preparación para dispositivos reales

| Requisito | Android | iOS |
|---|---|---|
| Build | `npx expo run:android` o `eas build --profile development` (Expo Go **no** sirve para push remotas) | `npx expo run:ios` o `eas build --profile development` (requiere cuenta Apple Developer) |
| API | `php artisan serve --host=0.0.0.0` y `EXPO_PUBLIC_API_URL=http://IP_DE_LA_PC:8000` en `app/.env` | igual |
| Push | `extra.eas.projectId` en `app.json` (`eas init`) + credenciales FCM | `extra.eas.projectId` + clave APNs |
| Biometría | huella o rostro cargado en el celular | Face ID / Touch ID cargado |

Pendientes de configuración que **no** se tocaron (son decisiones tuyas): `extra.eas.projectId`, `android.package` e `ios.bundleIdentifier` no están en `app.json`. Sin `projectId` el canal push del login devuelve "este dispositivo no puede recibir push". Un build *release* de Android bloquea `http://` (la API local sin HTTPS): usar un build de desarrollo o publicar la API con HTTPS.

## Matriz: qué cubre cada punto

| # | Punto | Test automático | Prueba manual en celular real |
|---|---|---|---|
| 1 | Login con correo, SMS y push | `login-y-sesion` (los 3 canales, errores) | Ingresar por cada canal. Push: llega, muestra el código; **tocarla no debe navegar** |
| 2 | Sesión expirada y renovación | `api-client` (401 → login + aviso, renovación por uso) | Dejar 10 min sin uso y tocar algo: vuelve al login con "venció por inactividad" |
| 3 | Activación y uso de biometría | `login-y-sesion`, `firma-y-biometria` | Perfil → activar (pide contraseña + huella/rostro); cerrar sesión; ingresar solo con huella. Revocar desde otro lado: debe pedir contraseña |
| 4 | Lectura offline del panel | `offline-sync` (datos por usuario) | Abrir el panel con red, activar modo avión, reabrir la app: se ve el panel con banner "Sin conexión" |
| 5 | Cola offline y sincronización | `offline-sync` (orden, red caída, 4xx, 401, 5xx) | Con la API apagada, tocar un aviso en Notificaciones; encender la API: queda leído |
| 6 | Recepción y apertura de push | `push-navegacion` | Con app abierta, en segundo plano y cerrada: llega el aviso y al tocarlo abre |
| 7 | Navegación desde avisos | `push-navegacion` (convenio / lista / sesión vencida) | Aviso de convenio → ficha. Con sesión vencida → login y luego ficha |
| 8 | Firma con ubicación y registro biométrico | `firma-y-biometria` | Firmar: pide huella/rostro (sin PIN) y ubicación; se registra con latitud/longitud |
| 9 | Confirmación de permisos | `firma-y-biometria` | Denegar ubicación → mensaje; denegar "para siempre" → botón **Abrir ajustes**; GPS apagado → aviso |
| 10 | Estados de carga, error y vacío | — (revisión visual) | Panel/convenios/notificaciones: spinner, error con API apagada, "No tenés notificaciones" |
| 11 | Android e iOS reales | — | Repetir 1–10 en al menos un Android y un iPhone; anotar modelo y versión |

## Planilla de resultados

| # | Android (modelo/versión) | iOS (modelo/versión) | Observaciones |
|---|---|---|---|
| 1 | | | |
| 2 | | | |
| 3 | | | |
| 4 | | | |
| 5 | | | |
| 6 | | | |
| 7 | | | |
| 8 | | | |
| 9 | | | |
| 10 | | | |
