"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import ConfirmDialog from "@/components/feedback/ConfirmDialog";
import ConvenioFilters from "@/components/convenios/ConvenioFilters";
import ConvenioPagination from "@/components/convenios/ConvenioPagination";
import ConvenioTable from "@/components/convenios/ConvenioTable";
import { useAuth } from "@/context/AuthContext";
import { useConvenios, useRemoveConvenio } from "@/hooks/useConvenios";
import {
  canCreateConvenio,
  canFilterByFaculty,
  getVisibleConvenioStates,
} from "@/lib/permissions";
import { paginateItems } from "@/lib/pagination";
import * as unidadesService from "@/services/unidades";
import * as conveniosService from "@/services/convenios";
import type { ConvenioListFilters, ConvenioListItem } from "@/types/convenio";

function parsePage(value: string | null): number {
  const page = Number(value);
  return Number.isSafeInteger(page) && page > 0 ? page : 1;
}

function getErrorMessage(error: unknown): string {
  return error instanceof Error
    ? error.message
    : "Ocurrió un error inesperado. Intentá nuevamente.";
}

function withQuery(pathname: string, params: URLSearchParams): string {
  const query = params.toString();
  return query ? `${pathname}?${query}` : pathname;
}

export default function ConveniosPageContent() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { user } = useAuth();
  const [draftRevision, setDraftRevision] = useState(0);
  const [convenioAEliminar, setConvenioAEliminar] =
    useState<ConvenioListItem | null>(null);
  const [errorEliminar, setErrorEliminar] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState("");
  const [exportando, setExportando] = useState(false);
  const [errorExportar, setErrorExportar] = useState<string | null>(null);
  const botonEliminarRef = useRef<HTMLButtonElement | null>(null);
  const draftsRef = useRef({
    buscar: searchParams.get("buscar") ?? "",
    tipo: searchParams.get("tipo") ?? "",
  });
  const rol = user?.rol?.nombre ?? null;
  const mostrarFacultad = canFilterByFaculty(rol, user?.alcance_global);
  const estadosVisibles = getVisibleConvenioStates(rol);
  const page = parsePage(searchParams.get("page"));
  const resultadoRevision = searchParams.get("resultado");
  const mensajeRevision =
    resultadoRevision === "aprobado"
      ? "Convenio aprobado."
      : resultadoRevision === "devuelto"
        ? "Convenio devuelto a corrección."
        : null;

  useEffect(() => {
    draftsRef.current.buscar = searchParams.get("buscar") ?? "";
    draftsRef.current.tipo = searchParams.get("tipo") ?? "";
  }, [searchParams]);

  useEffect(() => {
    const buscarActual = searchParams.get("buscar") ?? "";
    const tipoActual = searchParams.get("tipo") ?? "";
    const buscarNuevo = draftsRef.current.buscar.trim();
    const tipoNuevo = draftsRef.current.tipo.trim();

    if (buscarActual === buscarNuevo && tipoActual === tipoNuevo) return;

    const timeout = window.setTimeout(() => {
      const params = new URLSearchParams(window.location.search);
      if (buscarNuevo) params.set("buscar", buscarNuevo);
      else params.delete("buscar");
      if (tipoNuevo) params.set("tipo", tipoNuevo);
      else params.delete("tipo");
      params.delete("page");
      router.push(withQuery(pathname, params), { scroll: false });
    }, 300);

    return () => window.clearTimeout(timeout);
  }, [draftRevision, pathname, router, searchParams]);

  const filters = useMemo<Omit<ConvenioListFilters, "page">>(() => {
    const estado = searchParams.get("estado");
    const tipoFiltro = searchParams.get("tipo");
    const buscarFiltro = searchParams.get("buscar");
    const esMarcoParam = searchParams.get("es_marco");
    const facultadParam = searchParams.get("id_unidad_academica");
    const facultadId = Number(facultadParam);

    return {
      ...(estado ? { estado } : {}),
      ...(tipoFiltro ? { tipo: tipoFiltro } : {}),
      ...(buscarFiltro ? { buscar: buscarFiltro } : {}),
      ...(esMarcoParam === "true"
        ? { es_marco: true }
        : esMarcoParam === "false"
          ? { es_marco: false }
          : {}),
      ...(mostrarFacultad &&
      Number.isSafeInteger(facultadId) &&
      facultadId > 0
        ? { id_unidad_academica: facultadId }
        : {}),
    };
  }, [mostrarFacultad, searchParams]);

  const conveniosQuery = useConvenios(filters);
  const unidadesQuery = useQuery({
    queryKey: ["unidades-academicas"],
    queryFn: () => unidadesService.list(),
    enabled: mostrarFacultad,
  });
  const removeMutation = useRemoveConvenio();
  const data = conveniosQuery.data;
  const paginatedData = paginateItems(data ?? [], page);
  const { currentPage, lastPage } = paginatedData;

  useEffect(() => {
    if (
      data &&
      !conveniosQuery.isPlaceholderData &&
      page > lastPage
    ) {
      const params = new URLSearchParams(searchParams.toString());
      params.set("page", String(lastPage));
      router.replace(withQuery(pathname, params), { scroll: false });
    }
  }, [
    data,
    conveniosQuery.isPlaceholderData,
    lastPage,
    page,
    pathname,
    router,
    searchParams,
  ]);

  function updateFilter(key: string, value: string) {
    const params = new URLSearchParams(searchParams.toString());
    const buscarActual = draftsRef.current.buscar.trim();
    const tipoActual = draftsRef.current.tipo.trim();
    if (buscarActual) params.set("buscar", buscarActual);
    else params.delete("buscar");
    if (tipoActual) params.set("tipo", tipoActual);
    else params.delete("tipo");
    if (value) params.set(key, value);
    else params.delete(key);
    params.delete("page");
    router.push(withQuery(pathname, params), { scroll: false });
  }

  function clearFilters() {
    draftsRef.current.buscar = "";
    draftsRef.current.tipo = "";
    setDraftRevision((revision) => revision + 1);
    setMensaje("");
    router.push(pathname, { scroll: false });
  }

  function changePage(nextPage: number) {
    const params = new URLSearchParams(searchParams.toString());
    params.set("page", String(nextPage));
    router.push(withQuery(pathname, params), { scroll: false });
  }

  function requestDelete(
    convenio: ConvenioListItem,
    trigger: HTMLButtonElement,
  ) {
    botonEliminarRef.current = trigger;
    setErrorEliminar(null);
    setConvenioAEliminar(convenio);
  }

  async function confirmDelete() {
    if (!convenioAEliminar || removeMutation.isPending) return;
    setErrorEliminar(null);
    try {
      const response = await removeMutation.mutateAsync(
        convenioAEliminar.id_instrumento,
      );
      setMensaje(response.message);
      setConvenioAEliminar(null);
    } catch (error) {
      setErrorEliminar(getErrorMessage(error));
    }
  }

  async function exportCsv() {
    if (exportando) return;
    setExportando(true);
    setErrorExportar(null);
    try {
      const exportFilters: Omit<ConvenioListFilters, "page"> = {
        ...(filters.estado ? { estado: filters.estado } : {}),
        ...(draftsRef.current.tipo.trim()
          ? { tipo: draftsRef.current.tipo.trim() }
          : {}),
        ...(filters.es_marco !== undefined
          ? { es_marco: filters.es_marco }
          : {}),
        ...(filters.id_unidad_academica !== undefined
          ? { id_unidad_academica: filters.id_unidad_academica }
          : {}),
        ...(draftsRef.current.buscar.trim()
          ? { buscar: draftsRef.current.buscar.trim() }
          : {}),
      };
      await conveniosService.exportar(exportFilters);
    } catch (error) {
      setErrorExportar(getErrorMessage(error));
    } finally {
      setExportando(false);
    }
  }

  return (
    <section aria-labelledby="convenios-title" className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="w-fit">
          <h1
            id="convenios-title"
            className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
          >
            Convenios Registrados
          </h1>
          <div aria-hidden="true" className="mt-2 h-1 w-full rounded-full bg-brand" />
        </div>
        {canCreateConvenio(rol) ? (
          <Link
            href="/convenios/new"
            className="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-5 py-2.5 font-condensed font-bold text-white shadow-sm hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
          >
            Nuevo convenio
          </Link>
        ) : null}
      </div>

      <ConvenioFilters
        buscar={searchParams.get("buscar") ?? ""}
        tipo={searchParams.get("tipo") ?? ""}
        esMarco={searchParams.get("es_marco") ?? ""}
        estado={searchParams.get("estado") ?? ""}
        facultad={searchParams.get("id_unidad_academica") ?? ""}
        estadosVisibles={estadosVisibles}
        mostrarFacultad={mostrarFacultad}
        unidades={unidadesQuery.data ?? []}
        unidadesCargando={unidadesQuery.isPending}
        errorUnidades={
          unidadesQuery.isError ? getErrorMessage(unidadesQuery.error) : null
        }
        onBuscarChange={(value) => {
          draftsRef.current.buscar = value;
          setDraftRevision((revision) => revision + 1);
        }}
        onTipoChange={(value) => {
          draftsRef.current.tipo = value;
          setDraftRevision((revision) => revision + 1);
        }}
        onEsMarcoChange={(value) => updateFilter("es_marco", value)}
        onEstadoChange={(value) => updateFilter("estado", value)}
        onFacultadChange={(value) =>
          updateFilter("id_unidad_academica", value)
        }
        onLimpiar={clearFilters}
        onReintentarUnidades={() => void unidadesQuery.refetch()}
      />

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p aria-live="polite" aria-atomic="true" role="status">
          {conveniosQuery.isPlaceholderData
            ? "Actualizando resultados..."
            : data
              ? `Se encontraron ${data.length} convenios. Página ${currentPage} de ${lastPage}.`
              : conveniosQuery.isPending
                ? "Cargando convenios..."
                : ""}
        </p>
        <button
          type="button"
          onClick={() => void exportCsv()}
          disabled={exportando || conveniosQuery.isPending}
          aria-busy={exportando}
          className="min-h-11 enabled:cursor-pointer rounded-md border border-brand px-4 py-2 font-semibold text-brand-text hover:bg-brand hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60"
        >
          {exportando ? "Exportando CSV..." : "Exportar CSV"}
        </button>
      </div>
      {mensajeRevision || mensaje ? (
        <p role="status" aria-live="polite" className="font-semibold text-green-800 dark:text-green-300">
          {mensajeRevision ?? mensaje}
        </p>
      ) : null}
      {errorExportar ? (
        <p role="alert" className="text-red-700 dark:text-red-300">
          No se pudo exportar el CSV: {errorExportar}
        </p>
      ) : null}

      <div aria-busy={conveniosQuery.isFetching}>
        {conveniosQuery.isPending && !data ? (
          <p role="status" aria-live="polite" className="rounded-lg border border-ui-border bg-surface p-6 text-foreground">
            Cargando convenios...
          </p>
        ) : null}
        {conveniosQuery.isError ? (
          <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6 text-foreground">
            <p role="alert" className="text-red-700 dark:text-red-300">
              No se pudieron cargar los convenios:{" "}
              {getErrorMessage(conveniosQuery.error)}
            </p>
            <button
              type="button"
              onClick={() => void conveniosQuery.refetch()}
              className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
              Reintentar
            </button>
          </div>
        ) : null}
        {data && data.length === 0 ? (
          <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6 text-foreground">
            <p>No hay convenios que coincidan con los filtros.</p>
            <button
              type="button"
              onClick={clearFilters}
              className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
              Limpiar filtros
            </button>
          </div>
        ) : null}
        {data && data.length > 0 ? (
          <>
            <ConvenioTable
              convenios={paginatedData.items}
              rol={rol}
              onEliminar={requestDelete}
            />
            <ConvenioPagination
              currentPage={currentPage}
              lastPage={lastPage}
              total={data.length}
              onPageChange={changePage}
            />
          </>
        ) : null}
      </div>

      <ConfirmDialog
        open={convenioAEliminar !== null}
        title="Eliminar convenio"
        description={
          convenioAEliminar
            ? `¿Querés eliminar el convenio ${convenioAEliminar.id_instrumento}? Esta acción no se puede deshacer.`
            : ""
        }
        confirmLabel="Eliminar"
        loading={removeMutation.isPending}
        errorMessage={errorEliminar}
        returnFocusRef={botonEliminarRef}
        onConfirm={() => void confirmDelete()}
        onCancel={() => {
          setErrorEliminar(null);
          setConvenioAEliminar(null);
        }}
      />
    </section>
  );
}
