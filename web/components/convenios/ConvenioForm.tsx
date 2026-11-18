"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter } from "next/navigation";
import {
  useForm,
  type FieldPath,
  type UseFormReturn,
} from "react-hook-form";
import ConfirmDialog from "@/components/feedback/ConfirmDialog";
import ErrorState from "@/components/feedback/ErrorState";
import StepDatosBasicos from "@/components/convenios/form/StepDatosBasicos";
import StepDocumentos from "@/components/convenios/form/StepDocumentos";
import StepFichaPlazos from "@/components/convenios/form/StepFichaPlazos";
import Stepper from "@/components/convenios/form/Stepper";
import { convenioFormSchema, type ConvenioFormValues } from "@/components/convenios/form/schema";
import { useAuth } from "@/context/AuthContext";
import { canManageDraftConvenio } from "@/lib/permissions";
import { ApiError } from "@/services/api-client";
import * as convenios from "@/services/convenios";
import * as institucionesFirmantes from "@/services/instituciones-firmantes";
import * as unidadesAcademicas from "@/services/unidades";
import type { Convenio } from "@/types/convenio";
import type { DocumentoListResponse } from "@/types/documento";
import type { InstitucionFirmante, UnidadAcademica } from "@/types/unidad";
import Button from "@/components/ui/Button";

type StepNumber = 1 | 2 | 3;
type Notice = { kind: "success" | "error" | "info"; message: string };
type Operation = "validating" | "saving" | "sending" | null;
type FormOptions = {
  unidades: UnidadAcademica[];
  instituciones: InstitucionFirmante[];
  conveniosMarco: Awaited<ReturnType<typeof convenios.listMarcos>>;
};
type UploadCandidate = {
  clientId: string;
  tipo: string;
  titulo: string;
  archivo: File | null;
  documentoId: number | null;
  fileField: FieldPath<ConvenioFormValues>;
  titleField: FieldPath<ConvenioFormValues>;
};
type UploadedDocument = {
  id: number;
};
type EditLoadError = {
  message: string;
  status?: number;
};

class DocumentSyncError extends Error {
  constructor(
    documentTitle: string,
    readonly originalError: unknown,
  ) {
    super(
      `No se pudo cargar el documento "${documentTitle}": ${getApiMessage(originalError)}`,
    );
    this.name = "DocumentSyncError";
  }
}

const stepFields: Record<StepNumber, FieldPath<ConvenioFormValues>[]> = {
  1: [
    "claseInstrumento",
    "tipo",
    "titulo",
    "idUnidadAcademica",
    "idConvenioMarco",
    "institucionesFirmantes",
  ],
  2: [
    "expediente",
    "resolucionConsejoSuperior",
    "objeto",
    "alcance",
    "obligaciones",
    "marcoLegal",
    "condicionesRescision",
    "condicionRenovacion",
    "confidencialidad",
    "fechaInicio",
    "fechaVencimiento",
    "diasPreaviso",
    "notas",
  ],
  3: ["tituloDocumentoPrincipal", "archivoPrincipal", "anexos"],
};

const draftFields: FieldPath<ConvenioFormValues>[] = [
  ...stepFields[1],
  ...stepFields[2],
  ...stepFields[3].filter(
    (field) =>
      field !== "tituloDocumentoPrincipal" && field !== "archivoPrincipal",
  ),
];

const apiFieldMap: Record<string, FieldPath<ConvenioFormValues>> = {
  titulo: "titulo",
  objeto: "objeto",
  id_unidad_academica: "idUnidadAcademica",
  tipo: "tipo",
  es_marco: "claseInstrumento",
  id_convenio_marco: "idConvenioMarco",
  instituciones_ids: "institucionesFirmantes",
  dias_preaviso: "diasPreaviso",
  expediente: "expediente",
  resolucion: "resolucionConsejoSuperior",
  inicio: "fechaInicio",
  vencimiento: "fechaVencimiento",
  notas: "notas",
  alcance: "alcance",
  obligaciones: "obligaciones",
  condiciones_recision: "condicionesRescision",
  marco_legal: "marcoLegal",
  condicion_renovacion: "condicionRenovacion",
  confidencialidad: "confidencialidad",
};

const defaultValues: ConvenioFormValues = {
  editMode: false,
  claseInstrumento: "",
  tipo: "",
  titulo: "",
  idUnidadAcademica: "",
  idConvenioMarco: "",
  institucionesFirmantes: [],
  archivoPrincipal: null,
  documentoPrincipalId: null,
  nombreArchivoPrincipal: null,
  expediente: "",
  resolucionConsejoSuperior: "",
  objeto: "",
  alcance: "",
  obligaciones: "",
  marcoLegal: "",
  condicionesRescision: "",
  condicionRenovacion: "",
  confidencialidad: false,
  fechaInicio: "",
  fechaVencimiento: "",
  diasPreaviso: 30,
  notas: "",
  tituloDocumentoPrincipal: "",
  anexos: [],
};

