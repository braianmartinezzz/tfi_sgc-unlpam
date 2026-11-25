import type { UsuarioAutenticado } from '@/types/auth';
import type { PanelData } from '@/types/panel';

export type InstantaneaPanel = {
  me: UsuarioAutenticado;
  panel: PanelData;
  actualizado: Date;
};

/**
 * Última respuesta exitosa del panel, en memoria. Está en su propio módulo para poder
 * olvidarla al cerrar sesión o al entrar otro usuario (ver `limpiarOffline`).
 */
let ultima: InstantaneaPanel | null = null;

export function leerInstantaneaEnMemoria(): InstantaneaPanel | null {
  return ultima;
}

export function guardarInstantaneaEnMemoria(valor: InstantaneaPanel): void {
  ultima = valor;
}

export function olvidarInstantaneaEnMemoria(): void {
  ultima = null;
}
