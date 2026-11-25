import type { Href } from 'expo-router';

/**
 * Qué destinos ya tienen pantalla. Los que están en `false` se muestran deshabilitados
 * ("Próximamente") en vez de abrir una pantalla vacía. Al implementar uno: pasarlo a `true`
 * y cambiar su ruta en `Destinos`.
 */
export const Disponible = {
  escanearQr: true,
  notificaciones: true,
  miPerfil: true,
  registrarIdentidad: true,
  ayuda: true,
} as const;

export const Destinos = {
  buscarConvenios: (texto: string): Href => ({
    pathname: '/convenios',
    params: texto ? { buscar: texto } : {},
  }),
  /** Abre la lista con el panel de filtros desplegado. */
  busquedaAvanzada: (): Href => ({ pathname: '/convenios', params: { filtros: '1' } }),
  /** Con `idInstrumento` abre el firmador directamente sobre ese convenio. */
  firmador: (idInstrumento?: number): Href =>
    idInstrumento === undefined
      ? '/firmador'
      : { pathname: '/firmador', params: { id: String(idInstrumento) } },
  convenio: (idInstrumento: number): Href => ({
    pathname: '/convenio/[id]',
    params: { id: String(idInstrumento) },
  }),
  escanearQr: (): Href => '/escanear-qr',
  notificaciones: (): Href => '/notificaciones',
  movimientos: (): Href => '/movimientos',
  miPerfil: (): Href => '/perfil',
  registrarIdentidad: (): Href => '/identidad',
  ayuda: (): Href => '/ayuda',
} as const;
