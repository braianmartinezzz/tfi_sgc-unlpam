# Fase 7 — Pruebas con usuarios representativos

Objetivo: comprobar con personas reales que se puede **ingresar, enterarse de un aviso, revisar un convenio, firmarlo y ver su vencimiento** desde el celular, y detectar dónde se traban. Esto no lo puede reemplazar ningún test automático: el documento es el protocolo y la planilla; los resultados los carga el equipo.

## Participantes (mínimo 5)

| Perfil | Por qué | Cantidad |
|---|---|---|
| Rol que firma (Gestión o Administración) | Es quien usa el firmador | 1–2 |
| Rol de unidad académica (no firma) | Usa panel, avisos y ficha | 1–2 |
| Persona con baja visión o que usa texto grande | Prueba "Grande" y el tamaño del sistema | 1 |
| Persona que usa lector de pantalla (TalkBack/VoiceOver) | Prueba etiquetas y orden de lectura | 1 |
| Persona que usa teclado externo o switch access | Prueba foco y orden | 1 (opcional) |

Con 5 personas se detecta la mayoría de los problemas de uso (regla de Nielsen); si participa alguien con lector de pantalla, no hace falta sumar más del mismo perfil.

## Preparación

- Celular con la app instalada (build de desarrollo, no Expo Go si se prueba push) y API en el entorno de demo.
- Cuentas de prueba por rol, con al menos: 1 convenio pendiente de firma, 1 notificación sin leer y 1 convenio cercano a vencer.
- Consentimiento verbal para anotar y, si se quiere, grabar pantalla. No grabar datos reales.
- Una persona facilita y otra anota. El facilitador **no ayuda** salvo que pasen 3 minutos sin avance.

## Tareas

| # | Consigna (se lee tal cual) | Éxito | Tiempo esperado |
|---|---|---|---|
| T1 | "Ingresá a la aplicación con este usuario." | Llega al panel (con correo/contraseña + código, o con huella) | 1 min 30 |
| T2 | "¿Tenés algún aviso nuevo? Abrilo." | Abre una notificación sin leer y llega a la ficha | 1 min |
| T3 | "Decime el estado de este convenio y cuándo vence." | Dice estado y vencimiento (fecha y días que faltan) | 1 min |
| T4 | "Firmá este convenio." | Dibuja, guarda, confirma con huella y llega al mensaje de firma registrada | 3 min |
| T5 | "Marcá todas las notificaciones como leídas." | Lo logra desde Notificaciones | 1 min |
| T6 | "Hacé el texto más grande." | Cambia a "Grande" en Mi perfil | 1 min |

## Qué anotar por tarea

Éxito (sí / con ayuda / no), tiempo, errores (toques equivocados, vueltas atrás), frases textuales de la persona y gravedad del problema: **1** molestia, **2** demora, **3** impide terminar.

## Cuestionario final (escala 1 a 5, 1 = muy en desacuerdo, 5 = muy de acuerdo)

Adaptación breve de la escala SUS:

1. Me gustaría usar esta aplicación con frecuencia.
2. Me resultó innecesariamente compleja.
3. Me resultó fácil de usar.
4. Necesitaría ayuda de una persona técnica para usarla.
5. Las funciones están bien integradas.
6. Hay demasiada inconsistencia entre pantallas.
7. La mayoría de la gente aprendería a usarla rápido.
8. Me resultó incómoda de usar.
9. Me sentí seguro/a usándola.
10. Tuve que aprender muchas cosas antes de poder usarla.

Puntaje SUS: ítems impares (puntaje − 1) + ítems pares (5 − puntaje); sumar y multiplicar por 2,5. Referencia: 68 es el promedio general; más de 80 es muy bueno.

## Planilla de resultados

| Participante | Perfil | Dispositivo | T1 | T2 | T3 | T4 | T5 | T6 | SUS | Problemas (gravedad) |
|---|---|---|---|---|---|---|---|---|---|---|
| P1 | | | | | | | | | | |
| P2 | | | | | | | | | | |
| P3 | | | | | | | | | | |
| P4 | | | | | | | | | | |
| P5 | | | | | | | | | | |

## Cierre

Los problemas de gravedad 2 y 3 pasan a `errores-y-limitaciones.md` con un ID nuevo y se priorizan antes de la presentación.
