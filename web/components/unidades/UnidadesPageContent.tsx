"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import ConfirmDialog from "@/components/feedback/ConfirmDialog";
import UnidadFilters from "@/components/unidades/UnidadFilters";
import UnidadFormPanel from "@/components/unidades/UnidadForm";
import UnidadTable from "@/components/unidades/UnidadTable";
import { usePermissions } from "@/context/PermissionContext";
import { useSetUnidadActivo, useUnidades } from "@/hooks/useUnidades";
import { canChangeInstitucionOfRecord, canCreateUnidad, canEditUnidad, canToggleCatalogo } from "@/lib/permissions";
import { listAll } from "@/services/instituciones-firmantes";
import { ApiError } from "@/services/api-client";
import type { UnidadAcademica } from "@/types/unidad";

type PanelState =
  | { type: "closed" }
  | { type: "create" }
  | { type: "edit"; unidad: UnidadAcademica };

function withQuery(pathname: string, params: URLSearchParams): string {
  const query = params.toString();
  return query ? `${pathname}?${query}` : pathname;
}

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 403) {
    return "No tenés permisos para consultar esta información.";
  }
  return error instanceof Error
    ? error.message
    : "Ocurrió un error inesperado. Intentá nuevamente.";
}

function summarizeEstado(resumen: {
  unidades_desactivadas: number;
  usuarios_desactivados: number;
  representantes_desactivados: number;
}): string {
  const plural = (count: number, singular: string, pluralForm: string) =>
    `${count} ${count === 1 ? singular : pluralForm}`;
  return `Resumen: ${plural(resumen.unidades_desactivadas, "unidad desactivada", "unidades desactivadas")}, ${plural(resumen.usuarios_desactivados, "usuario desactivado", "usuarios desactivados")} y ${plural(resumen.representantes_desactivados, "representante desactivado", "representantes desactivados")}.`;
}

function parseInstitutionId(value: string): number | undefined {
  if (!/^[1-9]\d*$/.test(value)) return undefined;
  const parsed = Number(value);
  return Number.isSafeInteger(parsed) ? parsed : undefined;
}

function AccessDenied() {
  return (
    <section aria-labelledby="unidades-title" className="space-y-5">
      <div className="w-fit">
        <h1
          id="unidades-title"
          className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
        >
          Unidades académicas
        </h1>
        <div aria-hidden="true" className="mt-2 h-1 w-full rounded-full bg-brand" />
      </div>
      <p
        role="alert"
        className="rounded-lg border border-ui-border bg-surface p-6 text-red-700 dark:text-red-300"
      >
        No tenés permisos para acceder a la gestión de unidades académicas.
      </p>
    </section>
  );
}

