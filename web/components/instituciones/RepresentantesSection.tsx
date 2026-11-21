"use client";

import { useRef, useState } from "react";
import ConfirmDialog from "@/components/feedback/ConfirmDialog";
import ConvenioPagination from "@/components/convenios/ConvenioPagination";
import RepresentanteForm from "@/components/instituciones/RepresentanteForm";
import RepresentanteTable from "@/components/instituciones/RepresentanteTable";
import { usePermissions } from "@/context/PermissionContext";
import { useRepresentantes, useSetRepresentanteActivo } from "@/hooks/useRepresentantes";
import { canManageRepresentantes, canToggleCatalogo } from "@/lib/permissions";
import { paginateItems } from "@/lib/pagination";
import { ApiError } from "@/services/api-client";
import type { InstitucionFirmante } from "@/types/unidad";
import type { Representante } from "@/types/representante";

type FormState =
  | { type: "closed" }
  | { type: "create" }
  | { type: "edit"; representante: Representante };

function getPlural(count: number, singular: string, pluralForm: string): string {
  return `${count} ${count === 1 ? singular : pluralForm}`;
}

interface RepresentantesSectionProps {
  institucion: InstitucionFirmante;
}

export default function RepresentantesSection({
  institucion,
}: RepresentantesSectionProps) {
  const { role, institutionId } = usePermissions();
  const [page, setPage] = useState(1);
  const [form, setForm] = useState<FormState>({ type: "closed" });
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [confirmRepresentante, setConfirmRepresentante] =
    useState<Representante | null>(null);
  const [confirmError, setConfirmError] = useState<string | null>(null);
  const returnFocusRef = useRef<HTMLButtonElement | null>(null);
  const representativesQuery = useRepresentantes({
    id_institucion: institucion.id_institucion,
  });
  const toggleMutation = useSetRepresentanteActivo();
  const data = representativesQuery.data;
  const paginatedData = paginateItems(data ?? [], page);
  const { currentPage, lastPage } = paginatedData;
  const canManage = canManageRepresentantes(
    role,
    institutionId,
    institucion.id_institucion,
  );
  const canToggle = canToggleCatalogo(role);

  function closeForm() {
    setForm({ type: "closed" });
    window.requestAnimationFrame(() => returnFocusRef.current?.focus());
  }

  async function confirmToggle() {
    if (!confirmRepresentante || toggleMutation.isPending) return;
    setConfirmError(null);
    try {
      const response = await toggleMutation.mutateAsync({
        id: confirmRepresentante.id_persona,
        activo: !confirmRepresentante.activo,
      });
      const summary =
        response.resumen.representantes_desactivados > 0
          ? ` ${getPlural(response.resumen.representantes_desactivados, "representante desactivado", "representantes desactivados")}.`
          : "";
      setSuccessMessage(`${response.message}${summary}`);
      setConfirmRepresentante(null);
    } catch (error) {
      setConfirmError(
        error instanceof ApiError && error.status === 403
          ? "No tenés permisos para realizar esta acción."
          : error instanceof ApiError && error.status === 409
            ? error.message
            : "No se pudo cambiar el estado del representante. Intentá nuevamente.",
      );
    }
  }

  return (
    <section aria-labelledby="representantes-title" className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h3 id="representantes-title" className="text-xl font-bold">
            Representantes
          </h3>
          <p className="mt-1 text-sm text-foreground/80">
            {institucion.razon_social}
          </p>
        </div>
        {canManage ? (
          <div>
            <button
              type="button"
              disabled={!institucion.activo}
              aria-describedby={
                !institucion.activo ? "representante-create-disabled" : undefined
              }
              onClick={(event) => {
                returnFocusRef.current = event.currentTarget;
                setSuccessMessage(null);
                setForm({ type: "create" });
              }}
              className="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            >
              Nuevo representante
            </button>
            {!institucion.activo ? (
              <p
                id="representante-create-disabled"
                className="max-w-64 text-xs text-foreground/80"
              >
                No podés crear representantes bajo una institución inactiva.
              </p>
            ) : null}
          </div>
        ) : null}
      </div>

      {successMessage ? (
        <p role="status" aria-live="polite" aria-atomic="true" className="font-semibold text-green-800 dark:text-green-300">
          {successMessage}
        </p>
      ) : null}

      {form.type !== "closed" ? (
        <RepresentanteForm
          key={
            form.type === "edit"
              ? `representante-${form.representante.id_persona}`
              : "representante-nuevo"
          }
          institucionId={institucion.id_institucion}
          representante={form.type === "edit" ? form.representante : null}
          onClose={closeForm}
          onSuccess={(message) => {
            setSuccessMessage(message);
            if (form.type === "create") setPage(1);
            closeForm();
          }}
        />
      ) : null}

      <p role="status" aria-live="polite" aria-atomic="true">
        {data
          ? `Se encontraron ${data.length} representantes. Página ${currentPage} de ${lastPage}.`
          : representativesQuery.isPending
            ? "Cargando representantes..."
            : ""}
      </p>
      <div aria-busy={representativesQuery.isFetching}>
        {representativesQuery.isPending && !data ? (
          <p role="status" aria-live="polite" className="rounded-lg border border-ui-border bg-surface p-5">
            Cargando representantes...
          </p>
        ) : null}
        {representativesQuery.isError ? (
          <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-5">
            <p role="alert" className="text-red-700 dark:text-red-300">
              No se pudieron cargar los representantes:{" "}
              {representativesQuery.error instanceof ApiError &&
              representativesQuery.error.status === 403
                ? "No tenés permisos para consultar esta información."
                : representativesQuery.error instanceof Error
                  ? representativesQuery.error.message
                  : "Ocurrió un error inesperado. Intentá nuevamente."}
            </p>
            <button
              type="button"
              onClick={() => void representativesQuery.refetch()}
              className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
              Reintentar
            </button>
          </div>
        ) : null}
        {data && data.length === 0 ? (
          <div className="rounded-lg border border-ui-border bg-surface p-5">
            <p>Esta institución todavía no tiene representantes registrados.</p>
          </div>
        ) : null}
        {data && data.length > 0 ? (
          <>
            <RepresentanteTable
              representantes={paginatedData.items}
              canManage={canManage}
              canToggle={canToggle}
              institutionActive={institucion.activo}
              onEdit={(representante, button) => {
                returnFocusRef.current = button;
                setSuccessMessage(null);
                setForm({ type: "edit", representante });
              }}
              onToggle={(representante, button) => {
                returnFocusRef.current = button;
                setConfirmError(null);
                setConfirmRepresentante(representante);
              }}
            />
            <ConvenioPagination
              ariaLabel="Paginación de representantes"
              currentPage={currentPage}
              lastPage={lastPage}
              total={data.length}
              onPageChange={setPage}
            />
          </>
        ) : null}
      </div>

      <ConfirmDialog
        open={Boolean(confirmRepresentante)}
        title={
          confirmRepresentante?.activo
            ? "Desactivar representante"
            : "Activar representante"
        }
        description={
          confirmRepresentante?.activo
            ? "¿Querés desactivar este representante?"
            : "Al activar este representante, los demás registros que estén desactivados no se reactivan automáticamente."
        }
        confirmLabel={confirmRepresentante?.activo ? "Desactivar" : "Activar"}
        loading={toggleMutation.isPending}
        errorMessage={confirmError}
        returnFocusRef={returnFocusRef}
        onConfirm={() => void confirmToggle()}
        onCancel={() => {
          setConfirmError(null);
          setConfirmRepresentante(null);
        }}
      />
    </section>
  );
}
