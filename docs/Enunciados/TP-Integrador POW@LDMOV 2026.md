



Carreras: ANALISTA PROGRAMADOR & INGENIERÍA EN SISTEMAS
Materia: PROGRAMACIÓN ORIENTADA A LA WEB & LABORATORIO DE
## APLICACIONES MÓVILES
## TRABAJO INTEGRADOR FINAL




## PROGRAMACIÓN ORIENTADA A LA WEB
## &
## LABORATORIO DE APLICACIONES MÓVILES


## TRABAJO INTEGRADOR FINAL

Sistema de Gestión de Convenios de la Universidad Nacional de La Pampa









Carreras: ANALISTA PROGRAMADOR & INGENIERÍA EN SISTEMAS
Materia: PROGRAMACIÓN ORIENTADA A LA WEB & LABORATORIO DE
## APLICACIONES MÓVILES
## TRABAJO INTEGRADOR FINAL


## Introducción


El   presente   Trabajo   Práctico   Integrador   (TPI)   corresponde   a   las   asignaturas
Programación Orientada a la Web y Laboratorio de Dispositivos Móviles y propone un
proyecto común para integrar los saberes de ambas cátedras.

Los equipos diseñarán y desarrollarán desde cero un sistema institucional compuesto
por una plataforma web de gestión y una aplicación móvil, conectadas a una misma API
REST, para administrar convenios de la Universidad Nacional de La Pampa (UNLPam).

La  propuesta  abordará  convenios  de  colaboración  y  de  pasantías,  sus  instrumentos
marco    y    específicos,    adendas,    renovaciones,    partes,    personas    vinculadas,
documentación,   revisión,   firma   registrada,   plazos   y   estadísticas.   Se   trabajará
exclusivamente  con  datos  ficticios.  El  alcance  académico  no  incluye  firma  digital,
validación  jurídica,  OCR,  envío  de  correos  ni  conexión  con  sistemas  reales  de  la
## Universidad.



Alcance y Objetivos Generales

El objetivo principal es que los equipos apliquen los contenidos de ambas asignaturas a
un  caso  de  estudio  institucional,  identificando  necesidades,  definiendo  la  solución  y
construyendo una versión funcional con decisiones de diseño justificadas.

Será  desarrollado  en  el  ámbito  de  la  asignatura  Programación  Orientada  a  la  Web  y
Laboratorio  de  Dispositivos  Móviles  pertenecientes  a  la/s  carrera/s  de  Ingeniería  en
Sistemas y/o Analista Programador de la Facultad de Ingeniería de la UNLPam.

El  trabajo  integrador  tiene  como  alcance  el  desarrollo  de  un sistema  multiplataforma
que contemple:

▪ Frontend  Web:  Desarrollado  con React/Next.js,  con  interfaz  adaptable  a
escritorio, tabletas y móviles.
▪ Frontend   Móvil:   desarrollado   con React  Native, con   navegación   y
funcionalidades nativas pertinentes al caso.
▪ Backend: API  REST  desarrollada  en  PHP/Laravel.  Como  alternativa,  se  podrá
utilizar  Node.js  para  el  servidor,  manteniendo  la  exposición  de  servicios  y  las
responsabilidades de backend documentadas.
▪ Base de datos: MySql

## Objetivos Específicos
▪ Analizar  el  dominio  y  modelar  desde  cero  convenios  marco  y  específicos  de
colaboración y pasantía, adendas, renovaciones y sustituciones..
▪ Construir el cliente web con React/Next.js y la aplicación móvil con React Native,
compartiendo reglas de negocio mediante una API REST.





Carreras: ANALISTA PROGRAMADOR & INGENIERÍA EN SISTEMAS
Materia: PROGRAMACIÓN ORIENTADA A LA WEB & LABORATORIO DE
## APLICACIONES MÓVILES
## TRABAJO INTEGRADOR FINAL