function dateInputValue(value: string | null | undefined): string {
  return value ? value.slice(0, 10) : "";
}

function mapEditValues(
  convenio: Convenio,
  documents: DocumentoListResponse,
): {
  values: ConvenioFormValues;
  uploadedDocuments: Map<string, UploadedDocument>;
} {
  const principal = documents.data.find(
    ({ documento }) => documento.tipo === "Convenio",
  );
  const annexes = documents.data.filter(
    ({ documento }) => documento.tipo !== "Convenio",
  );
  const uploadedDocuments = new Map<string, UploadedDocument>();

  if (principal) {
    uploadedDocuments.set("principal", {
      id: principal.documento.id_documento,
    });
  }

  const formAnnexes = annexes.map(({ documento, ultima_version }) => {
    const clientId = crypto.randomUUID();
    uploadedDocuments.set(clientId, { id: documento.id_documento });
    return {
      clientId,
      documentoId: documento.id_documento,
      nombreArchivo: ultima_version?.nombre_archivo ?? null,
      tipo: documento.tipo,
      titulo: documento.titulo,
      archivo: null,
    };
  });
  const instrumento = convenio.instrumento;
  const tipo =
    convenio.tipo === "Colaboración" || convenio.tipo === "Pasantía"
      ? convenio.tipo
      : "";

  return {
    uploadedDocuments,
    values: {
      ...defaultValues,
      editMode: true,
      claseInstrumento:
        convenio.marco != null
          ? "Marco"
          : convenio.especifico != null
            ? "Específico"
            : "",
      tipo,
      titulo: instrumento?.titulo ?? "",
      idUnidadAcademica: String(convenio.id_unidad_academica ?? ""),
      idConvenioMarco:
        convenio.especifico != null
          ? String(convenio.especifico.id_convenio_marco)
          : "",
      institucionesFirmantes:
        convenio.instituciones_firmantes?.map(({ pivot }) =>
          String(pivot.id_institucion),
        ) ?? [],
      expediente: instrumento?.expediente ?? "",
      resolucionConsejoSuperior: instrumento?.resolucion ?? "",
      objeto: instrumento?.objeto ?? "",
      alcance: convenio.alcance ?? "",
      obligaciones: convenio.obligaciones ?? "",
      marcoLegal: convenio.marco_legal ?? "",
      condicionesRescision: convenio.condiciones_recision ?? "",
      condicionRenovacion: convenio.condicion_renovacion ?? "",
      confidencialidad: convenio.confidencialidad ?? false,
      fechaInicio: dateInputValue(instrumento?.inicio),
      fechaVencimiento: dateInputValue(instrumento?.vencimiento),
      diasPreaviso: instrumento?.dias_preaviso ?? "",
      notas: instrumento?.notas ?? "",
      tituloDocumentoPrincipal: principal?.documento.titulo ?? "",
      documentoPrincipalId: principal?.documento.id_documento ?? null,
      nombreArchivoPrincipal:
        principal?.ultima_version?.nombre_archivo ?? null,
      anexos: formAnnexes,
    },
  };
}

function isManagementRole(role: string | undefined): boolean {
  return role === "Gestión" || role === "Administración";
}

function getApiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return "No se pudo completar la operación. Revisá tu conexión e intentá nuevamente.";
  }
  if (error.status === 401) {
    return "La sesión expiró. Iniciá sesión nuevamente para continuar.";
  }
  if (error.status === 403) {
    return error.message.startsWith("Error de API (")
      ? "No tenés permisos para realizar esta operación."
      : error.message;
  }
  if (
    error.status >= 500 ||
    error.message.startsWith("Error de API (")
  ) {
    return "El servidor no pudo completar la operación. Intentá nuevamente.";
  }
  return error.message;
}

function stepForField(field: FieldPath<ConvenioFormValues>): StepNumber {
  if (stepFields[1].includes(field)) return 1;
  if (stepFields[2].includes(field)) return 2;
  return 3;
}

function getFirstStepWithErrors(
  form: UseFormReturn<ConvenioFormValues>,
): StepNumber | null {
  for (const step of [1, 2, 3] as const) {
    if (stepFields[step].some((field) => form.getFieldState(field).error)) {
      return step;
    }
  }
  return null;
}

function applyApiFieldErrors(
  error: unknown,
  setError: ReturnType<typeof useForm<ConvenioFormValues>>["setError"],
  fallback?: {
    fileField?: FieldPath<ConvenioFormValues>;
    titleField?: FieldPath<ConvenioFormValues>;
  },
): StepNumber | null {
  if (!(error instanceof ApiError) || !error.errors) return null;

  let firstStep: StepNumber | null = null;
  for (const [apiField, messages] of Object.entries(error.errors)) {
    const rootField = apiField.split(".")[0];
    const field =
      rootField === "archivo"
        ? fallback?.fileField
        : rootField === "titulo" && fallback?.titleField
          ? fallback.titleField
          : apiFieldMap[rootField];
    const message = messages[0];
    if (!field || !message) continue;

    setError(field, { type: "server", message });
    const step = stepForField(field);
    if (firstStep === null || step < firstStep) firstStep = step;
  }
  return firstStep;
}

