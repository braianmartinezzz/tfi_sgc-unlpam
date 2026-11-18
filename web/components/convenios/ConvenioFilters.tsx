"use client";

import { filterControlInteractionClassName } from "@/components/ui/filter-control-styles";
import type { UnidadAcademica } from "@/types/unidad";

interface ConvenioFiltersProps {
  buscar: string;
  tipo: string;
  esMarco: string;
  estado: string;
  facultad: string;
  estadosVisibles: readonly string[];
  mostrarFacultad: boolean;
  unidades: UnidadAcademica[];
  unidadesCargando: boolean;
  errorUnidades: string | null;
  onBuscarChange: (value: string) => void;
  onTipoChange: (value: string) => void;
  onEsMarcoChange: (value: string) => void;
  onEstadoChange: (value: string) => void;
  onFacultadChange: (value: string) => void;
  onLimpiar: () => void;
  onReintentarUnidades: () => void;
}

const controlClassName =
  `mt-1 min-h-11 w-full rounded-md border border-ui-border bg-surface px-3 py-2 text-base text-foreground ${filterControlInteractionClassName}`;

export default function ConvenioFilters({
  buscar,
  tipo,
  esMarco,
  estado,
  facultad,
  estadosVisibles,
  mostrarFacultad,
  unidades,
  unidadesCargando,
  errorUnidades,
  onBuscarChange,
  onTipoChange,
  onEsMarcoChange,
  onEstadoChange,
  onFacultadChange,
  onLimpiar,
  onReintentarUnidades,
}: ConvenioFiltersProps) {
  return (
    <section
      aria-label="Filtros de convenios"
      className="rounded-lg border border-ui-border bg-surface p-4 text-foreground shadow-sm sm:p-5"
    >
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div>
          <label htmlFor="convenio-buscar" className="font-semibold">
            Búsqueda
          </label>
          <div className="relative">
            <span
              aria-hidden="true"
              className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-foreground"
            >
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
            </span>
            <input
              id="convenio-buscar"
              key={`buscar-${buscar}`}
              className={`${controlClassName} pl-10`}
              type="search"
              onChange={(event) => onBuscarChange(event.currentTarget.value)}
              defaultValue={buscar}
              placeholder="Buscar por título"
              autoComplete="off"
            />
          </div>
        </div>
        <div>
          <label htmlFor="convenio-tipo" className="font-semibold">
            Tipo
          </label>
          <input
            id="convenio-tipo"
            key={`tipo-${tipo}`}
            className={controlClassName}
            type="text"
            onChange={(event) => onTipoChange(event.currentTarget.value)}
            defaultValue={tipo}
            placeholder="Todos los tipos"
            autoComplete="off"
          />
        </div>
        <div>
          <label htmlFor="convenio-subtipo" className="font-semibold">
            Marco/Específico
          </label>
          <select
            id="convenio-subtipo"
            className={`${controlClassName} enabled:cursor-pointer`}
            value={esMarco}
            onChange={(event) => onEsMarcoChange(event.currentTarget.value)}
          >
            <option value="">Todos</option>
            <option value="true">Marco</option>
            <option value="false">Específico</option>
          </select>
        </div>
        <div>
          <label htmlFor="convenio-estado" className="font-semibold">
            Estado
          </label>
          <select
            id="convenio-estado"
            className={`${controlClassName} enabled:cursor-pointer`}
            value={estado}
            onChange={(event) => onEstadoChange(event.currentTarget.value)}
          >
            <option value="">Todos los estados</option>
            {estadosVisibles.map((opcion) => (
              <option key={opcion} value={opcion}>
                {opcion}
              </option>
            ))}
          </select>
        </div>
        {mostrarFacultad ? (
          <div>
            <label htmlFor="convenio-facultad" className="font-semibold">
              Facultad
            </label>
            <select
              id="convenio-facultad"
              className={`${controlClassName} enabled:cursor-pointer`}
              value={facultad}
              onChange={(event) => onFacultadChange(event.currentTarget.value)}
              disabled={unidadesCargando || Boolean(errorUnidades)}
            >
              <option value="">Todas las facultades</option>
              {unidades.map((unidad) => (
                <option
                  key={unidad.id_unidad_academica}
                  value={unidad.id_unidad_academica}
                >
                  {unidad.sigla} — {unidad.nombre}
                </option>
              ))}
            </select>
            {unidadesCargando ? (
              <p role="status" className="mt-1 text-sm">
                Cargando facultades...
              </p>
            ) : null}
            {errorUnidades ? (
              <div className="mt-1 space-y-1">
                <p role="alert" className="text-sm text-red-700 dark:text-red-300">
                  {errorUnidades}
                </p>
                <button
                  type="button"
                  onClick={onReintentarUnidades}
                  className="text-sm font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                  Reintentar carga de facultades
                </button>
              </div>
            ) : null}
          </div>
        ) : null}
      </div>
      <div className="mt-4 flex justify-end">
        <button
          type="button"
          onClick={onLimpiar}
          className="min-h-11 cursor-pointer rounded-md border border-ui-border px-4 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
        >
          Limpiar filtros
        </button>
      </div>
    </section>
  );
}