▪ Desarrollar una API REST en PHP/Laravel para la gestión de datos.
▪ Incorporar  persistencia  local  para  el  trabajo  sin  conexión: (IndexedDB en  la
versión web y almacenamiento móvil local en React Native).
▪ Sincronizar con el servidor los datos pendientes y resolver de forma controlada
conflictos de actualización o conectividad.
▪ Aplicar seguridad, control de acceso por rol y unidad, protección de documentos,
accesibilidad, usabilidad y privacidad.
▪ Integrar  el  trabajo  de  las  dos  asignaturas,  con  planificación  grupal,  repositorio
Git y documentación técnica y de diseño.



## Requerimientos Funcionales

▪ Tipos  de  instrumento: registrar  y  consultar  convenios  marco  y  específicos  de
colaboración, convenios marco y específicos de pasantía y adendas.
▪ Ficha del convenio: incluir código único, título, tipo, unidad responsable, objeto,
alcance,   obligaciones,   condiciones   de   rescisión,   marco   legal,   resolución,
expediente  o  referencia,  condiciones  de  renovación,  confidencialidad,  notas  y
fechas de firma, inicio, vencimiento y preaviso.
▪ Partes   y   representantes: registrar   las   instituciones   firmantes,   incluida   la
UNLPam   o   la   unidad   académica   que   corresponda,   y   los   datos   de   sus
representantes. Antes de aprobar un convenio deben existir al menos dos partes.
▪ Relaciones  entre  instrumentos: vincular  un  específico  con  uno  o  más  marcos
compatibles; vincular una adenda con el instrumento que modifica; y relacionar
renovaciones  o  sustituciones  con  sus  antecedentes.  Cada  nuevo  instrumento
mantiene código y ficha propios.
▪ Personas   y   pasantías: registrar   personas   vinculadas,   rol   y   período.   Para
pasantías,  documentar  los  datos  complementarios  pertinentes,  como  carrera,
tutorías, carga horaria, asignación o seguro, usando datos ficticios.
▪ Roles  y  alcance: contemplar  administración,  gestión,  revisión  y  consulta.  Los
permisos y las consultas deben respetar el alcance global o la unidad asignada
a cada usuario.
▪ Documentos: adjuntar  archivos  PDF  de  hasta  20  MB,  identificados  como
principal,  anexo,  dictamen,  resolución  u  otro.  Mantener  las  cargas  anteriores,
verificar  tipo  y  tamaño,  registrar  integridad  mediante  SHA-256  y  proteger  los
archivos contra acceso directo.
▪ API  REST: documentar  y  desarrollar  recursos  como  /auth,  /users,  /units,
## /agreements, /parties, /people, /documents, /agreements/{id}/links, /dashboard
y /alerts, con métodos, validaciones, respuestas y errores definidos.
▪ Seguridad  y  permisos: requerir  autenticación  para  todas  las  funciones;  validar
autorización  en  el  servidor  para  fichas,  búsquedas,  estadísticas  y  descargas;
proteger operaciones con controles de sesión y CSRF o mecanismo equivalente.
▪ Circuito   de   estados: implementar   borrador,   revisión,   aprobado,   firmado,
rescindido   y   archivado.   Registrar   cada   transición   y   su   fundamento.   La





Carreras: ANALISTA PROGRAMADOR & INGENIERÍA EN SISTEMAS
Materia: PROGRAMACIÓN ORIENTADA A LA WEB & LABORATORIO DE
## APLICACIONES MÓVILES
## TRABAJO INTEGRADOR FINAL

