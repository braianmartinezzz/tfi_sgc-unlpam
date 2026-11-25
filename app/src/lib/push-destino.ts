import type { Href } from 'expo-router';

import { Destinos } from '@/constants/navigation';

/**
 * A qué pantalla lleva una push según su `data`:
 * - código de verificación (`tipo: 'mfa'`): a ninguna, el usuario ya está en el login;
 * - aviso de un convenio: la ficha de ese convenio;
 * - cualquier otro aviso: la lista de notificaciones.
 */
export function destinoDePush(data: Record<string, unknown>): Href | null {
  if (data.tipo === 'mfa') return null;

  const id = Number(data.id_instrumento);

  return Number.isInteger(id) && id > 0 ? Destinos.convenio(id) : Destinos.notificaciones();
}
