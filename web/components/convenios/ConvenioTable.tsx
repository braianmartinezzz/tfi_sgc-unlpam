"use client";

import Link from "next/link";
import EstadoBadge from "@/components/convenios/EstadoBadge";
import {
  canDeleteDraftConvenio,
  canEditConvenio,
  canReviewConvenio,
} from "@/lib/permissions";
import type { ConvenioListItem } from "@/types/convenio";

interface ConvenioTableProps {
  convenios: ConvenioListItem[];
  rol: string | null;
  onEliminar: (
    convenio: ConvenioListItem,
    trigger: HTMLButtonElement,
  ) => void;
}

const rowActionClassName =
  "rounded-md px-2 py-1 font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand";

export default function ConvenioTable({
  convenios,
  rol,
  onEliminar,
}: ConvenioTableProps) {
  return (
    <div
      className="max-w-full overflow-x-auto rounded-lg border border-ui-border bg-surface shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
      tabIndex={0}
      aria-label="Tabla de convenios; desplácese horizontalmente para ver todas las columnas"
    >
      <table className="w-full min-w-[880px] border-collapse bg-surface text-left text-foreground">
        <caption className="sr-only">Convenios registrados</caption>
        <thead className="bg-table-head text-white">
          <tr>
            <th scope="col" className="px-4 py-3 font-bold">
              Código
            </th>
            <th scope="col" className="px-4 py-3 font-bold">
              Convenio
            </th>
            <th scope="col" className="px-4 py-3 font-bold">
              Tipo
            </th>
            <th scope="col" className="px-4 py-3 font-bold">
              Estado
            </th>
            <th scope="col" className="px-4 py-3 font-bold">
              Facultad
            </th>
            <th scope="col" className="px-4 py-3 font-bold">
              Acciones
            </th>
          </tr>
        </thead>
        <tbody className="divide-y divide-ui-border">
          {convenios.map((convenio) => {
            const estado = convenio.instrumento?.estado ?? "Sin estado";
            const titulo = convenio.instrumento?.titulo ?? "—";
            const subtipo = convenio.marco
              ? "Marco"
              : convenio.especifico
                ? "Específico"
                : null;
            const facultad =
              convenio.unidad_academica?.sigla ||
              convenio.unidad_academica?.nombre ||
              "—";
            const puedeEditar = canEditConvenio(rol, estado);
            const puedeEliminar = canDeleteDraftConvenio(rol, estado);
            const detalleHref = `/convenios/${convenio.id_instrumento}`;

            return (
              <tr
                key={convenio.id_instrumento}
                className="bg-surface text-foreground even:bg-row-alternate"
              >
                <td className="whitespace-nowrap px-4 py-3">
                  <Link
                    href={detalleHref}
                    className="font-semibold text-brand-text underline decoration-1 underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                  >
                    {convenio.id_instrumento}
                  </Link>
                </td>
                <td className="max-w-sm px-4 py-3">
                  <span className="line-clamp-2" title={titulo}>
                    {titulo}
                  </span>
                </td>
                <td className="px-4 py-3">
                  {convenio.tipo}
                  {subtipo ? ` (${subtipo})` : ""}
                </td>
                <td className="px-4 py-3">
                  <EstadoBadge estado={estado} />
                </td>
                <td className="px-4 py-3">{facultad}</td>
                <td className="px-4 py-3">
                  <div
                    aria-label={`Acciones para el convenio ${convenio.id_instrumento}`}
                    className="flex items-center gap-2"
                  >
                    <Link href={detalleHref} className={rowActionClassName}>
                      Ver
                    </Link>
                    {canReviewConvenio(rol, estado) ? (
                      <Link
                        href={`${detalleHref}/revisar`}
                        className={`${rowActionClassName} cursor-pointer`}
                      >
                        Revisar
                      </Link>
                    ) : null}
                    {puedeEditar ? (
                      <Link
                        href={`${detalleHref}/edit`}
                        className={rowActionClassName}
                      >
                        Editar
                      </Link>
                    ) : null}

                    {puedeEliminar ? (
                      <button
                        type="button"
                        className={`${rowActionClassName} cursor-pointer`}
                        onClick={(event) =>
                          onEliminar(convenio, event.currentTarget)
                        }
                      >
                        Eliminar
                      </button>
                    ) : null}
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
