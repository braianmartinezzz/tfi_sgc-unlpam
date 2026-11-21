function dosDigitos(valor: number): string {
  return String(valor).padStart(2, '0');
}

export function parseFecha(valor: string | null | undefined): Date | null {
  if (!valor) return null;
  const fecha = new Date(valor);
  return Number.isNaN(fecha.getTime()) ? null : fecha;
}

export function formatFecha(valor: string | null | undefined): string {
  const fecha = parseFecha(valor);
  if (!fecha) return 'sin fecha';

  return `${dosDigitos(fecha.getDate())}/${dosDigitos(fecha.getMonth() + 1)}/${fecha.getFullYear()}`;
}

export function formatHora(fecha: Date): string {
  return `${dosDigitos(fecha.getHours())}:${dosDigitos(fecha.getMinutes())}`;
}

/** "hace 5 min", "hace 3 h", "hace 2 días"; para fechas más viejas, la fecha completa. */
export function formatRelativo(valor: string | null | undefined, ahora: Date = new Date()): string {
  const fecha = parseFecha(valor);
  if (!fecha) return 'sin fecha';

  const minutos = Math.floor((ahora.getTime() - fecha.getTime()) / 60000);

  if (minutos < 1) return 'recién';
  if (minutos < 60) return `hace ${minutos} min`;

  const horas = Math.floor(minutos / 60);
  if (horas < 24) return `hace ${horas} h`;

  const dias = Math.floor(horas / 24);
  if (dias < 7) return `hace ${dias} ${dias === 1 ? 'día' : 'días'}`;

  return formatFecha(valor);
}

export function iniciales(nombre: string | null | undefined): string {
  const partes = (nombre ?? '').trim().split(/\s+/).filter(Boolean);
  if (partes.length === 0) return '?';

  return (partes[0][0] + (partes.length > 1 ? partes[partes.length - 1][0] : '')).toUpperCase();
}
