export function formatDate(value: string | null | undefined): string {
  if (!value) return "sin vencimiento registrado";
  const date = new Date(`${value.slice(0, 10)}T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    throw new RangeError(`Fecha inválida: ${value}`);
  }
  return new Intl.DateTimeFormat("es-AR").format(date);
}

export function formatEstado(estado: string): string {
  return estado;
}
