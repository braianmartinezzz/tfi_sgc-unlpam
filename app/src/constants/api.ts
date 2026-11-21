import { Platform } from 'react-native';

/**
 * URL base de la API Laravel.
 *
 * - Web / emulador iOS: http://127.0.0.1:8000
 * - Emulador Android:   http://10.0.2.2:8000
 * - Celular físico:     http://IP_DE_TU_PC:8000  (ej. 192.168.1.20)
 *
 * Lo más cómodo es definirla en app/.env:
 *   EXPO_PUBLIC_API_URL=http://192.168.1.20:8000
 * y levantar Laravel con: php artisan serve --host=0.0.0.0
 */
const DEFAULT_URL = Platform.select({
  android: 'http://10.0.2.2:8000',
  default: 'http://127.0.0.1:8000',
});

export const API_URL = process.env.EXPO_PUBLIC_API_URL ?? DEFAULT_URL;
