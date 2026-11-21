"use client";

import ActivoBadge from "@/components/ui/ActivoBadge";
import type { InstitucionFirmante } from "@/types/unidad";

const rowActionClassName =
  "inline-flex min-h-11 items-center justify-center rounded-md px-2 py-1 font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60";

interface InstitucionTableProps {
  instituciones: InstitucionFirmante[];
  canEdit: (institucion: InstitucionFirmante) => boolean;
  canToggle: boolean;
  onEdit: (institucion: InstitucionFirmante, button: HTMLButtonElement) => void;
  onToggle: (
    institucion: InstitucionFirmante,
    button: HTMLButtonElement,
  ) => void;
}

export default function InstitucionTable({
  instituciones,
  canEdit,
  canToggle,
  onEdit,
  onToggle,
}: InstitucionTableProps) {
  return (
    <div
      className="max-w-full overflow-x-auto rounded-lg border border-ui-border bg-surface shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
      tabIndex={0}
      aria-label="Tabla de instituciones firmantes; desplácese horizontalmente para ver todas las columnas"
    >
      <table className="w-full min-w-[1050px] border-collapse bg-surface text-left text-foreground">
        <caption className="sr-only">Listado de instituciones firmantes</caption>
        <thead className="bg-table-head text-white">
          <tr>
            <th scope="col" className="px-4 py-3 font-bold">Razón social</th>
            <th scope="col" className="px-4 py-3 font-bold">CUIT</th>
            <th scope="col" className="px-4 py-3 font-bold">Tipo</th>
            <th scope="col" className="px-4 py-3 font-bold">Domicilio legal</th>
            <th scope="col" className="px-4 py-3 font-bold">Estado</th>
            <th scope="col" className="px-4 py-3 font-bold">Acciones</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-ui-border">
          {instituciones.map((institucion) => {
            const showEdit = canEdit(institucion);
            return (
              <tr
                key={institucion.id_institucion}
                className="bg-surface text-foreground even:bg-row-alternate"
              >
                <td className="px-4 py-3">
                  <div className="space-y-1">
                    <span>{institucion.razon_social}</span>
                    {institucion.es_propia ? (
                      <span className="block text-xs font-semibold text-brand-text">
                        Institución propia
                      </span>
                    ) : null}
                  </div>
                </td>
                <td className="px-4 py-3">{institucion.cuit ?? "—"}</td>
                <td className="px-4 py-3">{institucion.tipo_institucion ?? "—"}</td>
                <td className="max-w-sm px-4 py-3">{institucion.domicilio_legal ?? "—"}</td>
                <td className="px-4 py-3">
                  <ActivoBadge activo={institucion.activo} />
                </td>
                <td className="px-4 py-3">
                  {showEdit || canToggle ? (
                    <div className="flex flex-wrap items-center gap-1">
                      {showEdit ? (
                        <button
                          type="button"
                          className={rowActionClassName}
                          onClick={(event) =>
                            onEdit(institucion, event.currentTarget)
                          }
                        >
                          Editar
                        </button>
                      ) : null}
                      {canToggle ? (
                        <div>
                          <button
                            type="button"
                            className={rowActionClassName}
                            disabled={institucion.es_propia}
                            aria-describedby={
                              institucion.es_propia
                                ? `institucion-propia-reason-${institucion.id_institucion}`
                                : undefined
                            }
                            onClick={(event) =>
                              onToggle(institucion, event.currentTarget)
                            }
                          >
                            {institucion.activo ? "Desactivar" : "Activar"}
                          </button>
                          {institucion.es_propia ? (
                            <p
                              id={`institucion-propia-reason-${institucion.id_institucion}`}
                              className="max-w-64 text-xs text-foreground/80"
                            >
                              La institución propia no se puede desactivar.
                            </p>
                          ) : null}
                        </div>
                      ) : null}
                    </div>
                  ) : (
                    "—"
                  )}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
