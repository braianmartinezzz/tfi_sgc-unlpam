


Laboratorio Orientado a Aplicaciones
## Móviles

## Entrega 2

Resumen del trabajo
- Desarrollar una app utilizando React Native framework principal
- Defender el trabajo individualmente
- La app se puede presentar en un emulador o bien generando un apk
ACLARACIÓN: se puede realizar en grupo pero la defensa es individual.
- Fecha de entrega 24/11/2026 o 26/11/2026 (último día)
## Requerimientos
Desarrollar una app para el trabajo integrador con la materia Programación
Orientada a la Web, la misma tendrá como eje principal el desarrollo de una
app para la gestión de Convenios por parte de las autoridades de la
universidad.

Funcionalidades de la app:
● Login: (debe permitir múltiples accesos y MFA)
○ login via email y password
○ login via biometría del celular
○ MFA, solicitud de código que nos llegará por mail, push
notificación o sms (mail y sms se puede simular).
○ una vez que se loguea, no se pide más
● Home: box con un resumen de movimiento
## 1



○ box con de convenios firmados en el último año
○ box con de convenios vigentes
○ box con  de convenios en trámite
○ box con últimos movimientos (listado de convenios en los que
hubo movimientos), debe mostrarlo del más reciente al más
antiguo, máximo 10 movimientos.
○ Mis Pendientes (convenios por firmar)
○ cualquier otra información de resumen que parezca relevante
● Buscador de Convenios:
○ El buscador debe aparecer en el header
○ A parte debe ser una screen con un buscador avanzado, que
permita filtros, como por ejemplo por año, simil a
https://actosresolutivos.unlpam.edu.ar/
○ Cada vez que se lista el resultado de la búsqueda este debe
figurar como un árbol, teniendo en cuenta convenio padre,
convenio hijo, resoluciones asignadas a él, y todo tipo de
documentación asignada al convenio.
● Firmador de Convenios:
○ Debe existir un listado de convenios por firmar, debe mostrarlo
del más reciente al más antiguo, máximo 10 movimientos.
○ Al ingresar a un convenio debe quedar en claro, procedencia,
firmantes, fechas, convenio padre si es que lo tiene, etc.
○ Al acceder a uno de ellos, debe permitir su lectura completa del
pdf y al final tener un botón firmar
○ Debe permitir hacer zoom en la lectura
○ Debe permitir compartir el convenio, via pdf por wsp y email
○ La firma debe ser con huella digital y firma a mano en celular
○ Una vez firmado, el documento debe mostrarse firmado junto
con la fecha, hora, lat y long donde se realizó el acto de firma
## ○ Comentarios:
## 2



■ se debe poder realizar comentarios dentro del convenio,
los comentarios se van a dar entre los distintos usuarios,
solo vistos y realizados por ellos.
■ Si el comentario es ofensivo el sistema debe darse cuenta
solo y plasmar un comentario indicando solo “comentario
ofensivo”.
● Mi Perfil: La app debe contar con una sección para poder setear mi
perfil
○ tipo de letra, que debe alterar toda la app, (opciones letra chica,
mediana, grande).
○ modo claro y oscuro
○ mi foto de perfil, la misma debe ser mostrada en miniatura en la
app
● Registrar mi identidad: La app debe contar con una sección para
demostrar mi identidad
○ mi firma, debe quedar registrada para contactar, el mismo se
debe dar en un cuadro y luego debe quedar registrada la firma
como guardada en la app
○ Reconocimiento facial, al igual que mi firma debe quedar
recocida mi identidad facil
○ huella digital: idem a reconocimiento facial
● Notificaciones: El sistema debe contar con 2 tipos de notificaciones:
○ Notificaciones vía API:
■ notificación de nuevo convenio registrado
■ notificación de nuevo comentario o cambio de datos de un
convenio
■ notificación de convenio cancelado
○ Notificación vía app:
## 3



■ Cuando un convenio está para firmar y han pasado x
cantidad de horas sin que yo lo firme, la app me debe
recordar que “aún tienes un convenio por firmar”.
■ Cuando no ingresó a la app durante x cantidad de horas,
la app debe informar que “hay novedades”.
● Modo Off: cuando la app esta offline,
○ solo se pueden ver los convenios pendientes de firmar,
○ el buscador debe indicar que estas offline, al igual que
comentarios, firma de convenios y registro de identidad.
○ Si el usuario está logueado puede usar la app, si no está
logueado no puede ingresar.
● Ayuda al usuario:
○ La app debe contar con un manual del usuario, básicamente un
pequeño instructivo de como registrar mi identidad y firmar un
documento.
○ también debe contar con ayudas en cada sección aunque
puntuales.
● subir documentación:
○ subir documentación anexada a un convenio
○ solo serán pdfs
○ deben generar una marca en los metadatos, que servirá como
verificación que la documentación fue subida por la app,
indicando el usuario, convenio, fecha y hora del mismo, más
todo dato que crean necesario.
○ La subida de documentación es por la app, pero la marca del
documento se debe realizar en la api.
○ luego deben poder visualizarse, mostrando información de la
marca realizada
● Innovación: realizar una innovación relacionada a los requerimientos
## 4




Requerimientos técnicos:
a. Es importante que la app muestre una buena navegabilidad y una UX y UI adecuada y
profesional teniendo en cuenta todo lo visto en la materia, queda a libre elección el
modelo y diseño de la misma.
b. Se debe implementar una metodología de arquitectura, la cual debe ser graficada es
implementada en el código, tener en cuenta la organización de las carpetas.
c. Se debe implementar al menos en un componente, la metodología Atomic Design
d. Autentificación del usuario
a. se debe utilizar token y la api debe ser construida acorde los requerimientos
b. debe ser con tokens
e. Entregar apk, es importante que en la entrega figure el apk



## 5