aprobación  es  administrativa  y  no  equivale  a  firma  digital  ni  certificación  de
legalidad.
▪ Acceso  privado: tanto  la  web  como  la  aplicación  móvil  serán  de  uso  interno  y
requerirán  autenticación.  No  se  desarrollará  un  portal  público  ni  se  expondrán
convenios, documentos o datos personales sin autorización.
▪ Presentación de la información: ofrecer un panel y fichas legibles que permitan
reconocer  tipo,  unidad,  partes,  estado,  vigencia,  antecedentes  y  documentos
asociados.
o Registro  de  convenios: ofrecer  búsqueda  por  texto,  filtros  por  tipo,
estado, unidad y fechas, paginación y acceso a la ficha de detalle.
o Panel de estadísticas: informar cantidades por estado y tipo, convenios
firmados y vigentes, distribución por unidad y año de firma, y cantidad de
personas únicas dentro del alcance autorizado.
o Plazos: calcular  vencimiento  y  fecha  de  preaviso  a  partir  de  los  datos
registrados;  mostrar  instrumentos  próximos  a  vencer  y  vencidos.  La
ausencia   de   fecha   final   debe   presentarse   como   “sin   vencimiento
registrado”.
o Exportación: permitir  exportar  en  CSV  los  resultados  filtrados  que  el
usuario esté autorizado a consultar, neutralizando contenido que pudiera
ejecutarse como fórmula en una planilla.
▪ Observaciones de revisión: permitir que el equipo revisor devuelva un convenio
con observaciones vinculadas al trámite y a su estado, sin habilitar comentarios
públicos.
o Trazabilidad de observaciones: conservar responsable, fecha, contenido
y resolución de cada observación, junto con la respuesta o corrección del
equipo gestor.
o Panel de revisión: permitir revisar la ficha y los documentos, aprobar o
devolver para corrección, y mostrar con claridad el estado del trámite y
las observaciones pendientes.
o Auditoría  y  concurrencia: registrar  acciones  relevantes,  cambios  de
estado  y  descargas.  Impedir  que  una  edición  basada  en  una  versión
anterior sobrescriba silenciosamente cambios más recientes.
▪ Notificaciones: incorporar  avisos  automáticos  relacionados  con  el  circuito  de
convenios, sus vencimientos y los preavisos configurados.
o Disparadores: generar un aviso cuando se asigne una revisión, cambie el
estado  de  un  instrumento  o  un  vencimiento/preaviso  ingrese  en  el
período de alerta definido.
o Canales: presentar alertas dentro de la interfaz web y notificaciones en
la aplicación móvil; se admite notificación local o push según la solución
técnica del grupo.
o Contenido mínimo del aviso: código y título del convenio, tipo, estado o
fecha  relevante  y  una  acción  o  enlace  que  conduzca  a  la  pantalla
autorizada correspondiente.
▪ Innovación: proponer e implementar al menos una mejora pertinente al dominio,
explicar la necesidad que resuelve y validar su utilidad con usuarios o escenarios
de prueba.






Carreras: ANALISTA PROGRAMADOR & INGENIERÍA EN SISTEMAS
Materia: PROGRAMACIÓN ORIENTADA A LA WEB & LABORATORIO DE
## APLICACIONES MÓVILES
## TRABAJO INTEGRADOR FINAL



Requerimientos Funcionales de la app

Requerimientos Específicos de la Aplicación Móvil

▪ Sostenibilidad  institucional  (adaptación  del  requisito  de  monetización):  por
tratarse de una herramienta universitaria pública, no se habilitarán cobros reales.
La app deberá incluir una pantalla o módulo simulado que represente el valor del
sistema —por  ejemplo,  tiempo  o  tareas  administrativas  que  podría  ahorrar— y
explicite los supuestos utilizados.
▪ Implementar al menos dos mecanismos de identificación o desbloqueo entre las
siguientes opciones:
o Contraseña de acceso a la cuenta.
o Patrón o PIN de desbloqueo local de la aplicación.
o Autenticación  biométrica  disponible  en  el  dispositivo,  cuando  este  la
soporte.
▪ Resolver  la  navegación  móvil  con  patrones  apropiados  al  sistema  y  aplicar
criterios  de  UI/UX:  jerarquía,  consistencia,  retroalimentación,  prevención  de
errores y accesibilidad.
▪ Aplicar Atomic Design en al menos una sección, documentando la composición
de átomos, moléculas y organismos reutilizables.
▪ Permitir  alternar  entre  modo  claro  y  modo  oscuro,  conservando  contraste  y
legibilidad.
▪ Incorporar lector QR para leer un código asociado a un convenio y abrir su ficha
si el usuario tiene permiso. Si no se reconoce un código en un minuto, cerrar la
pantalla  de  lectura  y  mostrar  un  mensaje  claro.  El  QR  no  debe  contener  datos
privados; debe usar una referencia segura al registro.
▪ Incorporar una función contextual de geolocalización optativa para reconocer el
ingreso  a  una  sede  o  dependencia  de  la  UNLPam  y  mostrar  una  bienvenida  o
acceso rápido a los convenios de esa unidad. Solicitar permiso, ofrecer selección
manual cuando no se autorice y no realizar seguimiento continuo de la ubicación.



