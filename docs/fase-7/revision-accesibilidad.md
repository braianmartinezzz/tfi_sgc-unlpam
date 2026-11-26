# Fase 7 — Revisión de accesibilidad

Alcance: app móvil (`app/`). Criterio base: WCAG 2.1 AA y las guías de TalkBack (Android) y VoiceOver (iOS).
Lo que se **cambió en código** está en la primera parte; lo que **hay que comprobar en un celular real** está en la lista de verificación. Ninguna revisión con lector de pantalla se hizo todavía en dispositivo: los tests automáticos solo cubren contraste y textos de vencimiento.

## 1. Qué se cambió

| Tema | Cambio | Dónde |
|---|---|---|
| Etiquetas y hints | Todos los botones de las pantallas de login, verificación, panel, notificaciones, ficha, firmador y perfil dicen **qué hacen** (`accessibilityHint`) y su estado (`disabled`, `busy`, `checked`, `expanded`). | `src/app/*`, `src/components/**` |
| Estados que dependían del color | La notificación sin leer ahora dice "Nueva" por escrito y el lector la anuncia como "Sin leer". El vencimiento de la ficha muestra "Vence en N días". | `notificaciones.tsx`, `notificaciones-card.tsx`, `convenio/[id].tsx` |
| Encabezados | Títulos de pantalla y de tarjetas son encabezados (`header`), así se puede saltar de sección en sección. | `txt.tsx`, `card.tsx`, `encabezado-pantalla.tsx` |
| Ruido para el lector | Logo, avatar con foto, "+/−" y viñetas de la ayuda, subtítulo repetido del login: ocultos al lector. | `panel-header.tsx`, `ayuda.tsx`, `index.tsx` |
| Avisos | Los errores del login y de la verificación se **leen en voz alta** al aparecer; también "Firma guardada", "Código enviado", "Todas las notificaciones quedaron como leídas" y los conflictos de versión. | `src/lib/accesibilidad.ts` (`anunciar`) |
| Contraste | Rojo de error en modo claro (`#d13b3b` → `#b9302e`), placeholder del buscador en modo oscuro (`#8e919d` → `#9a9da9`) y rojo de error del login (era fijo `#D93025`: 3,52:1 en modo oscuro; ahora usa el color del tema). | `sgc-theme.ts`, `index.tsx`, `verificacion-mfa.tsx` |
| Botón invisible | "Agregar foto de perfil" tenía texto blanco sobre fondo blanco. Ahora lleva fondo institucional. | `perfil.tsx` |
| Tamaño de texto | "Chica / Mediana / Grande" ahora también alcanza a los textos que fijaban su propio `fontSize` (login, verificación); ningún texto baja de 12; se respeta el tamaño de fuente del sistema hasta ×1,6. | `txt.tsx` |
| Áreas táctiles | Mínimo de 44 en volver, avatar, buscador, filtros, enlaces, menú de perfil, "marcar todas", "olvidé mi contraseña". El interruptor de tema amplía su área sin cambiar su forma. | varios |
| Teclado y dispositivos externos | `PressableA11y` dibuja un **borde azul de foco** al navegar con teclado, mouse o switch access. En el login, "Siguiente" del correo pasa a la contraseña. | `pressable-a11y.tsx`, `index.tsx` |
| Firma | El recuadro es un elemento único para el lector; aparece un texto de estado ("Dibujá tu firma…" / "Firma guardada…"). **"Limpiar" ahora también invalida la firma guardada** (antes se podía confirmar un dibujo ya borrado). | `firmador.tsx` |
| Ayuda | Dos secciones nuevas: "Notificaciones y vencimientos" y "Accesibilidad". | `ayuda.tsx` |

## 2. Contraste medido (razón de contraste, mínimo AA = 4,5:1)

`__tests__/accesibilidad.test.ts` verifica 32 pares texto/fondo (claro y oscuro) y todos los colores de estado. Valores relevantes:

