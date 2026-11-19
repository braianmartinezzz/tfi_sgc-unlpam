"use client";

import ActivoBadge from "@/components/ui/ActivoBadge";
import type { Representante } from "@/types/representante";

const rowActionClassName =
  "inline-flex min-h-11 items-center justify-center rounded-md px-2 py-1 font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60";

interface RepresentanteTableProps {
  representantes: Representante[];
  canManage: boolean;
  canToggle: boolean;
  institutionActive: boolean;
  onEdit: (representante: Representante, button: HTMLButtonElement) => void;
  onToggle: (representante: Representante, button: HTMLButtonElement) => void;
}

export default function RepresentanteTable({
  representantes,
  canManage,
  canToggle,
  institutionActive,
  onEdit,
  onToggle,
}: RepresentanteTableProps) {
  return (
    <div
      className="max-w-full overflow-x-auto rounded-lg border border-ui-border bg-surface shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
      tabIndex={0}
      aria-label="Tabla de representantes; desplácese horizontalmente para ver todas las columnas"
    >
      <table className="w-full min-w-[1000px] border-collapse bg-surface text-left text-foreground">
        <caption className="sr-only">Representantes de la institución</caption>
        <thead className="bg-table-head text-white">
          <tr>
            <th scope="col" className="px-4 py-3 font-bold">Apellido y nombre</th>
            <th scope="col" className="px-4 py-3 font-bold">DNI</th>
            <th scope="col" className="px-4 py-3 font-bold">CUIL</th>
            <th scope="col" className="px-4 py-3 font-bold">Cargo</th>
            <th scope="col" className="px-4 py-3 font-bold">Estado</th>
            <th scope="col" className="px-4 py-3 font-bold">Acciones</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-ui-border">
          {representantes.map((representante) => {
            const fullName = `${representante.persona.apellido}, ${representante.persona.nombre}`;
            const canActivate = institutionActive || representante.activo;
            const activationReason =
              canToggle && !representante.activo && !institutionActive
                ? "No podés activar representantes de una institución inactiva."
                : null;
            return (
              <tr
                key={representante.id_persona}
                className="bg-surface text-foreground even:bg-row-alternate"
              >
                <td className="px-4 py-3">{fullName}</td>
                <td className="px-4 py-3">{representante.persona.dni}</td>
                <td className="px-4 py-3">{representante.persona.cuil ?? "—"}</td>
                <td className="px-4 py-3">{representante.cargo ?? "—"}</td>
                <td className="px-4 py-3">
                  <ActivoBadge activo={representante.activo} />
                </td>
                <td className="px-4 py-3">
                  {canManage || canToggle ? (
                    <div className="flex flex-wrap items-center gap-1">
                      {canManage ? (
                        <button
                          type="button"
                          className={rowActionClassName}
                          onClick={(event) =>
                            onEdit(representante, event.currentTarget)
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
                            disabled={!canActivate}
                            aria-describedby={
                              activationReason
                                ? `representante-activation-reason-${representante.id_persona}`
                                : undefined
                            }
                            onClick={(event) =>
                              onToggle(representante, event.currentTarget)
                            }
                          >
                            {representante.activo ? "Desactivar" : "Activar"}
                          </button>
                          {activationReason ? (
                            <p
                              id={`representante-activation-reason-${representante.id_persona}`}
                              className="max-w-64 text-xs text-foreground/80"
                            >
                              {activationReason}
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
