import { formatEstado } from "@/lib/format";

export default function EstadoBadge({ estado }: { estado: string }) {
  return <span>{formatEstado(estado)}</span>;
}