## Requerimientos No Funcionales

▪ Escalabilidad: organizar el sistema en módulos para permitir incorporar nuevos
tipos de instrumentos, unidades, estados o categorías documentales.
▪ Mantenibilidad: entregar código documentado, configuración de ejemplo sin
secretos, control de versiones en Git y pasos reproducibles de instalación y
ejecución.
▪ Compatibilidad: garantizar uso en navegadores modernos y dispositivos
móviles de distintas resoluciones; probar la app en emulador o dispositivo
compatible.





Carreras: ANALISTA PROGRAMADOR & INGENIERÍA EN SISTEMAS
Materia: PROGRAMACIÓN ORIENTADA A LA WEB & LABORATORIO DE
## APLICACIONES MÓVILES
## TRABAJO INTEGRADOR FINAL

▪ Rendimiento: apuntar a tiempos de respuesta inferiores a 3 segundos para las
vistas principales con datos de prueba, usando paginación, consultas
adecuadas y carga diferida cuando corresponda.


Indagación Previa y Marco de Referencia

Antes  de  programar,  cada  grupo  realizará  una  indagación  sobre  la  gestión  de
convenios,  pasantías  y  documentos  en  universidades  y  organismos  públicos.  El
propósito es comprender actores, circuitos, datos, permisos y problemas de uso, y
fundamentar la solución sin copiar sistemas existentes.


Modelos de Referencia

Se deberán relevar al menos dos referencias, que pueden ser plataformas de
gestión documental o de convenios, circuitos de pasantías universitarias, registros
de universidades públicas y formularios o procedimientos institucionales
disponibles para consulta.

▪ Sistemas   de   gestión   de   convenios   o   cooperación   entre   universidades   y
organismos públicos.
▪ Sistemas   de   gestión   documental,   expedientes   o   seguimiento   de   trámites
administrativos.
▪ Circuitos  digitales  de  prácticas,  pasantías  o  vinculación  entre  estudiantes,
unidades académicas y organizaciones.
▪ Procedimientos   y   formularios   institucionales   pertinentes,   analizados   sin
recopilar ni publicar datos personales o documentos reales.


Modelo de Datos e Interoperabilidad

▪ Definir un diccionario de datos con nombre, formato, obligatoriedad, validación
y significado de cada campo.
▪ Justificar   claves,   códigos   únicos,   tipos   de   instrumento,   unidades,   partes,
personas y relaciones entre instrumentos.
▪ Documentar  cómo  se  representan  documentos  y  versiones,  estados,  fechas,
auditoría y permisos de acceso.
▪ Cada grupo deberá justificar su modelo de datos y las reglas de interoperabilidad
que propone, utilizando formatos documentados como JSON para la API y CSV
para exportaciones.

El modelo debe reflejar que adendas, renovaciones y sustituciones son instrumentos
nuevos vinculados a antecedentes; la relación no debe alterar automáticamente el
registro anterior.

Tecnologías y Herramientas a Investigar





Carreras: ANALISTA PROGRAMADOR & INGENIERÍA EN SISTEMAS
Materia: PROGRAMACIÓN ORIENTADA A LA WEB & LABORATORIO DE
## APLICACIONES MÓVILES
## TRABAJO INTEGRADOR FINAL


▪ Frontend  Web  (React/Next.js): componentes,  formularios,  tablas,  paneles  y
estrategias de adaptación responsive.
▪ Frontend  móvil  (React  Native): navegación,  almacenamiento  seguro,  cámara
para lectura QR, permisos y notificaciones.
▪ Backend   (PHP/Laravel   API   REST   o   Node.js): autenticación,   autorización,
validación, manejo de archivos y documentación de endpoints.
▪ Persistencia: MySQL en servidor, IndexedDB para datos web sin conexión y una
alternativa   local   apropiada   para   React   Native;   justificar   sincronización   y
resolución de conflictos.


