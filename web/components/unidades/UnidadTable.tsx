"use client";

import ActivoBadge from "@/components/ui/ActivoBadge";
import type { UnidadAcademica } from "@/types/unidad";

const rowActionClassName =
  "inline-flex min-h-11 items-center justify-center rounded-md px-2 py-1 font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60";

interface UnidadTableProps {
  unidades: UnidadAcademica[];
  canEdit: (unidad: UnidadAcademica) => boolean;
  canToggle: boolean;
  onEdit: (unidad: UnidadAcademica, button: HTMLButtonElement) => void;
  onToggle: (unidad: UnidadAcademica, button: HTMLButtonElement) => void;
  ownUnitId: number | null;
}

export default function UnidadTable({
  unidades,
  canEdit,
  canToggle,
  onEdit,
  onToggle,
  ownUnitId,
}: UnidadTableProps) {
  return (
    <div
      className="max-w-full overflow-x-auto rounded-lg border border-ui-border bg-surface shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
      tabIndex={0}
      aria-label="Tabla de unidades académicas; desplácese horizontalmente para ver todas las columnas"
    >
      <table className="w-full min-w-[760px] border-collapse bg-surface text-left text-foreground">
        <caption className="sr-only">Listado de unidades académicas</caption>
        <thead className="bg-table-head text-white">
          <tr>
            <th scope="col" className="px-4 py-3 font-bold">Sigla</th>
            <th scope="col" className="px-4 py-3 font-bold">Nombre</th>
            <th scope="col" className="px-4 py-3 font-bold">Institución</th>
            <th scope="col" className="px-4 py-3 font-bold">Estado</th>
            <th scope="col" className="px-4 py-3 font-bold">Acciones</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-ui-border">
          {unidades.map((unidad) => {
            const ownUnit = unidad.id_unidad_academica === ownUnitId;
            const institutionInactive =
              unidad.institucion_firmante?.activo === false;
            const toggleDisabledReason = !unidad.activo && institutionInactive
              ? "No podés reactivar una unidad cuya institución está inactiva."
              : !unidad.activo && !unidad.institucion_firmante
                ? "No se pudo verificar el estado de la institución."
                : ownUnit && unidad.activo
                  ? "No podés desactivar tu propia unidad académica."
                  : null;
            const canShowToggle = canToggle;
            const canShowEdit = canEdit(unidad);

            return (
              <tr
                key={unidad.id_unidad_academica}
                className="bg-surface text-foreground even:bg-row-alternate"
              >
                <td className="px-4 py-3">{unidad.sigla}</td>
                <td className="px-4 py-3">{unidad.nombre}</td>
                <td className="px-4 py-3">
                  {unidad.institucion_firmante?.razon_social ?? "—"}
                </td>
                <td className="px-4 py-3">
                  <ActivoBadge activo={unidad.activo} />
                </td>
                <td className="px-4 py-3">
                  {canShowEdit || canShowToggle ? (
                    <div className="flex flex-wrap items-center gap-1">
                      {canShowEdit ? (
                        <button
                          type="button"
                          className={rowActionClassName}
                          onClick={(event) => onEdit(unidad, event.currentTarget)}
                        >
                          Editar
                        </button>
                      ) : null}
                      {canShowToggle ? (
                        <div>
                          <button
                            type="button"
                            className={rowActionClassName}
                            disabled={Boolean(toggleDisabledReason)}
                            aria-describedby={
                              toggleDisabledReason
                                ? `unidad-toggle-reason-${unidad.id_unidad_academica}`
                                : undefined
                            }
                            onClick={(event) => onToggle(unidad, event.currentTarget)}
                          >
                            {unidad.activo ? "Desactivar" : "Activar"}
                          </button>
                          {toggleDisabledReason ? (
                            <p
                              id={`unidad-toggle-reason-${unidad.id_unidad_academica}`}
                              className="max-w-64 text-xs text-foreground/80"
                            >
                              {toggleDisabledReason}
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
