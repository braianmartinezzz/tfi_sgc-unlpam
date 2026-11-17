import type { Usuario } from "@/types/usuario";

const rowActionClassName =
  "rounded-md px-2 py-1 font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-50";

export default function UsuarioTable({ usuarios }: { usuarios: Usuario[] }) {
  return (
    <div
      className="max-w-full overflow-x-auto rounded-lg border border-ui-border bg-surface shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
      tabIndex={0}
      aria-label="Tabla de usuarios; desplácese horizontalmente para ver todas las columnas"
    >
      <table className="w-full min-w-[1050px] border-collapse bg-surface text-left text-foreground">
        <caption className="sr-only">Listado de usuarios</caption>
        <thead className="bg-table-head text-white">
          <tr>
            <th scope="col" className="px-4 py-3 font-bold">Nombre</th>
            <th scope="col" className="px-4 py-3 font-bold">Apellido</th>
            <th scope="col" className="px-4 py-3 font-bold">Correo electrónico</th>
            <th scope="col" className="px-4 py-3 font-bold">Rol</th>
            <th scope="col" className="px-4 py-3 font-bold">Unidad académica</th>
            <th scope="col" className="px-4 py-3 font-bold">Estado</th>
            <th scope="col" className="px-4 py-3 font-bold">Acciones</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-ui-border">
          {usuarios.map((usuario) => {
            const unidad =
              usuario.unidad_academica?.sigla ||
              usuario.unidad_academica?.nombre ||
              "—";

            return (
              <tr
                key={usuario.id_usuario}
                className="bg-surface text-foreground even:bg-row-alternate"
              >
                <td className="max-w-xs px-4 py-3">{usuario.nombre || "—"}</td>
                <td
                  className="px-4 py-3"
                  title="El apellido no está disponible en los datos actuales."
                >
                  —
                </td>
                <td className="px-4 py-3">{usuario.mail}</td>
                <td className="px-4 py-3">{usuario.rol?.nombre ?? "—"}</td>
                <td className="px-4 py-3">{unidad}</td>
                <td className="px-4 py-3">
                  <span
                    className={`inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${
                      usuario.activo
                        ? "bg-emerald-100 text-emerald-950 dark:bg-emerald-900 dark:text-emerald-100"
                        : "bg-slate-200 text-slate-900 dark:bg-slate-700 dark:text-white"
                    }`}
                  >
                    {usuario.activo ? "Activo" : "Inactivo"}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <div
                    aria-label={`Acciones para ${usuario.nombre}`}
                    className="flex items-center gap-2"
                  >
                    <button
                      type="button"
                      disabled
                      title="No hay una pantalla de detalle implementada."
                      className={rowActionClassName}
                    >
                      Ver
                    </button>
                    <button
                      type="button"
                      disabled
                      title="La edición no está disponible en esta etapa."
                      className={rowActionClassName}
                    >
                      Editar
                    </button>
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