| Par | Antes | Ahora |
|---|---|---|
| Rojo de error sobre gris de página (claro) | 4,21 | ≥ 5,14 |
| Rojo de error sobre fila alternada (claro) | 4,11 | ≥ 5,14 |
| Placeholder del buscador (oscuro) | 4,37 | 5,08 |
| Error del login sobre tarjeta (oscuro) | 3,52 | 7,41 |
| Texto secundario sobre fila alternada (claro) | 5,12 | 5,12 |
| Blanco sobre rojo institucional | 4,77 | 4,77 |

Los bordes de color (verde, naranja, azul, rojo) son decorativos: lo que informa también está escrito.

## 3. Lista de verificación en dispositivo

Marcar en cada celular (ver planilla al final). Android: TalkBack (Ajustes → Accesibilidad). iPhone: VoiceOver (Ajustes → Accesibilidad).

| # | Pantalla | Qué debe pasar |
|---|---|---|
| 1 | Login | El foco recorre: Correo → Contraseña → Mostrar contraseña (interruptor) → Iniciar sesión → Olvidé mi contraseña. Un error de credenciales se lee solo. El subtítulo repetido no se lee. |
| 2 | Verificación | Cada canal dice "Recibir el código por … Envía el código". Al enviar se anuncia "Te enviamos un código por …". En modo demostración el código se lee dígito por dígito. |
| 3 | Panel | Se puede saltar entre encabezados (Panel, Mis pendientes, Avisos…). Las cajas de resumen se leen completas, incluida la nota "N vencido(s) sin archivar". Cada pendiente dice título, unidad, si está demorado y desde cuándo. |
| 4 | Menú del perfil | Al abrirlo el lector queda dentro del menú (no lee el panel de atrás). Cada opción explica qué hace. |
| 5 | Notificaciones | Una sin leer se anuncia "Sin leer. …". Tocarla abre el convenio. "Marcar todas" anuncia el resultado. |
| 6 | Ficha del convenio | Estado, tipo, unidad y vencimiento se leen como "Etiqueta: valor". El vencimiento incluye los días que faltan. |
| 7 | Firmador | Lista → convenio → "Abrir PDF" → dibujar → "Guardar firma" (se anuncia) → "Confirmar firma" (hint correcto según haya o no firma). Con TalkBack: doble toque y mantener para dibujar en el recuadro. |
| 8 | Conflicto de versión | Se anuncia "Conflicto de versión…"; los tres botones explican su efecto. |
| 9 | Mi perfil | Claro/Oscuro y Chica/Mediana/Grande se leen como opciones de un grupo, con la elegida marcada. |
| 10 | Texto grande | Con el tamaño del sistema al máximo y "Grande" en la app, nada se corta ni se superpone en login, panel y firmador. |
| 11 | Teclado externo | Tab / Shift+Tab recorren los elementos en orden lógico; Enter o Espacio activan; el elemento enfocado tiene borde azul visible. |
| 12 | Modo oscuro | Revisar a ojo errores, placeholders y estados. |

## 4. Planilla de resultados

| # | Android (modelo/versión + TalkBack) | iOS (modelo/versión + VoiceOver) | Teclado externo | Observaciones |
|---|---|---|---|---|
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |
| 4 | | | | |
| 5 | | | | |
| 6 | | | | |
| 7 | | | | |
| 8 | | | | |
| 9 | | | | |
| 10 | | | | |
| 11 | | | | |
| 12 | | | | |

## 5. Pantallas que NO se revisaron en esta pasada

`convenios.tsx` (lista y búsqueda), `movimientos.tsx`, `identidad.tsx`, `escanear-qr.tsx`, `proximamente.tsx`, `distribucion-cards.tsx`, `movimientos-card.tsx`, `ajuste-biometria.tsx`, `hint-row.tsx`. Heredan lo hecho en `Txt` (tamaño mínimo, escala, encabezados) pero sus botones y campos no tienen etiquetas ni hints revisados. Ver `errores-y-limitaciones.md` (L-08).