function UnidadesList() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { role, unitId } = usePermissions();
  const [draftBuscar, setDraftBuscar] = useState(searchParams.get("buscar") ?? "");
  const [panel, setPanel] = useState<PanelState>({ type: "closed" });
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [confirmUnidad, setConfirmUnidad] = useState<UnidadAcademica | null>(null);
  const [confirmError, setConfirmError] = useState<string | null>(null);
  const lastNavigatedBuscarRef = useRef(searchParams.get("buscar") ?? "");
  const returnFocusRef = useRef<HTMLButtonElement | null>(null);
  const buscar = searchParams.get("buscar") ?? "";
  const institutionId = searchParams.get("id_institucion") ?? "";
  const activeFilter = searchParams.get("activo") ?? "";

  useEffect(() => {
    if (buscar === lastNavigatedBuscarRef.current) return;
    lastNavigatedBuscarRef.current = buscar;
    setDraftBuscar(buscar);
  }, [buscar]);

  useEffect(() => {
    const newSearch = draftBuscar.trim();
    if (buscar === newSearch) return;
    const timeout = window.setTimeout(() => {
      const params = new URLSearchParams(window.location.search);
      if (newSearch) params.set("buscar", newSearch);
      else params.delete("buscar");
      lastNavigatedBuscarRef.current = newSearch;
      router.push(withQuery(pathname, params), { scroll: false });
    }, 400);
    return () => window.clearTimeout(timeout);
  }, [buscar, draftBuscar, pathname, router]);

  const filters = useMemo(() => ({
    ...(parseInstitutionId(institutionId)
      ? { id_institucion: parseInstitutionId(institutionId) }
      : {}),
    ...(buscar ? { buscar } : {}),
    ...(activeFilter === "true"
      ? { activo: true }
      : activeFilter === "false"
        ? { activo: false }
        : {}),
  }), [activeFilter, buscar, institutionId]);

  const unidadesQuery = useUnidades(filters);
  const institucionesQuery = useQuery({
    queryKey: ["instituciones-firmantes", "list-all"],
    queryFn: () => listAll(),
  });
  const toggleMutation = useSetUnidadActivo();
  const unidades = unidadesQuery.data ?? [];
  const activeInstitutions = (institucionesQuery.data ?? []).filter(
    (institucion) => institucion.activo,
  );

  function updateFilter(key: string, value: string) {
    const params = new URLSearchParams(searchParams.toString());
    const currentSearch = draftBuscar.trim();
    if (currentSearch) params.set("buscar", currentSearch);
    else params.delete("buscar");
    if (value) params.set(key, value);
    else params.delete(key);
    lastNavigatedBuscarRef.current = currentSearch;
    router.push(withQuery(pathname, params), { scroll: false });
  }

  function clearFilters() {
    lastNavigatedBuscarRef.current = "";
    setDraftBuscar("");
    const params = new URLSearchParams(searchParams.toString());
    params.delete("buscar");
    params.delete("id_institucion");
    params.delete("activo");
    router.push(withQuery(pathname, params), { scroll: false });
  }

  function closePanel() {
    setPanel({ type: "closed" });
    window.requestAnimationFrame(() => returnFocusRef.current?.focus());
  }

  function openCreate(button: HTMLButtonElement) {
    returnFocusRef.current = button;
    setSuccessMessage(null);
    setPanel({ type: "create" });
  }

  function openEdit(unidad: UnidadAcademica, button: HTMLButtonElement) {
    returnFocusRef.current = button;
    setSuccessMessage(null);
    setPanel({ type: "edit", unidad });
  }

  async function confirmToggle() {
    if (!confirmUnidad || toggleMutation.isPending) return;
    setConfirmError(null);
    try {
      const response = await toggleMutation.mutateAsync({
        id: confirmUnidad.id_unidad_academica,
        activo: !confirmUnidad.activo,
      });
      setSuccessMessage(
        `${response.message} ${summarizeEstado(response.resumen)}`,
      );
      setConfirmUnidad(null);
    } catch (error) {
      setConfirmError(
        error instanceof ApiError && error.status === 403
          ? "No tenés permisos para realizar esta acción."
          : error instanceof ApiError && error.status === 409
            ? error.message
            : "No se pudo cambiar el estado de la unidad. Intentá nuevamente.",
      );
    }
  }

  return (
    <section aria-labelledby="unidades-title" className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="w-fit">
          <h1
            id="unidades-title"
            className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
          >
            Unidades académicas
          </h1>
          <div aria-hidden="true" className="mt-2 h-1 w-full rounded-full bg-brand" />
        </div>
        {canCreateUnidad(role) ? (
          <button
            type="button"
            onClick={(event) => openCreate(event.currentTarget)}
            className="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-5 py-2.5 font-condensed font-bold text-white shadow-sm hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
          >
            Nueva unidad
          </button>
        ) : null}
      </div>

      <UnidadFilters
        buscar={draftBuscar}
        institucion={institutionId}
        activo={activeFilter === "true" || activeFilter === "false" ? activeFilter : ""}
        instituciones={institucionesQuery.data ?? []}
        institucionesCargando={institucionesQuery.isPending}
        institucionesError={
          institucionesQuery.isError
            ? `No se pudieron cargar las instituciones: ${getErrorMessage(institucionesQuery.error)}`
            : null
        }
        onBuscarChange={setDraftBuscar}
        onInstitucionChange={(value) => updateFilter("id_institucion", value)}
        onActivoChange={(value) => updateFilter("activo", value)}
        onLimpiar={clearFilters}
        onReintentarInstituciones={() => void institucionesQuery.refetch()}
      />

      {successMessage ? (
        <p role="status" aria-live="polite" aria-atomic="true" className="font-semibold text-green-800 dark:text-green-300">
          {successMessage}
        </p>
      ) : null}

      {panel.type !== "closed" ? (
        <UnidadFormPanel
          key={
            panel.type === "edit"
              ? `unidad-${panel.unidad.id_unidad_academica}`
              : "unidad-nueva"
          }
          unidad={panel.type === "edit" ? panel.unidad : null}
          instituciones={
            panel.type === "create"
              ? activeInstitutions
              : (institucionesQuery.data ?? []).filter(
                  (institucion) =>
                    institucion.activo ||
                    institucion.id_institucion ===
                      (panel.type === "edit"
                        ? panel.unidad.id_institucion
                        : undefined),
                )
          }
          institutionIsReadOnly={
            !canChangeInstitucionOfRecord(role) && panel.type === "edit"
          }
          onClose={closePanel}
          onSuccess={(message) => {
            setSuccessMessage(message);
            closePanel();
          }}
        />
      ) : null}

      <p aria-live="polite" aria-atomic="true" role="status">
        {unidadesQuery.data
          ? `Se encontraron ${unidades.length} unidades académicas.`
          : unidadesQuery.isPending
            ? "Cargando unidades académicas..."
            : ""}
      </p>
      <div aria-busy={unidadesQuery.isFetching}>
        {unidadesQuery.isPending && !unidadesQuery.data ? (
          <p role="status" aria-live="polite" className="rounded-lg border border-ui-border bg-surface p-6">
            Cargando unidades académicas...
          </p>
        ) : null}
        {unidadesQuery.isError ? (
          <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6 text-foreground">
            <p role="alert" className="text-red-700 dark:text-red-300">
              No se pudieron cargar las unidades académicas:{" "}
              {getErrorMessage(unidadesQuery.error)}
            </p>
            <button
              type="button"
              onClick={() => void unidadesQuery.refetch()}
              className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
              Reintentar
            </button>
          </div>
        ) : null}
        {unidadesQuery.data && unidades.length === 0 ? (
          <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6 text-foreground">
            <p>No hay unidades académicas que coincidan con los filtros.</p>
            <button
              type="button"
              onClick={clearFilters}
              className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
              Limpiar filtros
            </button>
          </div>
        ) : null}
        {unidades.length > 0 ? (
          <UnidadTable
            unidades={unidades}
            ownUnitId={unitId}
            canEdit={(unidad) =>
              canEditUnidad(role, unitId, unidad.id_unidad_academica)
            }
            canToggle={canToggleCatalogo(role)}
            onEdit={openEdit}
            onToggle={(unidad, button) => {
              returnFocusRef.current = button;
              setConfirmError(null);
              setConfirmUnidad(unidad);
            }}
          />
        ) : null}
      </div>

      <ConfirmDialog
        open={Boolean(confirmUnidad)}
        title={
          confirmUnidad?.activo
            ? "Desactivar unidad académica"
            : "Reactivar unidad académica"
        }
        description={
          confirmUnidad?.activo
            ? "Al desactivar esta unidad, también se desactivan sus usuarios y se les cierra la sesión. Los usuarios no se reactivan automáticamente."
            : "Al reactivar esta unidad, los usuarios desactivados por la baja no se reactivan automáticamente."
        }
        confirmLabel={confirmUnidad?.activo ? "Desactivar" : "Activar"}
        loading={toggleMutation.isPending}
        errorMessage={confirmError}
        returnFocusRef={returnFocusRef}
        onConfirm={() => void confirmToggle()}
        onCancel={() => {
          setConfirmError(null);
          setConfirmUnidad(null);
        }}
      />
    </section>
  );
}

export default function UnidadesPageContent() {
  const { can } = usePermissions();
  return can("catalogos:write") ? <UnidadesList /> : <AccessDenied />;
}
