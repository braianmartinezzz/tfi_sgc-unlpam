"use client";

import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import { filterControlInteractionClassName } from "@/components/ui/filter-control-styles";
import type { InstitucionFirmante } from "@/types/unidad";

interface UnidadFiltersProps {
  buscar: string;
  institucion: string;
  activo: string;
  instituciones: InstitucionFirmante[];
  institucionesCargando: boolean;
  institucionesError: string | null;
  onBuscarChange: (value: string) => void;
  onInstitucionChange: (value: string) => void;
  onActivoChange: (value: string) => void;
  onLimpiar: () => void;
  onReintentarInstituciones: () => void;
}

export default function UnidadFilters({
  buscar,
  institucion,
  activo,
  instituciones,
  institucionesCargando,
  institucionesError,
  onBuscarChange,
  onInstitucionChange,
  onActivoChange,
  onLimpiar,
  onReintentarInstituciones,
}: UnidadFiltersProps) {
  return (
    <section
      aria-label="Filtros de unidades académicas"
      className="rounded-lg border border-ui-border bg-surface p-4 text-foreground shadow-sm sm:p-5"
    >
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <Input
          id="unidad-buscar"
          label="Búsqueda"
          floatingLabel={false}
          icon={
            <svg
              aria-hidden="true"
              className="h-4 w-4"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <circle cx="11" cy="11" r="7" />
              <path d="m20 20-4-4" />
            </svg>
          }
          className={filterControlInteractionClassName}
          type="search"
          value={buscar}
          onChange={(event) => onBuscarChange(event.currentTarget.value)}
          placeholder="Sigla o nombre"
          autoComplete="off"
        />
        <div>
          <Select
            id="unidad-institucion-filtro"
            label="Institución"
            className={`${filterControlInteractionClassName} enabled:cursor-pointer`}
            value={institucion}
            onChange={(event) =>
              onInstitucionChange(event.currentTarget.value)
            }
            disabled={institucionesCargando || Boolean(institucionesError)}
          >
            <option value="">Todas las instituciones</option>
            {instituciones.map((opcion) => (
              <option key={opcion.id_institucion} value={opcion.id_institucion}>
                {opcion.razon_social}
              </option>
            ))}
          </Select>
          {institucionesCargando ? (
            <p role="status" className="mt-1 text-sm">
              Cargando instituciones...
            </p>
          ) : null}
          {institucionesError ? (
            <div className="mt-1 space-y-1">
              <p role="alert" className="text-sm text-red-700 dark:text-red-300">
                {institucionesError}
              </p>
              <button
                type="button"
                onClick={onReintentarInstituciones}
                className="min-h-11 text-sm font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
              >
                Reintentar carga de instituciones
              </button>
            </div>
          ) : null}
        </div>
        <Select
          id="unidad-estado-filtro"
          label="Estado"
          className={`${filterControlInteractionClassName} enabled:cursor-pointer`}
          value={activo}
          onChange={(event) => onActivoChange(event.currentTarget.value)}
        >
          <option value="">Todos los estados</option>
          <option value="true">Activas</option>
          <option value="false">Inactivas</option>
        </Select>
      </div>
      <div className="mt-4 flex justify-end">
        <Button
          variant="link"
          onClick={onLimpiar}
          className="min-h-11 cursor-pointer !rounded-md border border-ui-border px-4 py-2 font-semibold !no-underline text-foreground hover:bg-row-alternate"
        >
          Limpiar filtros
        </Button>
      </div>
    </section>
  );
}