Estándares de Usabilidad, Accesibilidad y Protección de Datos

▪ WCAG 2.1: aplicar pautas de accesibilidad, etiquetas asociadas a controles, foco
visible, contraste, navegación por teclado y mensajes comprensibles.
▪ ISO   9241-210: utilizar   principios   de   diseño   centrado   en   las   personas,
considerando usuarios, tareas y contexto.
▪ Privacidad  y  seguridad: minimizar  datos  personales  de  prueba,  controlar  su
acceso por rol y unidad, proteger documentos y  documentar las decisiones de
seguridad.


Tareas de Indagación

▪ Entregar un informe breve de hasta 5 páginas que incluya:
▪ Referencias   relevadas   y   una   comparación   de   actores,   funcionalidades,
navegación y problemas detectados.
▪ Modelo  de  datos  propuestos,  con  reglas  para  instrumentos,  antecedentes,
documentos y estados.
▪ Justificación de la arquitectura web, móvil, API REST, base de datos, trabajo sin
conexión, sincronización y seguridad.
▪ Análisis  de  usabilidad,  accesibilidad  y  privacidad  de  las  referencias,  más
necesidades priorizadas del caso.

Metodología de trabajo

El  proyecto  se  desarrollará  con  un  proceso  iterativo  de Diseño  Centrado  en  el
Usuario (DCU):    comprender    el    contexto,    especificar    necesidades,    diseñar
alternativas y evaluar las soluciones con escenarios representativos.











Carreras: ANALISTA PROGRAMADOR & INGENIERÍA EN SISTEMAS
Materia: PROGRAMACIÓN ORIENTADA A LA WEB & LABORATORIO DE
## APLICACIONES MÓVILES
## TRABAJO INTEGRADOR FINAL






El Proceso de DCU

- Entender y especificar el contexto de uso
a. Identificar  perfiles  de  administración,  gestión,  revisión  y  consulta;  sus
tareas, dispositivos, restricciones y necesidades de acceso por unidad.
- Especificar requerimientos
a. Priorizar  necesidades  y  reglas  para  registrar  instrumentos,  revisar  y
firmar,    buscar    antecedentes,    controlar    vencimientos    y    consultar
estadísticas.
- Producir soluciones de diseño
a.  Esta fase se puede subdividir en diferentes etapas secuenciales, desde
las primeras soluciones conceptuales hasta la solución final de diseño.
Se puede elaborar arquitectura de información, modelo de datos, flujos,
wireframes y prototipos de implementación para web y móvil.
## 4. Evaluación
a. Es la fase más importante del proceso, en la que se validan las soluciones
de  diseño  (el  sistema  satisface  los  requisitos)  o  por  el  contrario  se
detectan  problemas  de  usabilidad,  normalmente  a  través  de  test  con
usuarios. Probar     tareas     clave     con     usuarios     o     participantes
representativos  sobre  datos  ficticios;  registrar  errores,  accesibilidad,
tiempos y mejoras, e iterar el diseño.

Como etapas iniciales, cada equipo deberá realizar las siguientes actividades:

▪ Analizar los circuitos del dominio y delimitar qué actor realiza cada tarea y qué
información puede consultar o modificar.

▪ Narrar  los  flujos  principales: alta  de  un  marco,  registro  de  un  específico  o
pasantía, revisión, aprobación, registro de firma, adenda y renovación.

▪ Describir  la  arquitectura  de  información  y  el  modelo  de  dominio,  incluyendo
usuarios, unidades, convenios, partes, personas, documentos y relaciones.

▪ Diseñar las interfaces web y móvil, con estados vacíos, carga, validación, errores,
permisos y visualización de datos.

▪ Definir  el  diseño  de  interacción  y  navegación  de  cada  cliente,  justificando  las
diferencias entre web y aplicación móvil.


## 1 2 3 4





