"use client";

import {
  useRef,
  useState,
  type FormEvent,
  type KeyboardEvent,
  type ReactNode,
} from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import Button from "@/components/ui/Button";
import EmptyState from "@/components/feedback/EmptyState";
import ErrorState from "@/components/feedback/ErrorState";
import EstadoBadge from "@/components/convenios/EstadoBadge";
import { useAuth } from "@/context/AuthContext";
import {
  useConvenio,
  useConvenioDocuments,
  useConvenioObservations,
  useRespondObservation,
} from "@/hooks/useConvenios";
import {
  canEditConvenio,
  canReviewConvenio,
} from "@/lib/permissions";
import { ApiError, apiDownload } from "@/services/api-client";
import type { Convenio, HistorialEstado } from "@/types/convenio";
import type { DocumentoListItem } from "@/types/documento";
import type { Observacion } from "@/types/observacion";

const tabs = [
  { id: "general", label: "General" },
  { id: "partes", label: "Partes" },
  { id: "documentos", label: "Documentos" },
  { id: "historial", label: "Historial" },
  { id: "observaciones", label: "Observaciones" },
] as const;

type TabId = (typeof tabs)[number]["id"];

function isTabId(value: string | null): value is TabId {
  return tabs.some((tab) => tab.id === value);
}

const cardClassName =
  "rounded-lg border border-ui-border bg-surface p-4 text-foreground sm:p-5";
const headingClassName = "font-condensed text-xl font-bold tracking-tight";

function getErrorMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return "No se pudo completar la solicitud. Intentá nuevamente.";
  }
  if (error.status === 401) return "La sesión no está autorizada.";
  if (error.status === 403) {
    return "No tenés permisos para consultar este convenio.";
  }
  if (error.status === 404) return "No se encontró el convenio solicitado.";
  if (error.status === 0 || error.status >= 500) {
    return "No se pudo conectar con el servidor. Intentá nuevamente.";
  }
  return error.message;
}

