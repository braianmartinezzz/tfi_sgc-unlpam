"use client";

import { useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
import ConfirmDialog from "@/components/feedback/ConfirmDialog";
import { ApiError } from "@/services/api-client";
import * as convenios from "@/services/convenios";

type ReviewAction = "approve" | "return";
type PendingReturn = {
  observationId: number;
  motivo: string;
};

const cardClassName =
  "space-y-4 rounded-lg border border-ui-border bg-surface p-4 text-foreground sm:p-5";

export default function PanelRevision({ id }: { id: number }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [observacion, setObservacion] = useState("");
  const [operation, setOperation] = useState<ReviewAction | null>(null);
  const [confirmationAction, setConfirmationAction] =
    useState<ReviewAction | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [partialReturnMessage, setPartialReturnMessage] = useState<
    string | null
  >(null);
  const [pendingReturn, setPendingReturn] = useState<PendingReturn | null>(
    null,
  );
  const processingRef = useRef(false);
  const approveButtonRef = useRef<HTMLButtonElement>(null);
  const returnFocusRef = useRef<HTMLButtonElement>(null);
  const texto = observacion.trim();
  const busy = operation !== null;

  function openConfirmation(
    action: ReviewAction,
    trigger: HTMLButtonElement,
  ) {
    returnFocusRef.current = trigger;
    setErrorMessage(null);
    setFieldError(null);
    setPartialReturnMessage(null);
    setConfirmationAction(action);
  }

  async function confirmAction() {
    if (!confirmationAction || processingRef.current) return;

    const action = confirmationAction;
    let stagedReturn = pendingReturn;
    processingRef.current = true;
    setOperation(action);
    setErrorMessage(null);
    setFieldError(null);
    setPartialReturnMessage(null);

    try {
      if (action === "approve") {
        await convenios.approve(id);
      } else {
        if (!stagedReturn) {
          const response = await convenios.createObservation(id, {
            contenido: texto,
          });
          stagedReturn = {
            observationId: response.data.id_observacion,
            motivo: texto,
          };
          setPendingReturn(stagedReturn);
        }

        await convenios.returnToCorrection(id, {
          motivo: stagedReturn.motivo,
        });
      }

      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: ["convenio", id],
          exact: true,
        }),
        queryClient.invalidateQueries({
          queryKey: ["convenio", id, "documentos"],
          exact: true,
        }),
        queryClient.invalidateQueries({ queryKey: ["convenios"] }),
      ]);
      router.push(
        `/convenios?resultado=${action === "approve" ? "aprobado" : "devuelto"}`,
      );
    } catch (error) {
      setErrorMessage(
        error instanceof ApiError
          ? error.message
          : error instanceof Error
            ? error.message
            : "No se pudo completar la acción. Intentá nuevamente.",
      );
      if (error instanceof ApiError) {
        setFieldError(
          error.errors?.contenido?.[0] ??
            error.errors?.motivo?.[0] ??
            null,
        );
      }
      if (action === "return" && stagedReturn) {
        setPartialReturnMessage(
          `La observación #${stagedReturn.observationId} ya se registró. Al reintentar, no se volverá a crear.`,
        );
      }
    } finally {
      processingRef.current = false;
      setOperation(null);
      setConfirmationAction(null);
    }
  }

  return (
    <aside aria-labelledby={`panel-revision-title-${id}`} className={cardClassName}>
      <h2
        id={`panel-revision-title-${id}`}
        className="font-condensed text-xl font-bold tracking-tight"
      >
        Panel de revisión
      </h2>

      {errorMessage ? (
        <p role="alert" aria-live="assertive" className="text-sm font-medium text-brand-text">
          {errorMessage}
        </p>
      ) : null}
      {partialReturnMessage ? (
        <p role="status" aria-live="polite" className="text-sm text-foreground/80">
          {partialReturnMessage}
        </p>
      ) : null}
      {busy ? (
        <p role="status" aria-live="polite" aria-atomic="true">
          Procesando…
        </p>
      ) : null}

      <div className="space-y-2">
        <label
          htmlFor={`observaciones-${id}`}
          className="block text-sm font-semibold"
        >
          Observaciones
        </label>
        <textarea
          id={`observaciones-${id}`}
          value={observacion}
          placeholder="Ingrese las observaciones sobre el convenio..."
          onChange={(event) => {
            setObservacion(event.currentTarget.value);
            setErrorMessage(null);
            setFieldError(null);
          }}
          readOnly={busy || pendingReturn !== null}
          aria-invalid={Boolean(fieldError)}
          aria-describedby={`observaciones-ayuda-${id} observaciones-accion-ayuda-${id}${
            fieldError ? ` observaciones-error-${id}` : ""
          }`}
          rows={7}
          className="w-full rounded-md border border-ui-border bg-page px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand read-only:opacity-80"
        />
        <p
          id={`observaciones-ayuda-${id}`}
          className="text-sm text-foreground/70"
        >
          Si escribís observaciones, el convenio se devolverá a corrección.
        </p>
        <p
          id={`observaciones-accion-ayuda-${id}`}
          className="text-sm text-foreground/80"
        >
          {pendingReturn
            ? `La observación #${pendingReturn.observationId} quedó registrada. Se conservará para reintentar la devolución.`
            : texto.length < 5
              ? "Escribí al menos 5 caracteres para devolver."
              : null}
        </p>
        {fieldError ? (
          <p
            id={`observaciones-error-${id}`}
            role="alert"
            className="text-sm font-medium text-brand-text"
          >
            {fieldError}
          </p>
        ) : null}
        {texto.length > 0 ? (
          <p role="status" aria-live="polite" className="text-sm text-foreground/80">
            Hay observaciones escritas; borralas para poder aprobar.
          </p>
        ) : null}
      </div>

      <div className="flex flex-col gap-3">
        <button
          ref={approveButtonRef}
          type="button"
          disabled={busy || texto.length > 0}
          onClick={(event) =>
            openConfirmation("approve", event.currentTarget)
          }
          className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white enabled:cursor-pointer hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
        >
          Aprobar
        </button>
        <button
          type="button"
          disabled={busy || texto.length < 5}
          onClick={(event) => openConfirmation("return", event.currentTarget)}
          className="min-h-11 rounded-md border border-ui-border bg-surface px-4 py-2 font-semibold text-foreground enabled:cursor-pointer hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60"
        >
          Devolver a corrección
        </button>
      </div>

      <ConfirmDialog
        open={confirmationAction !== null}
        title={
          confirmationAction === "approve"
            ? "Aprobar convenio"
            : "Devolver a corrección"
        }
        description={
          confirmationAction === "approve"
            ? "¿Confirmás que querés aprobar este convenio?"
            : "¿Confirmás que querés registrar las observaciones y devolver este convenio a corrección?"
        }
        confirmLabel={
          confirmationAction === "approve" ? "Aprobar" : "Devolver"
        }
        loading={busy}
        errorMessage={null}
        returnFocusRef={returnFocusRef}
        onConfirm={() => void confirmAction()}
        onCancel={() => setConfirmationAction(null)}
      />
    </aside>
  );
}
