import type { Href } from 'expo-router';

/**
 * Destinos del panel. Las pantallas que todavía no existen apuntan a /proximamente;
 * cuando se implementen, solo hay que cambiar la ruta acá.
 */
function proximamente(titulo: string, detalle?: string): Href {
  return {
    pathname: '/proximamente',
    params: detalle ? { titulo, detalle } : { titulo },
  };
}

export const Destinos = {
  buscarConvenios: (texto: string): Href => ({
    pathname: '/convenios',
    params: texto ? { buscar: texto } : {},
  }),
  busquedaAvanzada: (): Href => '/convenios',
  firmador: (): Href => proximamente('Firmador de convenios'),
  escanearQr: (): Href => proximamente('Lector de código QR'),
  convenio: (idInstrumento: number): Href => proximamente('Ficha del convenio', `Convenio #${idInstrumento}`),
  notificaciones: (): Href => proximamente('Notificaciones'),
  movimientos: (): Href => proximamente('Movimientos recientes'),
  miPerfil: (): Href => proximamente('Mi perfil'),
  registrarIdentidad: (): Href => proximamente('Registrar mi identidad'),
  ayuda: (): Href => proximamente('Ayuda y manual de usuario'),
} as const;
