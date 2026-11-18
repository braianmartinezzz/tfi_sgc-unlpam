"use client";

import type { FormEvent } from "react";
import type { UnidadAcademica } from "@/types/unidad";

export interface EstadisticaFilterDraft {
  fecha_desde: string;
  fecha_hasta: string;
  tipo: string;
  id_unidad_academica: string;
}

interface EstadisticaFiltersProps {
  draft: EstadisticaFilterDraft;
  unidades: UnidadAcademica[];
  mostrarSelectorUnidad: boolean;
  unidadFija: string | null;
  unidadesCargando: boolean;
  errorUnidades: string | null;
  errorValidacion: string | null;
  filtrosActivos: boolean;
  onChange: (field: keyof EstadisticaFilterDraft, value: string) => void;
  onApply: (event: FormEvent<HTMLFormElement>) => void;
  onClear: () => void;
  onRetryUnits: () => void;
}

const controlClassName =
  "mt-1 min-h-11 w-full rounded-md border border-ui-border bg-surface px-3 py-2 text-base text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-70";

export default function EstadisticaFilters({
  draft,
  unidades,
  mostrarSelectorUnidad,
  unidadFija,
  unidadesCargando,
  errorUnidades,
  errorValidacion,
  filtrosActivos,
  onChange,
  onApply,
  onClear,
  onRetryUnits,
}: EstadisticaFiltersProps) {
  return (
    <form
      aria-label="Filtros de estadísticas"
      onSubmit={onApply}
      className="rounded-lg border border-ui-border bg-surface p-4 text-foreground shadow-sm sm:p-5"
    >
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-lg font-bold">Filtros</h2>
        {filtrosActivos ? (
          <span className="rounded-full bg-brand/10 px-3 py-1 text-sm font-semibold text-brand-text">
            Filtros aplicados
          </span>
        ) : null}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div>
          <label htmlFor="estadisticas-fecha-desde" className="font-semibold">
            Fecha desde
          </label>
          <input
            id="estadisticas-fecha-desde"
            className={`${controlClassName} [&::-webkit-calendar-picker-indicator]:cursor-pointer`}
            type="date"
            value={draft.fecha_desde}
            onChange={(event) => onChange("fecha_desde", event.currentTarget.value)}
          />
        </div>
        <div>
          <label htmlFor="estadisticas-fecha-hasta" className="font-semibold">
            Fecha hasta
          </label>
          <input
            id="estadisticas-fecha-hasta"
            className={`${controlClassName} [&::-webkit-calendar-picker-indicator]:cursor-pointer`}
            type="date"
            value={draft.fecha_hasta}
            onChange={(event) => onChange("fecha_hasta", event.currentTarget.value)}
          />
        </div>
        <div>
          <label htmlFor="estadisticas-tipo" className="font-semibold">
            Tipo de convenio
          </label>
          <select
            id="estadisticas-tipo"
            className={`${controlClassName} enabled:cursor-pointer`}
            value={draft.tipo}
            onChange={(event) => onChange("tipo", event.currentTarget.value)}
          >
            <option value="">Todos los tipos</option>
            <option value="Colaboración">Colaboración</option>
            <option value="Pasantía">Pasantía</option>
          </select>
        </div>

        {mostrarSelectorUnidad ? (
          <div>
            <label
              htmlFor="estadisticas-unidad-academica"
              className="font-semibold"
            >
              Unidad académica
            </label>
            <select
              id="estadisticas-unidad-academica"
              className={`${controlClassName} enabled:cursor-pointer`}
              value={draft.id_unidad_academica}
              onChange={(event) =>
                onChange("id_unidad_academica", event.currentTarget.value)
              }
              disabled={unidadesCargando || Boolean(errorUnidades)}
            >
              <option value="">Todas las unidades</option>
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
                Cargando unidades académicas...
              </p>
            ) : null}
            {errorUnidades ? (
              <div className="mt-1 space-y-1">
                <p role="alert" className="text-sm text-red-700 dark:text-red-300">
                  {errorUnidades}
                </p>
                <button
                  type="button"
                  onClick={onRetryUnits}
                  className="text-sm font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                  Reintentar carga de unidades
                </button>
              </div>
            ) : null}
          </div>
        ) : (
          <div>
            <span className="font-semibold">Unidad académica</span>
            <p className="mt-1 min-h-11 rounded-md border border-ui-border bg-page px-3 py-2">
              {unidadFija ??
                "Se aplica automáticamente el alcance de tu cuenta."}
            </p>
          </div>
        )}
      </div>

      {errorValidacion ? (
        <p role="alert" className="mt-3 text-sm text-red-700 dark:text-red-300">
          {errorValidacion}
        </p>
      ) : null}

      <div className="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button
          type="button"
          onClick={onClear}
          disabled={
            !filtrosActivos &&
            !draft.fecha_desde &&
            !draft.fecha_hasta &&
            !draft.tipo &&
            !draft.id_unidad_academica
          }
          className="min-h-11 enabled:cursor-pointer rounded-md border border-ui-border px-4 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60"
        >
          Limpiar filtros
        </button>
        <button
          type="submit"
          className="min-h-11 enabled:cursor-pointer rounded-md bg-brand px-4 py-2 font-semibold text-brand-on-brand hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
        >
          Aplicar filtros
        </button>
      </div>
    </form>
  );
}