function toCreateInput(values: ConvenioFormValues): Parameters<
  typeof convenios.create
>[0] {
  const isMarco = values.claseInstrumento === "Marco";
  return {
    titulo: values.titulo.trim(),
    objeto: values.objeto.trim() || null,
    id_unidad_academica: Number(values.idUnidadAcademica),
    tipo: values.tipo,
    es_marco: isMarco,
    ...(isMarco ? {} : { id_convenio_marco: Number(values.idConvenioMarco) }),
    instituciones_ids: values.institucionesFirmantes.map(Number),
    dias_preaviso:
      typeof values.diasPreaviso === "number"
        ? values.diasPreaviso
        : 30,
  };
}

function toUpdateInput(values: ConvenioFormValues): Parameters<
  typeof convenios.update
>[1] {
  const isMarco = values.claseInstrumento === "Marco";
  return {
    titulo: values.titulo.trim(),
    objeto: values.objeto.trim() || null,
    expediente: values.expediente.trim() || null,
    resolucion: values.resolucionConsejoSuperior.trim() || null,
    inicio: values.fechaInicio || null,
    vencimiento: values.fechaVencimiento || null,
    dias_preaviso:
      typeof values.diasPreaviso === "number" ? values.diasPreaviso : null,
    notas: values.notas.trim() || null,
    tipo: values.tipo,
    alcance: values.alcance.trim() || null,
    obligaciones: values.obligaciones.trim() || null,
    condiciones_recision: values.condicionesRescision.trim() || null,
    marco_legal: values.marcoLegal.trim() || null,
    condicion_renovacion: values.condicionRenovacion.trim() || null,
    confidencialidad: values.confidencialidad,
    id_unidad_academica: Number(values.idUnidadAcademica),
    ...(isMarco ? {} : { id_convenio_marco: Number(values.idConvenioMarco) }),
    instituciones_ids: values.institucionesFirmantes.map(Number),
  };
}

async function fileHash(file: File): Promise<string> {
  const digest = await crypto.subtle.digest("SHA-256", await file.arrayBuffer());
  return Array.from(new Uint8Array(digest), (byte) =>
    byte.toString(16).padStart(2, "0"),
  ).join("");
}

function getDocumentCandidates(values: ConvenioFormValues): UploadCandidate[] {
  const candidates: UploadCandidate[] = [];
  if (values.archivoPrincipal || values.documentoPrincipalId !== null) {
    candidates.push({
      clientId: "principal",
      tipo: "Convenio",
      titulo: values.tituloDocumentoPrincipal.trim(),
      archivo: values.archivoPrincipal,
      documentoId: values.documentoPrincipalId,
      fileField: "archivoPrincipal",
      titleField: "tituloDocumentoPrincipal",
    });
  }

  values.anexos.forEach((anexo, index) => {
    if (!anexo.archivo && anexo.documentoId === null) return;
    candidates.push({
      clientId: anexo.clientId,
      tipo: anexo.tipo,
      titulo: anexo.titulo.trim(),
      archivo: anexo.archivo,
      documentoId: anexo.documentoId,
      fileField: `anexos.${index}.archivo`,
      titleField: `anexos.${index}.titulo`,
    });
  });
  return candidates;
}

async function waitForSuccessNotice(): Promise<void> {
  await new Promise<void>((resolve) => window.setTimeout(resolve, 900));
}

