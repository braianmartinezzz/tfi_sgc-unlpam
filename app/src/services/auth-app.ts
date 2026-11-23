import { ApiError, NetworkError, apiRequest } from '@/services/api-client';
import type { UsuarioAutenticado } from '@/types/auth';

/**
 * Login de la app: contraseña + MFA, ingreso con biometría y activación de la
 * biometría desde "Mi perfil". Ver api/app/Http/Controllers/Api/AuthAppController.php.
 */

export type CanalMfa = 'mail' | 'sms' | 'push';

export type DesafioMfa = {
  mfa_token: string;
  canales: CanalMfa[];
};

export type EnvioCodigo = {
  canal: CanalMfa;
  destino: string;
  expira_en_segundos: number;
  /** Solo en entorno local y para mail/SMS simulados: permite hacer la demo sin abrir el log. */
  codigo_simulado?: string;
};

export type SesionApp = {
  access_token: string;
  user: UsuarioAutenticado;
  sesion_expira_en_minutos: number;
};

const json = (cuerpo: object): RequestInit => ({
  method: 'POST',
  body: JSON.stringify(cuerpo),
});

/** Paso 1: valida mail y contraseña. Todavía no hay sesión: devuelve el desafío MFA. */
export function iniciarLogin(mail: string, password: string): Promise<DesafioMfa> {
  return apiRequest<DesafioMfa>('/app/login', json({ mail, password }));
}

/** Paso 2: pide el código por el canal elegido (también sirve para reenviarlo). */
export function enviarCodigoMfa(
  mfaToken: string,
  canal: CanalMfa,
  pushToken?: string | null,
): Promise<EnvioCodigo> {
  return apiRequest<EnvioCodigo>(
    '/app/mfa/enviar',
    json({ mfa_token: mfaToken, canal, push_token: pushToken ?? null }),
  );
}

/** Paso 3: con el código correcto la API abre la sesión. */
export function verificarCodigoMfa(mfaToken: string, codigo: string): Promise<SesionApp> {
  return apiRequest<SesionApp>('/app/mfa/verificar', json({ mfa_token: mfaToken, codigo }));
}

/** Ingreso con huella o rostro: canjea el token del dispositivo por una sesión nueva. */
export function loginBiometrico(tokenDispositivo: string): Promise<SesionApp> {
  return apiRequest<SesionApp>('/app/login/biometrico', {
    method: 'POST',
    headers: { Authorization: `Bearer ${tokenDispositivo}` },
  });
}

/** Activa la biometría en este dispositivo (la API pide la contraseña). */
export async function activarBiometria(password: string): Promise<string> {
  const respuesta = await apiRequest<{ token_dispositivo: string }>(
    '/app/biometria',
    json({ password }),
  );

  return respuesta.token_dispositivo;
}

/** Revoca el token del dispositivo en la API. */
export async function desactivarBiometria(tokenDispositivo: string): Promise<void> {
  await apiRequest('/app/biometria', {
    method: 'DELETE',
    body: JSON.stringify({ token_dispositivo: tokenDispositivo }),
  });
}

/** Texto para mostrar al usuario a partir de cualquier error del login. */
export function mensajeDeError(error: unknown, porDefecto: string): string {
  if (error instanceof ApiError || error instanceof NetworkError) return error.message;
  return porDefecto;
}
