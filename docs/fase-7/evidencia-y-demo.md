# Fase 7 — Evidencia de flujos y demo completa

Flujo a mostrar: **login → notificación → revisión → firma → vencimiento**. Duración objetivo: 8 a 10 minutos.

## 1. Preparación (la víspera)

| Ítem | Qué verificar |
|---|---|
| Celular | Build de desarrollo instalado (no Expo Go), huella o rostro cargados, ubicación activada, brillo alto, modo "No molestar" activo, batería > 50 %. |
| API | Levantada y accesible desde el celular (`EXPO_PUBLIC_API_URL`). Probar con 4G y con wifi de la sala si se puede. |
| Usuario de la demo | Rol con permiso de firma. Biometría ya activada en Mi perfil para mostrar el ingreso con huella. |
| Datos | 1 convenio **Aprobado, pendiente de la firma de ese usuario**; 1 notificación sin leer asociada a ese convenio; 1 convenio con **vencimiento cercano** (dentro del plazo de preaviso). Verificar contra la base cómo calcula la API el preaviso antes de cargar las fechas. |
| Respaldo | Capturas y un video corto de cada paso (ver sección 3) por si falla la red. |
| Pantalla | Espejo del celular (scrcpy en Android, QuickTime en iPhone) o proyector con cable. |

Después de cada ensayo hay que **restablecer los datos** (el convenio firmado deja de estar pendiente): tener un segundo convenio pendiente listo.

## 2. Guion

| # | Minuto | Qué se hace | Qué se dice / qué se ve | Evidencia |
|---|---|---|---|---|
| 1 | 0:00 | Abrir la app. Mostrar login y, si hay sesión previa, el ingreso con huella. | "Ingreso con segundo factor; también con huella." Mostrar el tema oscuro y "Grande" brevemente para accesibilidad. | E-01, E-02 |
| 2 | 1:30 | Ingresar con correo, elegir un canal y escribir el código (modo demostración muestra el código). | "Dos pasos: contraseña y código." | E-03 |
| 3 | 2:30 | En el panel: señalar pendientes, resumen y avisos. | "Aviso de preaviso y notificación nueva." | E-04 |
| 4 | 3:30 | Tocar la notificación **Nueva**. | "Se marca como leída y abre el convenio." | E-05 |
| 5 | 4:15 | En la ficha: estado, unidad y **vencimiento con días que faltan**. | "Vence en N días: escrito, no solo color." | E-06 |
| 6 | 5:00 | Tocar **Ir a firmar**. Abrir el PDF. | "Reviso lo que voy a firmar." | E-07 |
| 7 | 6:00 | Dibujar, **Guardar firma**, **Confirmar firma**; huella y ubicación. | "Evidencia: trazo, biometría y ubicación." | E-08, E-09 |
| 8 | 7:30 | Mensaje de firma registrada. Volver al panel; el pendiente ya no está. **Compartir constancia**. | "Gestión registra la firma administrativa aparte." | E-10 |
| 9 | 8:30 | Volver a Notificaciones y al panel: mostrar el convenio en preaviso y el contador de vencimientos. | "Cierre del ciclo: vencimiento." | E-11 |
| 10 | 9:00 | (Opcional, 1 min) Activar TalkBack/VoiceOver sobre la ficha. | "Se lee estado, vencimiento y qué hace cada botón." | E-12 |

## 3. Evidencia a capturar

Guardar en `docs/fase-7/evidencia/` (crear la carpeta) con estos nombres. Capturas de pantalla del celular, sin datos personales reales.

| Código | Archivo | Contenido |
|---|---|---|
| E-01 | `01-login.png` | Pantalla de ingreso (claro). |
| E-02 | `02-login-oscuro-grande.png` | Mismo, en modo oscuro y texto grande. |
| E-03 | `03-verificacion.png` | Elección de canal y código. |
| E-04 | `04-panel.png` | Pendientes, resumen y avisos. |
| E-05 | `05-notificaciones.png` | Lista con una "Nueva". |
| E-06 | `06-ficha-vencimiento.png` | Ficha con "Vence en N días". |
| E-07 | `07-firmador-pdf.png` | Convenio seleccionado y botón de PDF. |
| E-08 | `08-firma-guardada.png` | Firma dibujada y "Firma guardada". |
| E-09 | `09-biometria.png` | Pedido de huella o rostro. |
| E-10 | `10-firma-registrada.png` | Mensaje de firma registrada. |
| E-11 | `11-panel-vencimientos.png` | Panel con preaviso y vencidos. |
| E-12 | `12-lector-pantalla.mp4` | Video corto con TalkBack o VoiceOver. |
| — | `demo-completa.mp4` | Grabación de pantalla del recorrido entero. |
| — | `resultados-tests.txt` | Salida de `cd app && npm test`. |

## 4. Plan B

| Si falla… | Hacer |
|---|---|
| La red o la API | Mostrar el video `demo-completa.mp4` y las capturas; explicar que el panel también funciona sin conexión (modo avión: banner "Sin conexión"). |
| El push | Entrar a Notificaciones a mano; el resultado es el mismo. |
| La huella | Tener a mano la cuenta con contraseña y un segundo celular. |
| La ubicación | Activar el GPS antes; si la app ofrece "Abrir ajustes", mostrarlo como parte del flujo de permisos. |

## 5. Lista para el último ensayo

- [ ] `cd app && npm test` pasa y se guardó la salida.
- [ ] Recorrido completo cronometrado, menos de 10 minutos.
- [ ] Datos restablecidos tras el ensayo.
- [ ] Capturas y video guardados y respaldados fuera del celular.
- [ ] `errores-y-limitaciones.md` revisado: se puede explicar cada limitación abierta.
