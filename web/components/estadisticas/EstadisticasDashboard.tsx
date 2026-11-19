"use client";

import { useState, type FormEvent } from "react";
import { useQuery } from "@tanstack/react-query";
import EstadisticaFilters, {
  type EstadisticaFilterDraft,
} from "@/components/estadisticas/EstadisticaFilters";
import {
  ConveniosPorEstadoChart,
  ConveniosPorTipoChart,
  ConveniosPorUnidadChart,
  FirmasPorAnioChart,
} from "@/components/estadisticas/EstadisticasCharts";
import { useAuth } from "@/context/AuthContext";
import { useEstadisticas } from "@/hooks/useEstadisticas";
import { canFilterByFaculty } from "@/lib/permissions";
import { ApiError } from "@/services/api-client";
import type { EstadisticaFilters as EstadisticaFilterValues } from "@/services/estadisticas";
import * as unidadesService from "@/services/unidades";
import type {
  EstadisticaIndicadores,
  EstadisticaPorAnio,
} from "@/types/estadistica";

const initialDraft: EstadisticaFilterDraft = {
  fecha_desde: "",
  fecha_hasta: "",
  tipo: "",
  id_unidad_academica: "",
};

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.status === 401) {
      return "Tu sesión ya no está autorizada. Iniciá sesión nuevamente.";
    }
    if (error.status === 403) {
      return "No tenés permiso para consultar estas estadísticas o esa unidad académica.";
    }
  }

  return error instanceof Error
    ? error.message
    : "No se pudieron cargar las estadísticas. Intentá nuevamente.";
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat("es-AR").format(value);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function hasExpectedIndicators(
  value: unknown,
): value is EstadisticaIndicadores {
  if (!isRecord(value)) return false;

  return [
    "convenios_vigentes",
    "convenios_en_tramite",
    "firmados_ultimo_anio",
    "personas_unicas",
    "pasantes_unicos",
  ].every(
    (key) => typeof value[key] === "number" && Number.isFinite(value[key]),
  );
}

function Icon({ name }: { name: string }) {
  const paths: Record<string, string> = {
    vigentes: "M5 12.5 9.5 17 19 7.5 M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z",
    tramite: "M8 3h8l4 4v14H4V3h4Zm4 5v5l3 2",
    firmados: "M4 5h16v14H4z M8 9h8 M8 13h8 M8 17h4",
    personas: "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M20 8v6 M23 11h-6",
    pasantes: "M12 3 2 8l10 5 10-5-10-5ZM4 10v6l8 5 8-5v-6 M22 8v7",
  };

  return (
    <svg
      aria-hidden="true"
      className="h-6 w-6"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d={paths[name]} />
    </svg>
  );
}

function IndicatorCard({
  title,
  value,
  description,
  icon,
  accent,
}: {
  title: string;
  value: number;
  description: string;
  icon: string;
  accent: string;
}) {
  return (
    <article className="rounded-lg border border-ui-border bg-surface p-4 text-foreground shadow-sm sm:p-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="text-sm font-semibold text-foreground/75">{title}</h3>
          <p className="mt-2 text-3xl font-bold tabular-nums">
            {formatNumber(value)}
          </p>
        </div>
        <span className={`rounded-md bg-page p-2 ${accent}`}>
          <Icon name={icon} />
        </span>
      </div>
      <p className="mt-3 text-sm text-foreground/70">{description}</p>
    </article>
  );
}

function SummaryCard({
  title,
  value,
  description,
}: {
  title: string;
  value: number;
  description: string;
}) {
  return (
    <article className="rounded-lg border border-ui-border bg-surface p-4 text-foreground">
      <h3 className="text-sm font-semibold text-foreground/70">{title}</h3>
      <p className="mt-1 text-2xl font-bold tabular-nums">
        {formatNumber(value)}
      </p>
      <p className="mt-1 text-sm text-foreground/70">{description}</p>
    </article>
  );
}