function formatDate(
  value: string | null | undefined,
  emptyLabel = "—",
): string {
  if (!value) return emptyLabel;

  const date = new Date(`${value.slice(0, 10)}T00:00:00.000Z`);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat("es-AR", { timeZone: "UTC" }).format(date);
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat("es-AR", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

function calculateNoticeDate(
  vencimiento: string | null | undefined,
  diasPreaviso: number | null | undefined,
): string {
  if (!vencimiento || diasPreaviso === null || diasPreaviso === undefined) {
    return "—";
  }

  const date = new Date(`${vencimiento.slice(0, 10)}T00:00:00.000Z`);
  if (Number.isNaN(date.getTime())) return "—";
  date.setUTCDate(date.getUTCDate() - diasPreaviso);
  return new Intl.DateTimeFormat("es-AR", { timeZone: "UTC" }).format(date);
}

function formatFileSize(bytes: number | null | undefined): string {
  if (bytes === null || bytes === undefined) return "—";
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function getConvenioClass(convenio: Convenio): string {
  if (convenio.marco) return "Marco";
  if (convenio.especifico) return "Específico";
  return "—";
}

function DetailGrid({
  items,
}: {
  items: Array<{ label: string; value: ReactNode }>;
}) {
  return (
    <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
      {items.map(({ label, value }) => (
        <div key={label} className="min-w-0">
          <dt className="text-sm font-semibold text-foreground/70">{label}</dt>
          <dd className="mt-1 break-words">{value}</dd>
        </div>
      ))}
    </dl>
  );
}

function GeneralPanel({ convenio }: { convenio: Convenio }) {
  const instrumento = convenio.instrumento;
  const unidad = convenio.unidad_academica;
  const unidadNombre = unidad
    ? [unidad.sigla, unidad.nombre].filter(Boolean).join(" — ")
    : "—";
  const estado = instrumento?.estado;
  const diasPreaviso = instrumento?.dias_preaviso;

  return (
    <div className="space-y-5">
      <section className={cardClassName}>
        <h2 className={headingClassName}>Información general</h2>
        <div className="mt-4">
          <DetailGrid
            items={[
              { label: "Título", value: instrumento?.titulo || "—" },
              { label: "Clase", value: getConvenioClass(convenio) },
              { label: "Tipo", value: convenio.tipo || "—" },
              {
                label: "Estado",
                value: estado ? <EstadoBadge estado={estado} /> : "—",
              },
              { label: "Unidad académica", value: unidadNombre },
              { label: "Objeto", value: instrumento?.objeto || "—" },
            ]}
          />
        </div>
      </section>

      <section className={cardClassName}>
        <h2 className={headingClassName}>Ficha y plazos</h2>
        <div className="mt-4">
          <DetailGrid
            items={[
              {
                label: "Expediente",
                value: instrumento?.expediente || "—",
              },
              {
                label: "Resolución del Consejo Superior",
                value: instrumento?.resolucion || "—",
              },
              {
                label: "Fecha de firma",
                value: formatDate(instrumento?.fecha_firma, "Sin registrar"),
              },
              { label: "Inicio", value: formatDate(instrumento?.inicio) },
              {
                label: "Vencimiento",
                value: formatDate(
                  instrumento?.vencimiento,
                  "Sin vencimiento registrado",
                ),
              },
              {
                label: "Días de preaviso",
                value:
                  diasPreaviso === null || diasPreaviso === undefined
                    ? "—"
                    : `${diasPreaviso}`,
              },
              {
                label: "Fecha de preaviso calculada",
                value: calculateNoticeDate(
                  instrumento?.vencimiento,
                  diasPreaviso,
                ),
              },
              { label: "Alcance", value: convenio.alcance || "—" },
              {
                label: "Obligaciones",
                value: convenio.obligaciones || "—",
              },
              {
                label: "Marco legal",
                value: convenio.marco_legal || "—",
              },
              {
                label: "Condiciones de rescisión",
                value: convenio.condiciones_recision || "—",
              },
              {
                label: "Condición de renovación",
                value: convenio.condicion_renovacion || "—",
              },
              {
                label: "Confidencialidad",
                value:
                  convenio.confidencialidad === null
                    ? "—"
                    : convenio.confidencialidad
                      ? "Sí"
                      : "No",
              },
              { label: "Notas", value: instrumento?.notas || "—" },
            ]}
          />
        </div>
      </section>
    </div>
  );
}

function PartsPanel({ convenio }: { convenio: Convenio }) {
  const institutions = convenio.instituciones_firmantes ?? [];
  const marco = convenio.especifico?.marco;
  const marcoInstrumento = marco?.instrumento;

  return (
    <div className="space-y-5">
      <section className={cardClassName}>
        <h2 className={headingClassName}>Instituciones firmantes</h2>
        {institutions.length === 0 ? (
          <div className="mt-4">
            <EmptyState
              title="No hay instituciones firmantes registradas."
              description="La API no devolvió instituciones firmantes para este convenio."
            />
          </div>
        ) : (
          <ul className="mt-4 space-y-4">
            {institutions.map((institution) => (
              <li
                key={institution.id_institucion}
                className="rounded-md border border-ui-border p-4"
              >
                <h3 className="font-semibold">{institution.razon_social}</h3>
                <div className="mt-3">
                  <DetailGrid
                    items={[
                      { label: "CUIT", value: institution.cuit || "—" },
                      {
                        label: "Domicilio legal",
                        value: institution.domicilio_legal || "—",
                      },
                      {
                        label: "Tipo de institución",
                        value: institution.tipo_institucion || "—",
                      },
                    ]}
                  />
                </div>
                <h4 className="mt-4 text-sm font-semibold">
                  Representantes
                </h4>
                {institution.representantes?.length ? (
                  <ul className="mt-2 list-inside list-disc space-y-1">
                    {institution.representantes.map((representante) => {
                      const nombre = [
                        representante.persona?.nombre,
                        representante.persona?.apellido,
                      ]
                        .filter(Boolean)
                        .join(" ");

                      return (
                        <li key={representante.id_persona}>
                          {nombre || "—"}
                          {representante.cargo
                            ? ` — ${representante.cargo}`
                            : ""}
                        </li>
                      );
                    })}
                  </ul>
                ) : (
                  <p className="mt-2">—</p>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>

      {convenio.especifico ? (
        <section className={cardClassName}>
          <h2 className={headingClassName}>Convenio marco asociado</h2>
          {marco ? (
            <div className="mt-4">
              <DetailGrid
                items={[
                  {
                    label: "Título",
                    value: marcoInstrumento?.titulo || "—",
                  },
                  {
                    label: "Identificador",
                    value: marco.id_instrumento,
                  },
                  {
                    label: "Estado",
                    value: marcoInstrumento?.estado ? (
                      <EstadoBadge estado={marcoInstrumento.estado} />
                    ) : (
                      "—"
                    ),
                  },
                  {
                    label: "Inicio",
                    value: formatDate(marcoInstrumento?.inicio),
                  },
                  {
                    label: "Vencimiento",
                    value: formatDate(
                      marcoInstrumento?.vencimiento,
                      "Sin vencimiento registrado",
                    ),
                  },
                ]}
              />
              <Link
                href={`/convenios/${marco.id_instrumento}`}
                className="mt-4 inline-flex min-h-10 items-center rounded-sm px-2 font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
              >
                Ver convenio marco
              </Link>
            </div>
          ) : (
            <div className="mt-4">
              <EmptyState
                title="No hay un convenio marco asociado."
                description="La API no devolvió un convenio marco para este convenio específico."
              />
            </div>
          )}
        </section>
      ) : null}
    </div>
  );
}

function groupDocuments(documents: DocumentoListItem[]) {
  const groups = new Map<string, DocumentoListItem[]>();
  for (const item of documents) {
    const type = item.documento.tipo;
    const group = groups.get(type) ?? [];
    group.push(item);
    groups.set(type, group);
  }

  return [...groups.entries()].sort(([left], [right]) => {
    if (left === "Convenio") return right === "Convenio" ? 0 : -1;
    if (right === "Convenio") return 1;
    return left.localeCompare(right, "es");
  });
}

function DocumentsPanel({ id, active }: { id: number; active: boolean }) {
  const documentsQuery = useConvenioDocuments(id, active);
  const [downloadingId, setDownloadingId] = useState<number | null>(null);
  const [downloadError, setDownloadError] = useState<{
    id: number;
    message: string;
  } | null>(null);
  const documents = documentsQuery.data?.data ?? [];

  async function downloadDocument(documentId: number) {
    setDownloadingId(documentId);
    setDownloadError(null);
    try {
      const filename = documents.find(
        ({ documento }) => documento.id_documento === documentId,
      )?.ultima_version?.nombre_archivo;
      const fallbackFilename = filename
        ? filename.toLowerCase().endsWith(".pdf")
          ? filename
          : `${filename}.pdf`
        : undefined;
      await apiDownload(
        `/documentos/${documentId}/descargar`,
        fallbackFilename,
      );
    } catch (error) {
      setDownloadError({
        id: documentId,
        message: getErrorMessage(error),
      });
    } finally {
      setDownloadingId(null);
    }
  }

  if (active && documentsQuery.isPending) {
    return (
      <div className="space-y-3">
        <h2 className={headingClassName}>Documentos</h2>
        <p role="status" aria-live="polite">
          Cargando documentos...
        </p>
      </div>
    );
  }

  if (active && documentsQuery.isError) {
    return (
      <div className="space-y-3">
        <h2 className={headingClassName}>Documentos</h2>
        <ErrorState message={getErrorMessage(documentsQuery.error)} />
        <Button
          type="button"
          loading={documentsQuery.isFetching}
          className="!w-auto"
          onClick={() => void documentsQuery.refetch()}
        >
          Reintentar
        </Button>
      </div>
    );
  }

  if (!documents.length) {
    return (
      <EmptyState
        title="Documentos"
        description="No hay documentos registrados para este convenio."
      />
    );
  }

  return (
    <div className="space-y-5">
      <h2 className={headingClassName}>Documentos</h2>
      {groupDocuments(documents).map(([type, group]) => (
        <section key={type} className={cardClassName}>
          <h3 className="font-semibold">{type}</h3>
          <ul className="mt-4 divide-y divide-ui-border">
            {group.map(({ documento, ultima_version: version }) => (
              <li
                key={documento.id_documento}
                className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0">
                  <h4 className="break-words font-semibold">
                    {documento.titulo}
                  </h4>
                  <dl className="mt-2 grid gap-x-4 gap-y-1 text-sm text-foreground/70 sm:grid-cols-3">
                    <div>
                      <dt className="sr-only">Tipo</dt>
                      <dd>{documento.tipo}</dd>
                    </div>
                    <div>
                      <dt className="sr-only">Fecha de carga</dt>
                      <dd>{formatDateTime(version?.fecha_carga)}</dd>
                    </div>
                    <div className="break-all">
                      <dt className="sr-only">Nombre de archivo y tamaño</dt>
                      <dd>
                        {version?.nombre_archivo ?? "—"} ·{" "}
                        {formatFileSize(version?.tamanio_bytes)}
                      </dd>
                    </div>
                  </dl>
                  {downloadError?.id === documento.id_documento ? (
                    <p role="alert" className="mt-2 text-sm text-brand-text">
                      {downloadError.message}
                    </p>
                  ) : null}
                </div>
                <Button
                  type="button"
                  className="!w-auto shrink-0"
                  loading={downloadingId === documento.id_documento}
                  onClick={() =>
                    void downloadDocument(documento.id_documento)
                  }
                >
                  Descargar
                </Button>
              </li>
            ))}
          </ul>
        </section>
      ))}
    </div>
  );
}

function getHistoryTimestamp(item: HistorialEstado): number {
  if (!item.fecha_hora) return Number.NEGATIVE_INFINITY;
  const timestamp = new Date(item.fecha_hora).getTime();
  return Number.isNaN(timestamp) ? Number.NEGATIVE_INFINITY : timestamp;
}

function HistoryPanel({ convenio }: { convenio: Convenio }) {
  const history = [...(convenio.historial_estados ?? [])].sort((left, right) => {
    const difference =
      getHistoryTimestamp(right) - getHistoryTimestamp(left);
    return Number.isNaN(difference) ? 0 : difference;
  });

  if (!history.length) {
    return (
      <EmptyState
        title="Historial de estados"
        description="No hay movimientos registrados para este convenio."
      />
    );
  }

  return (
    <div className="space-y-4">
      <h2 className={headingClassName}>Historial de estados</h2>
      <ol className="space-y-4">
        {history.map((item) => (
          <li key={item.id_historial} className={cardClassName}>
            <div className="flex flex-wrap items-center gap-2">
              {item.estado_anterior ? (
                <EstadoBadge estado={item.estado_anterior} />
              ) : (
                <span>—</span>
              )}
              <span aria-hidden="true">→</span>
              <EstadoBadge estado={item.estado_nuevo} />
            </div>
            <p className="mt-2 text-sm text-foreground/70">
              {formatDateTime(item.fecha_hora)}
            </p>
            {item.usuario?.nombre ? (
              <p className="mt-1">Usuario: {item.usuario.nombre}</p>
            ) : null}
            {item.motivo?.trim() ? (
              <p className="mt-1">Motivo: {item.motivo}</p>
            ) : null}
          </li>
        ))}
      </ol>
    </div>
  );
}

function ObservationReply({
  observation,
  isSaving,
  busy,
  onSubmit,
}: {
  observation: Observacion;
  isSaving: boolean;
  busy: boolean;
  onSubmit: (id: number, respuesta: string) => Promise<void>;
}) {
  const [respuesta, setRespuesta] = useState("");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const fieldId = `observacion-respuesta-${observation.id_observacion}`;
  const helpId = `${fieldId}-help`;
  const errorId = `${fieldId}-error`;

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (respuesta.length < 5 || busy) return;

    setErrorMessage(null);
    setFieldError(null);
    try {
      await onSubmit(observation.id_observacion, respuesta);
      setRespuesta("");
    } catch (error) {
      if (error instanceof ApiError) {
        setErrorMessage(error.message);
        setFieldError(error.errors?.respuesta?.[0] ?? null);
      } else {
        setErrorMessage("No se pudo enviar la respuesta. Intentá nuevamente.");
      }
    }
  }

  return (
    <form onSubmit={handleSubmit} className="mt-4 space-y-3">
      <div>
        <label htmlFor={fieldId} className="font-semibold">
          Respuesta
        </label>
        <textarea
          id={fieldId}
          value={respuesta}
          onChange={(event) => {
            setRespuesta(event.target.value);
            setErrorMessage(null);
            setFieldError(null);
          }}
          aria-describedby={`${helpId}${fieldError ? ` ${errorId}` : ""}`}
          aria-invalid={fieldError ? true : undefined}
          rows={3}
          className="mt-1 block w-full rounded-md border border-ui-border bg-surface px-3 py-2 text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
        />
        <p id={helpId} className="mt-1 text-sm text-foreground/70">
          Escribí al menos 5 caracteres.
        </p>
        {fieldError ? (
          <p id={errorId} className="mt-1 text-sm text-brand-text">
            {fieldError}
          </p>
        ) : null}
      </div>
      {errorMessage ? (
        <p role="alert" className="text-sm text-brand-text">
          {errorMessage}
        </p>
      ) : null}
      <Button
        type="submit"
        className="!w-auto"
        disabled={busy || respuesta.length < 5}
        loading={isSaving}
      >
        Responder
      </Button>
    </form>
  );
}

function ObservationsPanel({
  observations,
  canRespond,
  busyObservationId,
  isSaving,
  onSubmit,
  error,
  success,
}: {
  observations: Observacion[];
  canRespond: boolean;
  busyObservationId: number | null;
  isSaving: boolean;
  onSubmit: (id: number, respuesta: string) => Promise<void>;
  error: string | null;
  success: string | null;
}) {
  const sortedObservations = [...observations].sort((left, right) => {
    const difference =
      new Date(right.fecha).getTime() - new Date(left.fecha).getTime();
    return Number.isNaN(difference) || difference === 0
      ? right.id_observacion - left.id_observacion
      : difference;
  });

  return (
    <div className="space-y-4">
      <h2 className={headingClassName}>Observaciones</h2>
      {success ? (
        <p role="status" aria-live="polite" className={cardClassName}>
          {success}
        </p>
      ) : null}
      {error ? (
        <p role="alert" className={cardClassName}>
          {error}
        </p>
      ) : null}
      <ul className="space-y-4">
        {sortedObservations.map((observation) => {
          const pending = observation.estado_tramite === "Pendiente";
          return (
            <li
              key={observation.id_observacion}
              className={cardClassName}
            >
              <p className="whitespace-pre-wrap">{observation.contenido}</p>
              <dl className="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                <div>
                  <dt className="font-semibold">Fecha y hora</dt>
                  <dd>{formatDateTime(observation.fecha)}</dd>
                </div>
                <div>
                  <dt className="font-semibold">Autor</dt>
                  <dd>{observation.usuario.nombre}</dd>
                </div>
                <div>
                  <dt className="font-semibold">Estado del trámite</dt>
                  <dd>{observation.estado_tramite}</dd>
                </div>
              </dl>
              {observation.respuesta ? (
                <div className="mt-4 rounded-md border border-ui-border p-3">
                  <h3 className="font-semibold">Respuesta</h3>
                  <p className="mt-1 whitespace-pre-wrap">
                    {observation.respuesta}
                  </p>
                  <p className="mt-2 text-sm text-foreground/70">
                    {formatDateTime(observation.fecha_resolucion)}
                  </p>
                </div>
              ) : null}
              {canRespond && pending ? (
                <ObservationReply
                  observation={observation}
                  isSaving={busyObservationId === observation.id_observacion}
                  busy={isSaving}
                  onSubmit={onSubmit}
                />
              ) : null}
            </li>
          );
        })}
      </ul>
    </div>
  );
}

export default function DetalleConvenio({
  id,
  reviewMode = false,
  asideContent,
}: {
  id: string;
  reviewMode?: boolean;
  asideContent?: ReactNode;
}) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { status: authStatus, user } = useAuth();
  const [activeTab, setActiveTab] = useState<TabId>(() => {
    const requestedTab = searchParams.get("tab");
    return isTabId(requestedTab) ? requestedTab : "general";
  });
  const [observationSuccess, setObservationSuccess] = useState<string | null>(
    null,
  );
  const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);
  const parsedId = /^\d+$/.test(id) ? Number(id) : 0;
  const validId = Number.isSafeInteger(parsedId) && parsedId > 0;
  const convenioQuery = useConvenio(
    parsedId,
    validId && authStatus === "authenticated",
  );
  const role = user?.rol?.nombre ?? null;
  const observationsQuery = useConvenioObservations(
    parsedId,
    validId &&
      authStatus === "authenticated" &&
      Boolean(convenioQuery.data) &&
      (role === "Gestión" || role === "Administración" || role === "Revisión"),
  );
  const respondObservationMutation = useRespondObservation(parsedId);
  const observations = observationsQuery.data?.data ?? [];
  const pendingObservationCount = observations.filter(
    (observation) => observation.estado_tramite === "Pendiente",
  ).length;
  const observationError =
    observationsQuery.error instanceof ApiError &&
    observationsQuery.error.status !== 403
      ? observationsQuery.error.message
      : null;
  const observationsTabVisible =
    observations.length > 0 || observationError !== null;
  const visibleTabs = observationsTabVisible
    ? tabs
    : tabs.filter((tab) => tab.id !== "observaciones");
  const selectedTab = visibleTabs.some((tab) => tab.id === activeTab)
    ? activeTab
    : "general";
  const canRespond =
    canEditConvenio(role, convenioQuery.data?.instrumento?.estado ?? "") &&
    convenioQuery.data?.instrumento?.estado === "EnCorreccion";

  async function submitObservationResponse(
    idObservacion: number,
    respuesta: string,
  ) {
    await respondObservationMutation.mutateAsync({
      idObservacion,
      input: { respuesta },
    });
    setObservationSuccess("La respuesta se envió correctamente.");
  }

  function handleTabKeyDown(
    event: KeyboardEvent<HTMLButtonElement>,
    currentIndex: number,
  ) {
    let nextIndex: number | null = null;
    if (event.key === "ArrowRight") {
      nextIndex = (currentIndex + 1) % visibleTabs.length;
    } else if (event.key === "ArrowLeft") {
      nextIndex = (currentIndex - 1 + visibleTabs.length) % visibleTabs.length;
    } else if (event.key === "Home") {
      nextIndex = 0;
    } else if (event.key === "End") {
      nextIndex = visibleTabs.length - 1;
    }

    if (nextIndex === null) return;
    event.preventDefault();
    setActiveTab(visibleTabs[nextIndex].id);
    tabRefs.current[nextIndex]?.focus();
  }

  if (authStatus === "loading") {
    return (
      <p role="status" aria-live="polite">
        Verificando la sesión...
      </p>
    );
  }

  if (authStatus !== "authenticated" || !user) {
    return (
      <section className="space-y-3">
        <ErrorState message="Iniciá sesión para consultar este convenio." />
        <Button
          type="button"
          className="!w-auto"
          onClick={() => router.push("/login")}
        >
          Iniciar sesión
        </Button>
      </section>
    );
  }

  if (!validId) {
    return (
      <section className="space-y-3">
        <ErrorState message="No se encontró el convenio solicitado." />
        <Button
          type="button"
          className="!w-auto"
          onClick={() => router.push("/convenios")}
        >
          Volver a convenios
        </Button>
      </section>
    );
  }

  if (convenioQuery.isPending) {
    return (
      <p role="status" aria-live="polite">
        Cargando convenio...
      </p>
    );
  }

  if (convenioQuery.isError) {
    const status =
      convenioQuery.error instanceof ApiError
        ? convenioQuery.error.status
        : null;
    const canRetry = status !== 403 && status !== 404;

    return (
      <section className="space-y-3">
        <ErrorState message={getErrorMessage(convenioQuery.error)} />
        <div className="flex flex-wrap gap-3">
          {canRetry ? (
            <Button
              type="button"
              loading={convenioQuery.isFetching}
              className="!w-auto"
              onClick={() => void convenioQuery.refetch()}
            >
              Reintentar
            </Button>
          ) : null}
          <Button
            type="button"
            variant="link"
            onClick={() => router.push("/convenios")}
          >
            Volver a convenios
          </Button>
        </div>
      </section>
    );
  }

  const convenio = convenioQuery.data;
  if (!convenio) {
    return (
      <section className="space-y-3">
        <ErrorState message="No se encontró el convenio solicitado." />
        <Button
          type="button"
          className="!w-auto"
          onClick={() => router.push("/convenios")}
        >
          Volver a convenios
        </Button>
      </section>
    );
  }

  const estado = convenio.instrumento?.estado;
  const rol = user.rol?.nombre ?? null;
  const puedeRevisar = canReviewConvenio(rol, estado ?? "");

  return (
    <section
      aria-labelledby={`convenio-title-${parsedId}`}
      className="space-y-5"
    >
      <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div className="min-w-0">
          <div className="w-fit">
            {reviewMode ? (
              <h2
                id={`convenio-title-${parsedId}`}
                className="font-condensed text-2xl font-bold tracking-tight sm:text-3xl"
              >
                {convenio.instrumento?.titulo || "—"}
              </h2>
            ) : (
              <h1
                id={`convenio-title-${parsedId}`}
                className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
              >
                {convenio.instrumento?.titulo || "—"}
              </h1>
            )}
            <div
              aria-hidden="true"
              className="mt-2 h-1 w-full rounded-full bg-brand"
            />
          </div>
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <p>
              Instrumento N.º {parsedId} · {getConvenioClass(convenio)}
            </p>
            {estado ? <EstadoBadge estado={estado} /> : null}
          </div>
        </div>
        {!reviewMode ? (
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              variant="link"
              onClick={() => router.push("/convenios")}
              className="!h-11 !w-auto !rounded-md !border !border-ui-border !bg-surface !px-4 !py-2 !text-sm !font-semibold !text-foreground !no-underline enabled:cursor-pointer hover:!bg-row-alternate"
            >
              Volver
            </Button>
            {puedeRevisar ? (
              <Button
                type="button"
                className="!w-auto"
                onClick={() => router.push(`/convenios/${parsedId}/revisar`)}
              >
                Revisar
              </Button>
            ) : null}
            {canEditConvenio(rol, estado ?? "") ? (
              <Button
                type="button"
                className="!w-auto enabled:cursor-pointer"
                onClick={() => router.push(`/convenios/${parsedId}/edit`)}
              >
                Editar
              </Button>
            ) : null}
          </div>
        ) : null}
      </header>

      <div
        role="tablist"
        aria-label="Secciones del convenio"
        aria-orientation="horizontal"
        className="max-w-full overflow-x-auto border-b border-ui-border"
      >
        <div className="flex w-max min-w-full">
          {visibleTabs.map((tab, index) => {
            const selected = selectedTab === tab.id;
            const tabId = `convenio-tab-${parsedId}-${tab.id}`;
            const panelId = `convenio-panel-${parsedId}-${tab.id}`;
            return (
              <button
                key={tab.id}
                ref={(element) => {
                  tabRefs.current[index] = element;
                }}
                id={tabId}
                type="button"
                role="tab"
                aria-selected={selected}
                aria-controls={panelId}
                tabIndex={selected ? 0 : -1}
                onClick={() => setActiveTab(tab.id)}
                onKeyDown={(event) => handleTabKeyDown(event, index)}
                className={`min-h-11 shrink-0 cursor-pointer border-b-2 px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand ${
                  selected
                    ? "border-brand text-brand-text"
                    : "border-transparent text-foreground/70 hover:bg-row-alternate hover:text-foreground"
                }`}
              >
                {tab.id === "observaciones" ? (
                  <span
                    aria-label={`Observaciones, ${pendingObservationCount} pendientes`}
                  >
                    Observaciones{" "}
                    <span aria-hidden="true">
                      ({pendingObservationCount})
                    </span>
                  </span>
                ) : (
                  tab.label
                )}
              </button>
            );
          })}
        </div>
      </div>

      <div
        className={
          asideContent
            ? "grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]"
            : ""
        }
      >
        <div className="min-w-0">
          {visibleTabs.map((tab) => {
            const selected = selectedTab === tab.id;
            const tabId = `convenio-tab-${parsedId}-${tab.id}`;
            const panelId = `convenio-panel-${parsedId}-${tab.id}`;
            return (
              <section
                key={tab.id}
                id={panelId}
                role="tabpanel"
                aria-labelledby={tabId}
                tabIndex={0}
                hidden={!selected}
                className="min-w-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
              >
                {tab.id === "general" ? (
                  <GeneralPanel convenio={convenio} />
                ) : tab.id === "partes" ? (
                  <PartsPanel convenio={convenio} />
                ) : tab.id === "documentos" ? (
                  <DocumentsPanel id={parsedId} active={selected} />
                ) : tab.id === "observaciones" ? (
                  <ObservationsPanel
                    observations={observations}
                    canRespond={canRespond}
                    busyObservationId={
                      respondObservationMutation.variables?.idObservacion ??
                      null
                    }
                    isSaving={respondObservationMutation.isPending}
                    onSubmit={submitObservationResponse}
                    error={observationError}
                    success={observationSuccess}
                  />
                ) : (
                  <HistoryPanel convenio={convenio} />
                )}
              </section>
            );
          })}
        </div>

        {asideContent ? (
          puedeRevisar ? (
            <div className="lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:self-start lg:overflow-y-auto">
              {asideContent}
            </div>
          ) : (
            <aside className={cardClassName}>
              <h2 className={headingClassName}>
                Panel de revisión no disponible
              </h2>
              <p role="status">
                Solo un usuario con rol Revisión puede revisar convenios que
                estén en estado Revisión.
              </p>
              <Link
                href={`/convenios/${parsedId}`}
                className="inline-flex min-h-11 items-center justify-center rounded-md border border-ui-border bg-surface px-4 py-2 font-semibold text-foreground hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
              >
                Ir al detalle
              </Link>
            </aside>
          )
        ) : null}
      </div>
    </section>
  );
}