export default function ConvenioForm({
  convenioId,
}: {
  convenioId?: number;
}) {
  const router = useRouter();
  const { status: authStatus, user } = useAuth();
  const sendButtonRef = useRef<HTMLButtonElement>(null);
  const previousStep = useRef<StepNumber>(1);
  const processingRef = useRef(false);
  const creationUncertainRef = useRef(false);
  const instrumentIdRef = useRef<number | null>(convenioId ?? null);
  const uploadedDocumentsRef = useRef(new Map<string, UploadedDocument>());
  const [instrumentId, setInstrumentId] = useState<number | null>(
    convenioId ?? null,
  );
  const [currentStep, setCurrentStep] = useState<StepNumber>(1);
  const [formResetKey, setFormResetKey] = useState(0);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [dialogError, setDialogError] = useState<string | null>(null);
  const [confirmationOpen, setConfirmationOpen] = useState(false);
  const [operation, setOperation] = useState<Operation>(null);
  const [loadingOptions, setLoadingOptions] = useState(true);
  const [optionsInitialized, setOptionsInitialized] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [loadingExisting, setLoadingExisting] = useState(
    convenioId !== undefined,
  );
  const [editLoaded, setEditLoaded] = useState(convenioId === undefined);
  const [editDenied, setEditDenied] = useState(false);
  const [editLoadError, setEditLoadError] = useState<EditLoadError | null>(
    null,
  );
  const [options, setOptions] = useState<FormOptions | null>(null);
  const form = useForm<ConvenioFormValues>({
    resolver: zodResolver(convenioFormSchema),
    mode: "onTouched",
    defaultValues,
    shouldUnregister: false,
  });
  const {
    clearErrors,
    formState: { errors },
    getValues,
    reset,
    setError,
    trigger,
  } = form;
  const role = user?.rol?.nombre;
  const canManage = isManagementRole(role);
  const isEditMode = convenioId !== undefined;
  const busy = operation !== null;

  const fetchOptions = useCallback(async (): Promise<FormOptions> => {
    const [unidades, instituciones, conveniosMarco] = await Promise.all([
      unidadesAcademicas.list(),
      institucionesFirmantes.listAll(),
      convenios.listMarcos(),
    ]);
    return { unidades, instituciones, conveniosMarco };
  }, []);

  const initializeOptions = useCallback(
    (loadedOptions: FormOptions) => {
      if (!user) return;
      setOptions(loadedOptions);

      const isGestion = user.rol?.nombre === "Gestión";
      const unitId = isGestion ? String(user.id_unidad) : "";
      const assignedUnit = loadedOptions.unidades.find(
        (unit) => unit.id_unidad_academica === user.id_unidad,
      );
      if (isGestion && !assignedUnit) {
        setLoadError(
          "La unidad académica del usuario no está disponible en la API.",
        );
        setOptionsInitialized(true);
        return;
      }

      const institutionId = assignedUnit?.id_institucion;
      if (
        isGestion &&
        institutionId !== undefined &&
        !loadedOptions.instituciones.some(
          (institution) => institution.id_institucion === institutionId,
        )
      ) {
        setLoadError(
          "La institución firmante asociada a la unidad no está disponible en la API.",
        );
        setOptionsInitialized(true);
        return;
      }

      if (!isEditMode) {
        reset({
          ...defaultValues,
          idUnidadAcademica: unitId,
          institucionesFirmantes:
            isGestion && institutionId !== undefined
              ? [String(institutionId)]
              : [],
        });
      }
      setLoadError(null);
      setOptionsInitialized(true);
    },
    [isEditMode, reset, user],
  );

  const loadOptions = useCallback(async () => {
    setLoadingOptions(true);
    setLoadError(null);
    try {
      initializeOptions(await fetchOptions());
    } catch (error) {
      setLoadError(getApiMessage(error));
    } finally {
      setLoadingOptions(false);
    }
  }, [fetchOptions, initializeOptions]);

  const loadEditData = useCallback(async () => {
    if (
      convenioId === undefined ||
      !Number.isSafeInteger(convenioId) ||
      convenioId <= 0
    ) {
      return;
    }

    try {
      const convenio = await convenios.get(convenioId);
      if (
        !canManageDraftConvenio(
          role ?? null,
          convenio.instrumento?.estado ?? "",
        )
      ) {
        setEditDenied(true);
        return;
      }

      const documents = await convenios.listDocuments(convenioId);
      const mapped = mapEditValues(convenio, documents);
      reset(mapped.values);
      uploadedDocumentsRef.current = mapped.uploadedDocuments;
      instrumentIdRef.current = convenioId;
      setInstrumentId(convenioId);
      setEditLoaded(true);
    } catch (error) {
      setEditLoadError({
        message:
          error instanceof ApiError && error.status === 403
            ? "No tenés permisos para consultar o editar este convenio."
            : error instanceof ApiError && error.status === 404
              ? "No se encontró el convenio solicitado."
              : getApiMessage(error),
        status: error instanceof ApiError ? error.status : undefined,
      });
    } finally {
      setLoadingExisting(false);
    }
  }, [convenioId, reset, role]);

  useEffect(() => {
    if (
      isEditMode &&
      authStatus === "authenticated" &&
      user &&
      canManage &&
      Number.isSafeInteger(convenioId) &&
      convenioId > 0 &&
      optionsInitialized &&
      !loadingOptions &&
      options
    ) {
      void Promise.resolve().then(loadEditData);
    }
  }, [
    authStatus,
    canManage,
    convenioId,
    isEditMode,
    loadEditData,
    loadingOptions,
    options,
    optionsInitialized,
    user,
  ]);

  useEffect(() => {
    let active = true;
    if (user && canManage) {
      void fetchOptions()
        .then((loadedOptions) => {
          if (active) initializeOptions(loadedOptions);
        })
        .catch((error: unknown) => {
          if (active) setLoadError(getApiMessage(error));
        })
        .finally(() => {
          if (active) setLoadingOptions(false);
        });
    }
    return () => {
      active = false;
    };
  }, [canManage, fetchOptions, initializeOptions, user]);

  useEffect(() => {
    if (previousStep.current !== currentStep) {
      document.getElementById(`form-step-${currentStep}-title`)?.focus();
      previousStep.current = currentStep;
    }
  }, [currentStep]);

  function setStep(step: number) {
    if (
      !processingRef.current &&
      step >= 1 &&
      step <= 3 &&
      (step === 1 || step === 2 || step === 3)
    ) {
      setNotice(null);
      setCurrentStep(step);
    }
  }

  function resetNewConvenio() {
    reset(defaultValues);
    instrumentIdRef.current = null;
    creationUncertainRef.current = false;
    uploadedDocumentsRef.current.clear();
    setInstrumentId(null);
    setCurrentStep(1);
    setFormResetKey((key) => key + 1);
  }

  async function goToNextStep() {
    if (processingRef.current) return;
    processingRef.current = true;
    setOperation("validating");
    setNotice(null);
    try {
      if (!(await trigger(stepFields[currentStep]))) return;

      const nextStep = (currentStep + 1) as StepNumber;
      setCurrentStep(nextStep);
    } catch (error) {
      setNotice({ kind: "error", message: getApiMessage(error) });
    } finally {
      processingRef.current = false;
      setOperation(null);
    }
  }

  async function ensureInstrument(values: ConvenioFormValues): Promise<number> {
    if (convenioId !== undefined) return convenioId;
    if (instrumentIdRef.current !== null) return instrumentIdRef.current;
    if (creationUncertainRef.current) {
      throw new ApiError(
        0,
        "No se pudo confirmar la creación anterior. Para evitar generar un convenio duplicado, no se volverá a crear automáticamente.",
      );
    }

    let response: Awaited<ReturnType<typeof convenios.create>>;
    try {
      response = await convenios.create(toCreateInput(values));
    } catch (error) {
      if (
        !(error instanceof ApiError) ||
        error.status < 400 ||
        error.status >= 500
      ) {
        creationUncertainRef.current = true;
      }
      throw error;
    }

    const createdId = response.data.instrumento?.id_instrumento;
    if (!Number.isInteger(createdId) || createdId <= 0) {
      creationUncertainRef.current = true;
      throw new ApiError(
        201,
        "La API creó una respuesta incompleta y no confirmó el identificador del convenio. No se repetirá la creación para evitar duplicados.",
      );
    }

    instrumentIdRef.current = createdId;
    setInstrumentId(createdId);
    return createdId;
  }

  async function saveDraft(values: ConvenioFormValues): Promise<number> {
    const id = await ensureInstrument(values);
    setNotice({
      kind: "info",
      message: isEditMode
        ? "Guardando los cambios del convenio..."
        : "Guardando la ficha del borrador...",
    });
    await convenios.update(id, toUpdateInput(values));
    return id;
  }

  async function saveAndExit() {
    if (processingRef.current) return;
    processingRef.current = true;
    setOperation("saving");
    setNotice(null);
    clearErrors();
    try {
      if (!(await trigger(draftFields))) {
        setCurrentStep(getFirstStepWithErrors(form) ?? 1);
        return;
      }

      const values = getValues();
      const id = await saveDraft(values);
      await synchronizeDocuments(id, values);
      if (!isEditMode) resetNewConvenio();
      setNotice({
        kind: "success",
        message: isEditMode
          ? "Cambios guardados correctamente. Volviendo al detalle..."
          : "Borrador guardado correctamente. Volviendo a convenios...",
      });
      await waitForSuccessNotice();
      router.push(isEditMode ? `/convenios/${id}` : "/convenios");
    } catch (error) {
      const documentError =
        error instanceof DocumentSyncError ? error.originalError : error;
      const errorStep = applyApiFieldErrors(documentError, setError);
      if (errorStep !== null) setCurrentStep(errorStep);
      setNotice({
        kind: "error",
        message:
          error instanceof DocumentSyncError
            ? error.message
            : getApiMessage(error),
      });
    } finally {
      processingRef.current = false;
      setOperation(null);
    }
  }

  function handleDocumentFieldErrors(
    error: unknown,
    candidate: UploadCandidate,
  ) {
    applyApiFieldErrors(error, setError, {
      fileField: candidate.fileField,
      titleField: candidate.titleField,
    });
  }

  async function synchronizeDocuments(
    id: number,
    values: ConvenioFormValues,
  ): Promise<void> {
    const candidates = getDocumentCandidates(values);
    const serverDocuments = (await convenios.listDocuments(id)).data;
    const claimedIds = new Set<number>();
    const currentClientIds = new Set(candidates.map(({ clientId }) => clientId));

    for (const candidate of candidates) {
      setNotice({
        kind: "info",
        message: `Verificando "${candidate.titulo}"...`,
      });
      const hash = candidate.archivo
        ? await fileHash(candidate.archivo)
        : null;
      const tracked = uploadedDocumentsRef.current.get(candidate.clientId);
      const trackedDocument = tracked
        ? serverDocuments.find(
            ({ documento }) => documento.id_documento === tracked.id,
          )
        : undefined;

      if (tracked && !trackedDocument) {
        uploadedDocumentsRef.current.delete(candidate.clientId);
      }

      const existingDocument =
        trackedDocument ??
        (candidate.documentoId === null
          ? undefined
          : serverDocuments.find(
              ({ documento }) =>
                documento.id_documento === candidate.documentoId,
            ));

      if (existingDocument) {
        const documentId = existingDocument.documento.id_documento;
        const currentHash =
          existingDocument.ultima_version?.hash_sha256.toLowerCase();
        const sameFile =
          candidate.archivo === null || currentHash === hash;
        const keepExistingPrincipalTitle =
          candidate.titleField === "tituloDocumentoPrincipal" &&
          candidate.titulo === "" &&
          candidate.archivo === null;
        const sameDetails =
          existingDocument.documento.tipo === candidate.tipo &&
          (existingDocument.documento.titulo === candidate.titulo ||
            keepExistingPrincipalTitle);

        if (!sameFile || !sameDetails) {
          setNotice({
            kind: "info",
            message: `Actualizando "${candidate.titulo}"...`,
          });
          try {
            if (sameFile) {
              await convenios.updateDocumentDetails(documentId, {
                tipo: candidate.tipo,
                titulo: candidate.titulo,
              });
            } else if (candidate.archivo) {
              await convenios.updateDocument(documentId, {
                tipo: candidate.tipo,
                titulo: candidate.titulo,
                archivo: candidate.archivo,
              });
            }
          } catch (error) {
            handleDocumentFieldErrors(error, candidate);
            throw new DocumentSyncError(candidate.titulo, error);
          }
        }

        claimedIds.add(documentId);
        uploadedDocumentsRef.current.set(candidate.clientId, { id: documentId });
        continue;
      }

      if (candidate.documentoId !== null && candidate.archivo === null) {
        throw new DocumentSyncError(
          candidate.titulo,
          new ApiError(
            404,
            "El documento existente ya no está disponible. Seleccioná un archivo PDF para cargarlo nuevamente.",
          ),
        );
      }

      if (candidate.archivo === null || hash === null) continue;

      const matchingDocument = serverDocuments.find(
        ({ documento, ultima_version }) =>
          !claimedIds.has(documento.id_documento) &&
          documento.tipo === candidate.tipo &&
          documento.titulo === candidate.titulo &&
          ultima_version?.hash_sha256.toLowerCase() === hash,
      );
      if (matchingDocument) {
        const documentId = matchingDocument.documento.id_documento;
        claimedIds.add(documentId);
        uploadedDocumentsRef.current.set(candidate.clientId, { id: documentId });
        continue;
      }

      const sameNameDocument = serverDocuments.find(
        ({ documento }) =>
          !claimedIds.has(documento.id_documento) &&
          documento.tipo === candidate.tipo &&
          documento.titulo === candidate.titulo,
      );
      if (sameNameDocument) {
        const documentId = sameNameDocument.documento.id_documento;
        setNotice({
          kind: "info",
          message: `Actualizando "${candidate.titulo}"...`,
        });
        try {
          await convenios.updateDocument(documentId, {
            tipo: candidate.tipo,
            titulo: candidate.titulo,
            archivo: candidate.archivo,
          });
        } catch (error) {
          handleDocumentFieldErrors(error, candidate);
          throw new DocumentSyncError(candidate.titulo, error);
        }
        claimedIds.add(documentId);
        uploadedDocumentsRef.current.set(candidate.clientId, { id: documentId });
        continue;
      }

      setNotice({
        kind: "info",
        message: `Cargando "${candidate.titulo}"...`,
      });
      try {
        const response = await convenios.uploadDocument(id, {
          tipo: candidate.tipo,
          titulo: candidate.titulo,
          archivo: candidate.archivo,
        });
        const documentId = response.data.documento.id_documento;
        claimedIds.add(documentId);
        uploadedDocumentsRef.current.set(candidate.clientId, { id: documentId });
        serverDocuments.push({
          documento: response.data.documento,
          ultima_version: response.data.version,
        });
      } catch (error) {
        handleDocumentFieldErrors(error, candidate);
        throw new DocumentSyncError(candidate.titulo, error);
      }
    }

    for (const [clientId, uploaded] of uploadedDocumentsRef.current) {
      if (currentClientIds.has(clientId)) continue;
      const stillExists = serverDocuments.some(
        ({ documento }) => documento.id_documento === uploaded.id,
      );
      if (stillExists) {
        setNotice({
          kind: "info",
          message: "Quitando un anexo que ya no está seleccionado...",
        });
        await convenios.deleteDocument(uploaded.id);
      }
      uploadedDocumentsRef.current.delete(clientId);
    }
  }

  async function requestReview() {
    if (processingRef.current) return;
    processingRef.current = true;
    setOperation("validating");
    setNotice(null);
    setDialogError(null);
    clearErrors();

    try {
      const basicsValid = await trigger(stepFields[1]);
      const detailsValid = await trigger(stepFields[2]);
      const documentsValid = await trigger(stepFields[3]);
      const values = getValues();
      let requirementsValid = true;

      if (values.institucionesFirmantes.length < 2) {
        setError("institucionesFirmantes", {
          type: "minimum",
          message: "Se necesitan al menos dos instituciones firmantes.",
        });
        requirementsValid = false;
      }
      if (
        !values.archivoPrincipal &&
        values.documentoPrincipalId === null
      ) {
        setError("archivoPrincipal", {
          type: "required",
          message: "El documento principal PDF es obligatorio.",
        });
        requirementsValid = false;
      }
      values.anexos.forEach((anexo, index) => {
        if (!anexo.archivo && anexo.documentoId === null) {
          setError(`anexos.${index}.archivo`, {
            type: "required",
            message: "Adjuntá un archivo PDF para este anexo.",
          });
          requirementsValid = false;
        }
      });

      if (
        !basicsValid ||
        !detailsValid ||
        !documentsValid ||
        !requirementsValid
      ) {
        const firstInvalidStep = getFirstStepWithErrors(form);
        setCurrentStep(firstInvalidStep ?? 3);
        return;
      }
      setConfirmationOpen(true);
    } catch (error) {
      setNotice({ kind: "error", message: getApiMessage(error) });
    } finally {
      processingRef.current = false;
      setOperation(null);
    }
  }

  async function confirmReview() {
    if (processingRef.current) return;
    processingRef.current = true;
    setOperation("sending");
    setDialogError(null);
    setNotice(null);

    try {
      const values = getValues();
      const id = await ensureInstrument(values);
      setNotice({ kind: "info", message: "Actualizando la ficha del convenio..." });
      await convenios.update(id, toUpdateInput(values));
      await synchronizeDocuments(id, values);
      setNotice({ kind: "info", message: "Enviando el convenio a revisión..." });
      const response = await convenios.sendForReview(id);
      setConfirmationOpen(false);
      if (!isEditMode) resetNewConvenio();
      setNotice({ kind: "success", message: response.message });
      await waitForSuccessNotice();
      router.push(`/convenios/${id}`);
    } catch (error) {
      const errorStep = applyApiFieldErrors(error, setError);
      if (errorStep !== null) setCurrentStep(errorStep);
      const message = getApiMessage(error);
      setDialogError(message);
      setNotice({ kind: "error", message });
    } finally {
      processingRef.current = false;
      setOperation(null);
    }
  }

  if (authStatus === "loading") {
    return (
      <p role="status" className="rounded-md border border-ui-border bg-surface p-4">
        Verificando la sesión...
      </p>
    );
  }

  if (!user || !canManage) {
    return (
      <ErrorState
        message={`Solo los usuarios de Gestión o Administración pueden ${
          isEditMode ? "editar" : "crear"
        } convenios.`}
      />
    );
  }

  if (
    isEditMode &&
    (!Number.isSafeInteger(convenioId) || convenioId <= 0)
  ) {
    return <ErrorState message="No se encontró el convenio solicitado." />;
  }

  if (loadError || (!loadingOptions && (!options || !optionsInitialized))) {
    return (
      <div className="space-y-3 rounded-md border border-ui-border bg-surface p-4">
        <p role="alert">{loadError ?? "No se pudieron cargar los datos del formulario."}</p>
        <Button type="button" onClick={() => void loadOptions()} loading={loadingOptions}>
          Reintentar
        </Button>
      </div>
    );
  }

  if (loadingOptions || !optionsInitialized || !options) {
    return (
      <p role="status" aria-live="polite" className="rounded-md border border-ui-border bg-surface p-4">
        Cargando unidades, instituciones y convenios marco...
      </p>
    );
  }

  if (isEditMode && loadingExisting) {
    return (
      <p role="status" aria-live="polite" className="rounded-md border border-ui-border bg-surface p-4">
        Cargando el convenio y sus documentos...
      </p>
    );
  }

  if (isEditMode && editDenied) {
    return (
      <div className="space-y-3 rounded-md border border-ui-border bg-surface p-4">
        <ErrorState message="Solo se pueden editar convenios en estado Borrador." />
        <Button type="button" onClick={() => router.push(`/convenios/${convenioId}`)}>
          Volver al convenio
        </Button>
      </div>
    );
  }

  if (isEditMode && editLoadError) {
    return (
      <div className="space-y-3 rounded-md border border-ui-border bg-surface p-4">
        <ErrorState message={editLoadError.message} />
        {editLoadError.status !== 403 && editLoadError.status !== 404 ? (
          <Button
            type="button"
            onClick={() => {
              setLoadingExisting(true);
              setEditLoadError(null);
              setEditDenied(false);
              void loadEditData();
            }}
          >
            Reintentar
          </Button>
        ) : null}
        <Button type="button" onClick={() => router.push("/convenios")}>
          Volver a convenios
        </Button>
      </div>
    );
  }

  if (isEditMode && !editLoaded) {
    return (
      <p role="status" aria-live="polite" className="rounded-md border border-ui-border bg-surface p-4">
        Cargando el convenio y sus documentos...
      </p>
    );
  }

  return (
    <div className="mx-auto w-full max-w-5xl">
      <Stepper
        currentStep={currentStep}
        onStepChange={setStep}
        disabled={busy}
      />

      <form
        noValidate
        aria-label="Formulario de convenio"
        aria-busy={busy}
        onSubmit={(event) => event.preventDefault()}
        className="overflow-hidden rounded-lg border border-ui-border bg-surface text-foreground shadow-sm"
      >
        <fieldset disabled={busy} className="min-w-0 border-0 p-0">
          <div className="space-y-6 p-4 sm:p-6">
            {notice ? (
              <p
                role={notice.kind === "error" ? "alert" : "status"}
                aria-live={notice.kind === "error" ? "assertive" : "polite"}
                className="rounded-md border border-ui-border bg-page px-4 py-3 text-sm"
              >
                {notice.message}
              </p>
            ) : null}
            {instrumentId ? (
              <p className="text-sm text-foreground/70">
                {isEditMode ? "Convenio" : "Borrador"} #{instrumentId}. Los
                cambios se guardarán sobre este convenio; no se crearán
                registros adicionales.
              </p>
            ) : null}

            <div key={formResetKey}>
              <div hidden={currentStep !== 1}>
                <StepDatosBasicos
                  form={form}
                  user={user}
                  unidades={options.unidades}
                  instituciones={options.instituciones}
                  conveniosMarco={options.conveniosMarco}
                />
              </div>
              <div hidden={currentStep !== 2}>
                <StepFichaPlazos form={form} />
              </div>
              <div hidden={currentStep !== 3}>
                <StepDocumentos form={form} />
              </div>
            </div>
          </div>
        </fieldset>

        <footer className="flex flex-col gap-3 border-t border-ui-border bg-page px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <button
            type="button"
            disabled={busy}
            onClick={() =>
              router.push(
                isEditMode ? `/convenios/${convenioId}` : "/convenios",
              )
            }
            className="min-h-11 enabled:cursor-pointer rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
          >
            Cancelar
          </button>

          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            {currentStep > 1 ? (
              <button
                type="button"
                disabled={busy}
                onClick={() => setStep(currentStep - 1)}
                className="min-h-11 enabled:cursor-pointer rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
              >
                Anterior
              </button>
            ) : null}
            <button
              type="button"
              disabled={busy}
              aria-busy={operation === "saving"}
              onClick={() => void saveAndExit()}
              className="min-h-11 enabled:cursor-pointer rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
            >
              {operation === "saving" ? "Guardando..." : "Guardar y salir"}
            </button>
            {currentStep < 3 ? (
              <Button
                type="button"
                disabled={busy}
                onClick={() => void goToNextStep()}
                className="!w-full enabled:cursor-pointer sm:!w-auto"
              >
                {operation === "validating" ? "Validando..." : "Siguiente"}
              </Button>
            ) : (
              <div>
                <Button
                  ref={sendButtonRef}
                  type="button"
                  disabled={busy}
                  onClick={() => void requestReview()}
                  className="!w-full enabled:cursor-pointer sm:!w-auto"
                >
                  {operation === "validating"
                    ? "Validando..."
                    : "Enviar a revisión"}
                </Button>
                {errors.archivoPrincipal ? (
                  <p className="sr-only" role="alert">
                    {errors.archivoPrincipal.message}
                  </p>
                ) : null}
              </div>
            )}
          </div>
        </footer>
      </form>

      <ConfirmDialog
        open={confirmationOpen}
        title="Enviar convenio a revisión"
        description="Se guardará la ficha, se cargarán los documentos pendientes y se solicitará la revisión. ¿Confirmás el envío?"
        confirmLabel="Confirmar envío"
        loading={operation === "sending"}
        errorMessage={dialogError}
        returnFocusRef={sendButtonRef}
        onConfirm={() => void confirmReview()}
        onCancel={() => {
          if (!processingRef.current) {
            setConfirmationOpen(false);
            setDialogError(null);
          }
        }}
      />
    </div>
  );
}