export default function EstadisticasDashboard() {
  const { user } = useAuth();
  const rol = user?.rol?.nombre ?? null;
  const mostrarSelectorUnidad = canFilterByFaculty(
    rol,
    user?.alcance_global,
  );
  const unidadesQuery = useQuery({
    queryKey: ["unidades-academicas"],
    queryFn: () => unidadesService.list(),
    enabled: mostrarSelectorUnidad,
  });
  const [draft, setDraft] = useState<EstadisticaFilterDraft>(initialDraft);
  const [appliedFilters, setAppliedFilters] =
    useState<EstadisticaFilterValues>({});
  const [errorValidacion, setErrorValidacion] = useState<string | null>(null);
  const filtersForRequest = mostrarSelectorUnidad
    ? appliedFilters
    : {
        ...(appliedFilters.fecha_desde
          ? { fecha_desde: appliedFilters.fecha_desde }
          : {}),
        ...(appliedFilters.fecha_hasta
          ? { fecha_hasta: appliedFilters.fecha_hasta }
          : {}),
        ...(appliedFilters.tipo ? { tipo: appliedFilters.tipo } : {}),
        ...(appliedFilters.dias !== undefined
          ? { dias: appliedFilters.dias }
          : {}),
      };
  const estadisticasQuery = useEstadisticas(filtersForRequest);
  const unidadFija = user?.unidad_academica
    ? `${user.unidad_academica.sigla} — ${user.unidad_academica.nombre}`
    : null;
  const filtrosActivos = Object.values(filtersForRequest).some(
    (value) => value !== undefined && value !== "",
  );

  function updateDraft(field: keyof EstadisticaFilterDraft, value: string) {
    setDraft((current) => ({ ...current, [field]: value }));
    setErrorValidacion(null);
  }

  function applyFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (
      draft.fecha_desde &&
      draft.fecha_hasta &&
      draft.fecha_desde > draft.fecha_hasta
    ) {
      setErrorValidacion(
        "La fecha desde no puede ser posterior a la fecha hasta.",
      );
      return;
    }

    const nextFilters: EstadisticaFilterValues = {};
    if (draft.fecha_desde) nextFilters.fecha_desde = draft.fecha_desde;
    if (draft.fecha_hasta) nextFilters.fecha_hasta = draft.fecha_hasta;
    if (draft.tipo) nextFilters.tipo = draft.tipo;

    if (mostrarSelectorUnidad && draft.id_unidad_academica) {
      const idUnidad = Number(draft.id_unidad_academica);
      const unidadValida = unidadesQuery.data?.some(
        (unidad) => unidad.id_unidad_academica === idUnidad,
      );
      if (!Number.isSafeInteger(idUnidad) || !unidadValida) {
        setErrorValidacion(
          "Seleccioná una unidad académica disponible en la lista.",
        );
        return;
      }
      nextFilters.id_unidad_academica = idUnidad;
    }

    setErrorValidacion(null);
    setAppliedFilters(nextFilters);
  }

  function clearFilters() {
    setDraft(initialDraft);
    setErrorValidacion(null);
    setAppliedFilters({});
  }

  const data = estadisticasQuery.data?.data;
  const isLoading = estadisticasQuery.isPending || estadisticasQuery.isFetching;
  const porAnio = data?.por_anio
    .slice()
    .sort((a: EstadisticaPorAnio, b: EstadisticaPorAnio) => a.anio - b.anio);

  return (
    <div className="space-y-6">
      <header>
        <div className="w-fit">
          <h1 className="text-3xl font-bold sm:text-4xl">Estadísticas</h1>
          <div aria-hidden="true" className="mt-2 h-1 w-full rounded-full bg-brand" />
        </div>
        <p className="mt-1 text-foreground/70">
          Visualización y análisis de los convenios gestionados en el sistema.
        </p>
      </header>

      <EstadisticaFilters
        draft={draft}
        unidades={unidadesQuery.data ?? []}
        mostrarSelectorUnidad={mostrarSelectorUnidad}
        unidadFija={unidadFija}
        unidadesCargando={unidadesQuery.isFetching}
        errorUnidades={
          unidadesQuery.error ? getErrorMessage(unidadesQuery.error) : null
        }
        errorValidacion={errorValidacion}
        filtrosActivos={filtrosActivos}
        onChange={updateDraft}
        onApply={applyFilters}
        onClear={clearFilters}
        onRetryUnits={() => void unidadesQuery.refetch()}
      />

      {isLoading ? (
        <div
          role="status"
          aria-live="polite"
          className="rounded-lg border border-ui-border bg-surface p-6 text-foreground"
        >
          Cargando estadísticas...
        </div>
      ) : estadisticasQuery.error ? (
        <div
          role="alert"
          className="rounded-lg border border-ui-border bg-surface p-6 text-red-700 dark:text-red-300"
        >
          <p>{getErrorMessage(estadisticasQuery.error)}</p>
          <button
            type="button"
            onClick={() => void estadisticasQuery.refetch()}
            className="mt-3 min-h-11 rounded-md border border-ui-border px-4 py-2 font-semibold text-foreground hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
          >
            Reintentar
          </button>
        </div>
      ) : data && hasExpectedIndicators(data.indicadores) ? (
        <div className="space-y-6">
          {data.totales.convenios === 0 ? (
            <p
              role="status"
              className="rounded-lg border border-ui-border bg-surface p-4 text-foreground"
            >
              No hay convenios para los filtros seleccionados.
            </p>
          ) : null}

          <section aria-labelledby="estadisticas-indicadores">
            <h2
              id="estadisticas-indicadores"
              className="mb-3 text-xl font-bold"
            >
              Indicadores principales
            </h2>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
              <IndicatorCard
                title="Convenios vigentes"
                value={data.indicadores.convenios_vigentes}
                description="Firmados y dentro de su período de vigencia."
                icon="vigentes"
                accent="text-brand-text"
              />
              <IndicatorCard
                title="Convenios en trámite"
                value={data.indicadores.convenios_en_tramite}
                description="En los estados de tramitación definidos por el sistema."
                icon="tramite"
                accent="text-orange-600 dark:text-orange-300"
              />
              <IndicatorCard
                title="Firmados durante el último año"
                value={data.indicadores.firmados_ultimo_anio}
                description="Firmas registradas en los últimos doce meses."
                icon="firmados"
                accent="text-amber-600 dark:text-amber-300"
              />
              <IndicatorCard
                title="Personas únicas"
                value={data.indicadores.personas_unicas}
                description="Personas distintas registradas como firmantes."
                icon="personas"
                accent="text-brand-text"
              />
              <IndicatorCard
                title="Pasantes únicos"
                value={data.indicadores.pasantes_unicos}
                description="Estudiantes distintos vinculados a pasantías."
                icon="pasantes"
                accent="text-orange-600 dark:text-orange-300"
              />
            </div>
          </section>

          <section aria-labelledby="estadisticas-graficos">
            <h2
              id="estadisticas-graficos"
              className="mb-3 text-xl font-bold"
            >
              Distribución de convenios
            </h2>
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
              <ConveniosPorEstadoChart data={data.por_estado} />
              <ConveniosPorTipoChart data={data.por_tipo} />
              <FirmasPorAnioChart data={porAnio ?? []} />
              <ConveniosPorUnidadChart data={data.por_unidad_academica} />
            </div>
          </section>

          <section aria-labelledby="estadisticas-vencimientos">
            <h2
              id="estadisticas-vencimientos"
              className="mb-3 text-xl font-bold"
            >
              Vencimientos
            </h2>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <SummaryCard
                title="Convenios vencidos"
                value={data.vencimientos.vencidos}
                description="Con fecha de vencimiento anterior a hoy."
              />
              <SummaryCard
                title="Próximos a vencer"
                value={data.vencimientos.proximos}
                description={`Dentro de los próximos ${data.vencimientos.dias} días.`}
              />
              <SummaryCard
                title="En período de preaviso"
                value={data.vencimientos.en_preaviso}
                description="Convenios que alcanzaron el plazo de preaviso."
              />
            </div>
          </section>
        </div>
      ) : data ? (
        <p
          role="alert"
          className="rounded-lg border border-ui-border bg-surface p-6 text-red-700 dark:text-red-300"
        >
          La respuesta de la API no incluye los indicadores de estadísticas
          esperados.
        </p>
      ) : (
        <p
          role="alert"
          className="rounded-lg border border-ui-border bg-surface p-6 text-red-700 dark:text-red-300"
        >
          La API no devolvió los datos de estadísticas esperados.
        </p>
      )}
    </div>
  );
}
