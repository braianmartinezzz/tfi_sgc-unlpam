"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import ConfirmDialog from "@/components/feedback/ConfirmDialog";
import ConvenioPagination from "@/components/convenios/ConvenioPagination";
import InstitucionFilters from "@/components/instituciones/InstitucionFilters";
import InstitucionFormPanel from "@/components/instituciones/InstitucionFormPanel";
import InstitucionTable from "@/components/instituciones/InstitucionTable";
import { usePermissions } from "@/context/PermissionContext";
import {
  useInstituciones,
  useSetInstitucionActivo,
} from "@/hooks/useInstituciones";
import {
  canCreateInstitucion,
  canEditInstitucion,
  canToggleCatalogo,
} from "@/lib/permissions";
import { ApiError } from "@/services/api-client";
import type { InstitucionFirmante } from "@/types/unidad";

type PanelState =
  | { type: "closed" }
  | { type: "create" }
  | { type: "edit"; institucion: InstitucionFirmante };

function parsePage(value: string | null): number {
  const parsed = Number(value);
  return Number.isSafeInteger(parsed) && parsed > 0 ? parsed : 1;
}

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

function plural(count: number, singular: string, pluralForm: string): string {
  return `${count} ${count === 1 ? singular : pluralForm}`;
}

function summarizeEstado(resumen: {
  unidades_desactivadas: number;
  usuarios_desactivados: number;
  representantes_desactivados: number;
}): string {
  return `Resumen: ${plural(resumen.unidades_desactivadas, "unidad desactivada", "unidades desactivadas")}, ${plural(resumen.usuarios_desactivados, "usuario desactivado", "usuarios desactivados")} y ${plural(resumen.representantes_desactivados, "representante desactivado", "representantes desactivados")}.`;
}

function AccessDenied() {
  return (
    <section aria-labelledby="instituciones-title" className="space-y-5">
      <div className="w-fit">
        <h1
          id="instituciones-title"
          className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
        >
          Instituciones firmantes
        </h1>
        <div aria-hidden="true" className="mt-2 h-1 w-full rounded-full bg-brand" />
      </div>
      <p
        role="alert"
        className="rounded-lg border border-ui-border bg-surface p-6 text-red-700 dark:text-red-300"
      >
        No tenés permisos para acceder a la gestión de instituciones firmantes.
      </p>
    </section>
  );
}

