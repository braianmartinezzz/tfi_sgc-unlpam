"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { ApiError } from "@/services/api-client";
import UsuarioFilters from "@/components/usuarios/UsuarioFilters";
import UsuarioTable from "@/components/usuarios/UsuarioTable";
import ConvenioPagination from "@/components/convenios/ConvenioPagination";
import { usePermissions } from "@/context/PermissionContext";
import { useRoles } from "@/hooks/useRoles";
import { useUnidades } from "@/hooks/useUnidades";
import { useUsuarios } from "@/hooks/useUsuarios";
import type { UsuarioFilters as UsuarioListFilters } from "@/services/usuarios";

function parsePage(value: string | null): number {
  const page = Number(value);
  return Number.isSafeInteger(page) && page > 0 ? page : 1;
}

function parsePositiveInteger(value: string | null): number | undefined {
  if (!value || !/^[1-9]\d*$/.test(value)) return undefined;
  const parsed = Number(value);
  return Number.isSafeInteger(parsed) ? parsed : undefined;
}

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 403) {
    return "No tenés permisos para consultar esta información.";
  }
  return error instanceof Error
    ? error.message
    : "Ocurrió un error inesperado. Intentá nuevamente.";
}

function withQuery(pathname: string, params: URLSearchParams): string {
  const query = params.toString();
  return query ? `${pathname}?${query}` : pathname;
}

function AccessDenied() {
  return (
    <section
      aria-labelledby="usuarios-title"
      className="space-y-5"
    >
      <div>
        <h1
          id="usuarios-title"
          className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
        >
          Gestión de usuarios
        </h1>
        <div aria-hidden="true" className="mt-2 h-1 w-16 rounded-full bg-brand" />
      </div>
      <p
        role="alert"
        className="rounded-lg border border-ui-border bg-surface p-6 text-red-700 dark:text-red-300"
      >
        No tenés permisos para acceder a la gestión de usuarios.
      </p>
    </section>
  );
}