Carreras: ANALISTA PROGRAMADOR & INGENIERÍA EN SISTEMAS
Materia: PROGRAMACIÓN ORIENTADA A LA WEB & LABORATORIO DE
## APLICACIONES MÓVILES
## TRABAJO INTEGRADOR FINAL

▪ Documentar  arquitectura,  matriz  de  permisos,  API  REST,  modelo  de  datos,
estados del trámite y decisiones de sincronización.

▪ Implementar  desde  cero  los  requerimientos  acordados  y  validar  los  flujos
completos en ambos clientes con la misma API.


## Consideraciones Finales

El  Trabajo  Práctico  Integrador  es  una  instancia  central  de  evaluación  intercátedra.  El
resultado debe mostrar una solución coherente en la que el análisis, la experiencia de
uso y la implementación técnica respondan al mismo dominio.

Se  evaluarán  la  funcionalidad  y  consistencia  entre  web,  app,  API  y  base  de  datos;  el
modelado  y  las  reglas  del  dominio;  la  seguridad;  el  diseño  UI/UX;  la  accesibilidad;  la
documentación; las pruebas y la colaboración del equipo.

Se espera que cada equipo entregue:
▪ Código  fuente  completo  en  un  repositorio  Git,  organizado  en  cliente  web,
aplicación móvil, backend y recursos de base de datos.
▪ Informe técnico con arquitectura, modelo de datos y diagramas, API REST, matriz
de   permisos,   decisiones   de   diseño,   instalación,   pruebas   y   limitaciones
conocidas.
▪ Prototipos  o  archivos  de  diseño,  manual  breve  de  uso  y  presentación  oral  con
demostración de los flujos web y móvil.

Organización: La organización del trabajo es grupal. Los grupos deberán componerse
de  2  o  3  personas  cada  uno. Todos  sus  integrantes  deben  participar  en  el  análisis,
desarrollo y presentación de ambas partes del proyecto.

El  cuerpo  docente  de  ambas  cátedras  realizará  el  seguimiento  por  etapas  y  podrá
solicitar evidencias individuales y grupales del avance.


Fecha de Entrega


18 de noviembre de 2025

Se hará la presentación de forma conjunta para ambas asignaturas, comenzando por la
mañana y  finalizando por la tarde.  Se  sorteará  previamente el  turno por grupos.  Cada
grupo hará la exposición ante sus compañeros y docentes.

El proyecto tendrá instancias de seguimiento durante el cursado. Cada equipo deberá
presentar avances de indagación, diseño, integración, pruebas y producto final, según el
cronograma que indiquen las cátedras.







Carreras: ANALISTA PROGRAMADOR & INGENIERÍA EN SISTEMAS
Materia: PROGRAMACIÓN ORIENTADA A LA WEB & LABORATORIO DE
## APLICACIONES MÓVILES
## TRABAJO INTEGRADOR FINAL



Pautas de presentación

La exposición será grupal y evaluada por los equipos docentes de ambas asignaturas.
La demostración utilizará cuentas, documentos y personas ficticias; no se deben cargar
datos personales o convenios reales.

▪ No se requiere copia impresa. Entregar en un archivo .zip o .rar todas las fuentes,
documentación y recursos del proyecto, bajo la nomenclatura
GRUPO_XX_APELLIDO1_APELLIDO2_APELLIDO3.zip.



## Herramientas / Preparación

Para  alcanzar  los  objetivos,  se  espera  que  cada  estudiante  aplique  los  contenidos
trabajados en ambas asignaturas y participe de las distintas etapas del proyecto.

▪ Utilizar una herramienta de diseño, como Figma u otra equivalente, para producir
arquitectura de información, flujos, prototipos y componentes de interfaz.
▪ Contar con IDE, Git, entorno para React/Next.js, React Native y backend elegido,
MySQL y emulador o dispositivo móvil para las pruebas.


Las   herramientas   adicionales   son   orientativas.   El   equipo   podrá   seleccionar
alternativas compatibles con los requerimientos, siempre que justifique la decisión
y documente cómo ejecutar el sistema desde cero.
