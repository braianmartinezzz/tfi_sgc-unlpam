export default function ActivoBadge({ activo }: { activo: boolean }) {
  return (
    <span
      className={`inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${
        activo
          ? "bg-emerald-100 text-emerald-950 dark:bg-emerald-900 dark:text-emerald-100"
          : "bg-slate-200 text-slate-900 dark:bg-slate-700 dark:text-white"
      }`}
    >
      {activo ? "Activo" : "Inactivo"}
    </span>
  );
}