function UsuariosList() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const [draftRevision, setDraftRevision] = useState(0);
  const draftsRef = useRef({
    buscar: searchParams.get("buscar") ?? "",
  });
  const page = parsePage(searchParams.get("page"));
  const buscar = searchParams.get("buscar") ?? "";

  useEffect(() => {
    draftsRef.current.buscar = buscar;
  }, [buscar]);

  useEffect(() => {
    const buscarNuevo = draftsRef.current.buscar.trim();
    if (buscar === buscarNuevo) return;

    const timeout = window.setTimeout(() => {
      const params = new URLSearchParams(window.location.search);
      if (buscarNuevo) params.set("buscar", buscarNuevo);
      else params.delete("buscar");
      params.delete("page");
      router.push(withQuery(pathname, params), { scroll: false });
    }, 300);

    return () => window.clearTimeout(timeout);
  }, [buscar, draftRevision, pathname, router]);

  const filters = useMemo<UsuarioListFilters>(() => {
    const rolId = parsePositiveInteger(searchParams.get("id_rol"));
    const unidadId = parsePositiveInteger(searchParams.get("id_unidad"));
    const activo = searchParams.get("activo");

    return {
      ...(rolId ? { id_rol: rolId } : {}),
      ...(unidadId ? { id_unidad: unidadId } : {}),
      ...(activo === "true"
        ? { activo: true }
        : activo === "false"
          ? { activo: false }
          : {}),
      ...(buscar ? { buscar } : {}),
      page,
    };
  }, [buscar, page, searchParams]);

  const usuariosQuery = useUsuarios(filters);
  const rolesQuery = useRoles();
  const unidadesQuery = useUnidades();
  const data = usuariosQuery.data;
  const lastPage = Math.max(data?.last_page ?? 1, 1);
  const currentPage = Math.min(
    Math.max(data?.current_page ?? page, 1),
    lastPage,
  );
  const rolId = parsePositiveInteger(searchParams.get("id_rol"));
  const unidadId = parsePositiveInteger(searchParams.get("id_unidad"));
  const activo = searchParams.get("activo");

  useEffect(() => {
    if (data && !usuariosQuery.isPlaceholderData && page > lastPage) {
      const params = new URLSearchParams(searchParams.toString());
      params.set("page", String(lastPage));
      router.replace(withQuery(pathname, params), { scroll: false });
    }
  }, [
    data,
    lastPage,
    page,
    pathname,
    router,
    searchParams,
    usuariosQuery.isPlaceholderData,
  ]);

  function updateFilter(key: string, value: string) {
    const params = new URLSearchParams(searchParams.toString());
    const currentSearch = draftsRef.current.buscar.trim();
    if (currentSearch) params.set("buscar", currentSearch);
    else params.delete("buscar");
    if (value) params.set(key, value);
    else params.delete(key);
    params.delete("page");
    router.push(withQuery(pathname, params), { scroll: false });
  }

  function clearFilters() {
    draftsRef.current.buscar = "";
    setDraftRevision((revision) => revision + 1);
    router.push(pathname, { scroll: false });
  }

  function changePage(nextPage: number) {
    const params = new URLSearchParams(searchParams.toString());
    params.set("page", String(nextPage));
    router.push(withQuery(pathname, params), { scroll: false });
  }

  return (
    <section aria-labelledby="usuarios-title" className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1
            id="usuarios-title"
            className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
          >
            Gestión de usuarios
          </h1>
          <div aria-hidden="true" className="mt-2 h-1 w-16 rounded-full bg-brand" />
        </div>
        <Link
          href="/usuarios/new"
          onClick={(event) => {
            if (
              event.defaultPrevented ||
              event.button !== 0 ||
              event.metaKey ||
              event.ctrlKey ||
              event.shiftKey ||
              event.altKey
            ) {
              return;
            }

            event.preventDefault();
            router.push(`/usuarios/new?visit=${window.crypto.randomUUID()}`);
          }}
          className="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-5 py-2.5 font-condensed font-bold text-white shadow-sm hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
        >
          Nuevo usuario
        </Link>
      </div>

      <UsuarioFilters
        buscar={buscar}
        rol={rolId ? String(rolId) : ""}
        unidad={unidadId ? String(unidadId) : ""}
        estado={activo === "true" || activo === "false" ? activo : ""}
        roles={rolesQuery.data ?? []}
        unidades={unidadesQuery.data ?? []}
        rolesCargando={rolesQuery.isPending}
        unidadesCargando={unidadesQuery.isPending}
        errorRoles={
          rolesQuery.isError
            ? `No se pudieron cargar los roles: ${getErrorMessage(rolesQuery.error)}`
            : null
        }
        errorUnidades={
          unidadesQuery.isError
            ? `No se pudieron cargar las unidades: ${getErrorMessage(unidadesQuery.error)}`
            : null
        }
        onBuscarChange={(value) => {
          draftsRef.current.buscar = value;
          setDraftRevision((revision) => revision + 1);
        }}
        onRolChange={(value) => updateFilter("id_rol", value)}
        onUnidadChange={(value) => updateFilter("id_unidad", value)}
        onEstadoChange={(value) => updateFilter("activo", value)}
        onLimpiar={clearFilters}
        onReintentarRoles={() => void rolesQuery.refetch()}
        onReintentarUnidades={() => void unidadesQuery.refetch()}
      />

      <p aria-live="polite" aria-atomic="true" role="status">
        {usuariosQuery.isPlaceholderData
          ? `Cargando página ${page}...`
          : data
            ? `Se encontraron ${data.total} usuarios. Página ${currentPage} de ${lastPage}.`
            : usuariosQuery.isPending
              ? "Cargando usuarios..."
              : ""}
      </p>

      <div aria-busy={usuariosQuery.isFetching}>
        {usuariosQuery.isPending && !data ? (
          <p
            role="status"
            aria-live="polite"
            className="rounded-lg border border-ui-border bg-surface p-6 text-foreground"
          >
            Cargando usuarios...
          </p>
        ) : null}
        {usuariosQuery.isError ? (
          <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6 text-foreground">
            <p role="alert" className="text-red-700 dark:text-red-300">
              No se pudieron cargar los usuarios:{" "}
              {getErrorMessage(usuariosQuery.error)}
            </p>
            <button
              type="button"
              onClick={() => void usuariosQuery.refetch()}
              className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
              Reintentar
            </button>
          </div>
        ) : null}
        {data && data.data.length === 0 ? (
          <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6 text-foreground">
            <p>No hay usuarios que coincidan con los filtros.</p>
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
            <UsuarioTable usuarios={data.data} />
            <ConvenioPagination
              ariaLabel="Paginación de usuarios"
              currentPage={currentPage}
              lastPage={lastPage}
              total={data.total}
              onPageChange={changePage}
            />
          </>
        ) : null}
      </div>
    </section>
  );
}

export default function UsuariosPageContent() {
  const { can } = usePermissions();

  if (!can("usuarios:write")) return <AccessDenied />;

  return <UsuariosList />;
}
