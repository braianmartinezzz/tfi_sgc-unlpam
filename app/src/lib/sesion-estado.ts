/**
 * Recuerda que la sesión se cerró porque el servidor la venció (inactividad o
 * token revocado), para que el login pueda avisarlo una sola vez.
 */
let sesionVencida = false;

export function marcarSesionVencida(): void {
  sesionVencida = true;
}

/** Devuelve true una única vez después de un cierre por vencimiento. */
export function consumirSesionVencida(): boolean {
  const valor = sesionVencida;
  sesionVencida = false;
  return valor;
}
