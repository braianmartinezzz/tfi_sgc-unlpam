import { formatEstado } from "@/lib/format";

const stateClasses: Readonly<Record<string, string>> = {
  Borrador: "bg-slate-200 text-slate-900 dark:bg-slate-700 dark:text-white",
  EnCorreccion:
    "bg-amber-100 text-amber-950 dark:bg-amber-900 dark:text-amber-100",
  "Revisión": "bg-blue-100 text-blue-950 dark:bg-blue-900 dark:text-blue-100",
  Aprobado:
    "bg-emerald-100 text-emerald-950 dark:bg-emerald-900 dark:text-emerald-100",
  Firmado:
    "bg-green-100 text-green-950 dark:bg-green-900 dark:text-green-100",
  Rescindido: "bg-rose-100 text-rose-950 dark:bg-rose-900 dark:text-rose-100",
  Rechazado: "bg-red-100 text-red-950 dark:bg-red-900 dark:text-red-100",
  Archivado:
    "bg-violet-100 text-violet-950 dark:bg-violet-900 dark:text-violet-100",
};

export default function EstadoBadge({ estado }: { estado: string }) {
  return (
    <span
      className={`inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${
        stateClasses[estado] ??
        "bg-neutral-200 text-neutral-900 dark:bg-neutral-700 dark:text-white"
      }`}
    >
      {formatEstado(estado)}
    </span>
  );
}