function InstitucionesList() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { role, institutionId } = usePermissions();
  const [draftBuscar, setDraftBuscar] = useState(searchParams.get("buscar") ?? "");
  const [panel, setPanel] = useState<PanelState>({ type: "closed" });
  const [initialTab, setInitialTab] = useState<"institucion" | "representantes">(
    "institucion",
  );
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [confirmInstitucion, setConfirmInstitucion] =
    useState<InstitucionFirmante | null>(null);
  const [confirmError, setConfirmError] = useState<string | null>(null);
  const lastNavigatedBuscarRef = useRef(searchParams.get("buscar") ?? "");
  const returnFocusRef = useRef<HTMLButtonElement | null>(null);
  const buscar = searchParams.get("buscar") ?? "";
  const activeFilter = searchParams.get("activo") ?? "";
  const page = parsePage(searchParams.get("page"));

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
      params.delete("page");
      lastNavigatedBuscarRef.current = newSearch;
      router.push(withQuery(pathname, params), { scroll: false });
    }, 400);
    return () => window.clearTimeout(timeout);
  }, [buscar, draftBuscar, pathname, router]);

  const filters = useMemo(() => ({
    ...(buscar ? { buscar } : {}),
    ...(activeFilter === "true"
      ? { activo: true }
      : activeFilter === "false"
        ? { activo: false }
        : {}),
    page,
  }), [activeFilter, buscar, page]);
  const institucionesQuery = useInstituciones(filters);
  const toggleMutation = useSetInstitucionActivo();
  const data = institucionesQuery.data;
  const lastPage = Math.max(data?.last_page ?? 1, 1);
  const currentPage = Math.min(Math.max(data?.current_page ?? page, 1), lastPage);

  useEffect(() => {
    if (data && !institucionesQuery.isPlaceholderData && page > lastPage) {
      const params = new URLSearchParams(searchParams.toString());
      params.set("page", String(lastPage));
      router.replace(withQuery(pathname, params), { scroll: false });
    }
  }, [
    data,
    institucionesQuery.isPlaceholderData,
    lastPage,
    page,
    pathname,
    router,
    searchParams,
  ]);

  function updateFilter(key: string, value: string) {
    const params = new URLSearchParams(searchParams.toString());
    const currentSearch = draftBuscar.trim();
    if (currentSearch) params.set("buscar", currentSearch);
    else params.delete("buscar");
    if (value) params.set(key, value);
    else params.delete(key);
    params.delete("page");
    lastNavigatedBuscarRef.current = currentSearch;
    router.push(withQuery(pathname, params), { scroll: false });
  }

  function clearFilters() {
    lastNavigatedBuscarRef.current = "";
    setDraftBuscar("");
    const params = new URLSearchParams(searchParams.toString());
    params.delete("buscar");
    params.delete("activo");
    params.delete("page");
    router.push(withQuery(pathname, params), { scroll: false });
  }

  function closePanel() {
    setPanel({ type: "closed" });
    setInitialTab("institucion");
    window.requestAnimationFrame(() => returnFocusRef.current?.focus());
  }

  function openCreate(button: HTMLButtonElement) {
    returnFocusRef.current = button;
    setSuccessMessage(null);
    setInitialTab("institucion");
    setPanel({ type: "create" });
  }

  function openEdit(institucion: InstitucionFirmante, button: HTMLButtonElement) {
    returnFocusRef.current = button;
    setSuccessMessage(null);
    setInitialTab("institucion");
    setPanel({ type: "edit", institucion });
  }

  async function confirmToggle() {
    if (!confirmInstitucion || toggleMutation.isPending) return;
    setConfirmError(null);
    try {
      const response = await toggleMutation.mutateAsync({
        id: confirmInstitucion.id_institucion,
        activo: !confirmInstitucion.activo,
      });
      setSuccessMessage(`${response.message} ${summarizeEstado(response.resumen)}`);
      setConfirmInstitucion(null);
    } catch (error) {
      setConfirmError(
        error instanceof ApiError && error.status === 403
          ? "No tenés permisos para realizar esta acción."
          : error instanceof ApiError && error.status === 409
            ? error.message
            : "No se pudo cambiar el estado de la institución. Intentá nuevamente.",
      );
    }
  }

  function changePage(nextPage: number) {
    const params = new URLSearchParams(searchParams.toString());
    params.set("page", String(nextPage));
    router.push(withQuery(pathname, params), { scroll: false });
  }

  return (
    <section aria-labelledby="instituciones-title" className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="w-fit">
          <h1
            id="instituciones-title"
            className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
          >
            Instituciones firmantes
          </h1>
          <div aria-hidden="true" className="mt-2 h-1 w-full rounded-full bg-brand" />
        </div>
        {canCreateInstitucion(role) ? (
          <button
            type="button"
            onClick={(event) => openCreate(event.currentTarget)}
            className="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-5 py-2.5 font-condensed font-bold text-white shadow-sm hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
          >
            Nueva institución
          </button>
        ) : null}
      </div>

      <InstitucionFilters
        buscar={draftBuscar}
        activo={activeFilter === "true" || activeFilter === "false" ? activeFilter : ""}
        onBuscarChange={setDraftBuscar}
        onActivoChange={(value) => updateFilter("activo", value)}
        onLimpiar={clearFilters}
      />

      {successMessage ? (
        <p role="status" aria-live="polite" aria-atomic="true" className="font-semibold text-green-800 dark:text-green-300">
          {successMessage}
        </p>
      ) : null}

      {panel.type !== "closed" ? (
        <InstitucionFormPanel
          key={
            panel.type === "edit"
              ? `institucion-${panel.institucion.id_institucion}`
              : "institucion-nueva"
          }
          institucion={panel.type === "edit" ? panel.institucion : null}
          initialTab={initialTab}
          onClose={closePanel}
          onCreated={(institucion, message) => {
            setSuccessMessage(message);
            setInitialTab("representantes");
            setPanel({ type: "edit", institucion });
          }}
          onSaved={(message) => {
            setSuccessMessage(message);
            closePanel();
          }}
        />
      ) : null}

      <p role="status" aria-live="polite" aria-atomic="true">
        {institucionesQuery.isPlaceholderData
          ? `Cargando página ${page}...`
          : data
            ? `Se encontraron ${data.total} instituciones. Página ${currentPage} de ${lastPage}.`
            : institucionesQuery.isPending
              ? "Cargando instituciones..."
              : ""}
      </p>
      <div aria-busy={institucionesQuery.isFetching}>
        {institucionesQuery.isPending && !data ? (
          <p role="status" aria-live="polite" className="rounded-lg border border-ui-border bg-surface p-6">
            Cargando instituciones...
          </p>
        ) : null}
        {institucionesQuery.isError ? (
          <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6 text-foreground">
            <p role="alert" className="text-red-700 dark:text-red-300">
              No se pudieron cargar las instituciones:{" "}
              {getErrorMessage(institucionesQuery.error)}
            </p>
            <button
              type="button"
              onClick={() => void institucionesQuery.refetch()}
              className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
              Reintentar
            </button>
          </div>
        ) : null}
        {data && data.data.length === 0 ? (
          <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6 text-foreground">
            <p>No hay instituciones que coincidan con los filtros.</p>
            <button
              type="button"
              onClick={clearFilters}
              className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
              Limpiar filtros
            </button>
          </div>
        ) : null}
        {data && data.data.length > 0 ? (
          <>
            <InstitucionTable
              instituciones={data.data}
              canEdit={(institucion) =>
                canEditInstitucion(
                  role,
                  institutionId,
                  institucion.id_institucion,
                )
              }
              canToggle={canToggleCatalogo(role)}
              onEdit={openEdit}
              onToggle={(institucion, button) => {
                returnFocusRef.current = button;
                setConfirmError(null);
                setConfirmInstitucion(institucion);
              }}
            />
            <ConvenioPagination
              ariaLabel="Paginación de instituciones firmantes"
              currentPage={currentPage}
              lastPage={lastPage}
              total={data.total}
              onPageChange={changePage}
            />
          </>
        ) : null}
      </div>

      <ConfirmDialog
        open={Boolean(confirmInstitucion)}
        title={
          confirmInstitucion?.activo
            ? "Desactivar institución"
            : "Reactivar institución"
        }
        description={
          confirmInstitucion?.activo
            ? "Al desactivar esta institución, también se desactivan sus unidades, los usuarios de esas unidades (se les cierra la sesión) y sus representantes. Los usuarios y representantes no se reactivan solos."
            : "Al reactivar esta institución, los usuarios y representantes desactivados por la baja no se reactivan automáticamente."
        }
        confirmLabel={confirmInstitucion?.activo ? "Desactivar" : "Activar"}
        loading={toggleMutation.isPending}
        errorMessage={confirmError}
        returnFocusRef={returnFocusRef}
        onConfirm={() => void confirmToggle()}
        onCancel={() => {
          setConfirmError(null);
          setConfirmInstitucion(null);
        }}
      />
    </section>
  );
}

export default function InstitucionesPageContent() {
  const { can } = usePermissions();
  return can("catalogos:write") ? <InstitucionesList /> : <AccessDenied />;
}
