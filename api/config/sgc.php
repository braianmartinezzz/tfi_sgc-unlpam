<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sesión de la app móvil
    |--------------------------------------------------------------------------
    | El token de la app vence tras N minutos SIN USO (ventana deslizante: cada
    | pedido autenticado lo renueva). La web sigue usando su token sin vencimiento.
    */
    'sesion_app' => [
        'inactividad_minutos' => (int) env('SESION_APP_INACTIVIDAD_MINUTOS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verificación en dos pasos (MFA) del login de la app
    |--------------------------------------------------------------------------
    | Mail y SMS son simulados (el código queda en el log de Laravel). En entorno
    | local la respuesta del envío incluye `codigo_simulado` para poder hacer la
    | demostración sin abrir el log; en cualquier otro entorno no se expone.
    */
    'mfa' => [
        'longitud' => 6,
        'codigo_expira_minutos' => (int) env('MFA_CODIGO_EXPIRA_MINUTOS', 5),
        'desafio_expira_minutos' => 10,
        'max_intentos' => 5,
        'max_envios' => 5,
        'exponer_codigo_simulado' => (bool) env('MFA_EXPONER_CODIGO', env('APP_ENV') === 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingreso con biometría
    |--------------------------------------------------------------------------
    | Al activarla, la API entrega un token propio del dispositivo (con la única
    | habilidad `biometrico`) que solo sirve para abrir una sesión nueva.
    */
    'biometria' => [
        'dias_validez' => (int) env('BIOMETRIA_DIAS_VALIDEZ', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Expo Push (notificaciones push reales)
    |--------------------------------------------------------------------------
    */
    'expo_push' => [
        'url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
        'access_token' => env('EXPO_ACCESS_TOKEN'),
    ],

];
