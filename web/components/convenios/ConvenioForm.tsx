"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter } from "next/navigation";
import {
  useForm,
  useWatch,
  type FieldPath,
  type UseFormReturn,
} from "react-hook-form";
import ConfirmDialog from "@/components/feedback/ConfirmDialog";
import StepDatosBasicos from "@/components/convenios/form/StepDatosBasicos";
import StepDocumentos from "@/components/convenios/form/StepDocumentos";
import StepFichaPlazos from "@/components/convenios/form/StepFichaPlazos";
import Stepper from "@/components/convenios/form/Stepper";
import { convenioFormSchema, type ConvenioFormValues } from "@/components/convenios/form/schema";
import { useAuth } from "@/context/AuthContext";
import { ApiError } from "@/services/api-client";
import * as convenios from "@/services/convenios";
import * as institucionesFirmantes from "@/services/instituciones-firmantes";
import * as unidadesAcademicas from "@/services/unidades";
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
  archivo: File;
  fileField: FieldPath<ConvenioFormValues>;
  titleField: FieldPath<ConvenioFormValues>;
};
type UploadedDocument = {
  id: number;
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
  claseInstrumento: "",
  tipo: "",
  titulo: "",
  idUnidadAcademica: "",
  idConvenioMarco: "",
  institucionesFirmantes: [],
  archivoPrincipal: null,
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
    dias_preaviso: values.diasPreaviso,
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
    dias_preaviso: values.diasPreaviso,
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
  if (values.archivoPrincipal) {
    candidates.push({
      clientId: "principal",
      tipo: "Convenio",
      titulo: values.tituloDocumentoPrincipal.trim(),
      archivo: values.archivoPrincipal,
      fileField: "archivoPrincipal",
      titleField: "tituloDocumentoPrincipal",
    });
  }

  values.anexos.forEach((anexo, index) => {
    if (!anexo.archivo) return;
    candidates.push({
      clientId: anexo.clientId,
      tipo: anexo.tipo,
      titulo: anexo.titulo.trim(),
      archivo: anexo.archivo,
      fileField: `anexos.${index}.archivo`,
      titleField: `anexos.${index}.titulo`,
    });
  });
  return candidates;
}

function getSelectedDocumentFields(
  values: ConvenioFormValues,
): FieldPath<ConvenioFormValues>[] {
  const fields: FieldPath<ConvenioFormValues>[] = [];
  if (values.archivoPrincipal) {
    fields.push("tituloDocumentoPrincipal", "archivoPrincipal");
  }

  values.anexos.forEach((anexo, index) => {
    if (!anexo.archivo) return;
    fields.push(
      `anexos.${index}.tipo`,
      `anexos.${index}.titulo`,
      `anexos.${index}.archivo`,
    );
  });
  return fields;
}

async function waitForSuccessNotice(): Promise<void> {
  await new Promise<void>((resolve) => window.setTimeout(resolve, 900));
}

export default function ConvenioForm() {
  const router = useRouter();
  const { status: authStatus, user } = useAuth();
  const sendButtonRef = useRef<HTMLButtonElement>(null);
  const previousStep = useRef<StepNumber>(1);
  const processingRef = useRef(false);
  const creationUncertainRef = useRef(false);
  const instrumentIdRef = useRef<number | null>(null);
  const uploadedDocumentsRef = useRef(new Map<string, UploadedDocument>());
  const [instrumentId, setInstrumentId] = useState<number | null>(null);
  const [currentStep, setCurrentStep] = useState<StepNumber>(1);
  const [highestStep, setHighestStep] = useState<StepNumber>(1);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [dialogError, setDialogError] = useState<string | null>(null);
  const [confirmationOpen, setConfirmationOpen] = useState(false);
  const [operation, setOperation] = useState<Operation>(null);
  const [loadingOptions, setLoadingOptions] = useState(true);
  const [optionsInitialized, setOptionsInitialized] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [options, setOptions] = useState<FormOptions | null>(null);
  const form = useForm<ConvenioFormValues>({
    resolver: zodResolver(convenioFormSchema),
    mode: "onTouched",
    defaultValues,
  });
  const {
    clearErrors,
    control,
    formState: { errors },
    getValues,
    reset,
    setError,
    trigger,
  } = form;
  const titulo = useWatch({ control, name: "titulo" });
  const tipo = useWatch({ control, name: "tipo" });
  const idUnidadAcademica = useWatch({ control, name: "idUnidadAcademica" });
  const claseInstrumento = useWatch({ control, name: "claseInstrumento" });
  const idConvenioMarco = useWatch({ control, name: "idConvenioMarco" });
  const institucionesSeleccionadas = useWatch({
    control,
    name: "institucionesFirmantes",
  });
  const archivoPrincipal = useWatch({ control, name: "archivoPrincipal" });
  const role = user?.rol?.nombre;
  const canManage = isManagementRole(role);
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

      reset({
        ...defaultValues,
        idUnidadAcademica: unitId,
        institucionesFirmantes:
          isGestion && institutionId !== undefined
            ? [String(institutionId)]
            : [],
      });
      setLoadError(null);
      setOptionsInitialized(true);
    },
    [reset, user],
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

  const readyToReview =
    titulo.trim() !== "" &&
    tipo !== "" &&
    idUnidadAcademica !== "" &&
    claseInstrumento !== "" &&
    institucionesSeleccionadas.length >= 2 &&
    (claseInstrumento !== "Específico" || idConvenioMarco !== "") &&
    archivoPrincipal !== null;

  function setStep(step: number) {
    if (
      !processingRef.current &&
      step >= 1 &&
      step <= highestStep &&
      (step === 1 || step === 2 || step === 3)
    ) {
      setNotice(null);
      setCurrentStep(step);
    }
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
      setHighestStep((highest) => Math.max(highest, nextStep) as StepNumber);
    } catch (error) {
      setNotice({ kind: "error", message: getApiMessage(error) });
    } finally {
      processingRef.current = false;
      setOperation(null);
    }
  }

  async function ensureInstrument(values: ConvenioFormValues): Promise<number> {
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
    setNotice({ kind: "info", message: "Guardando la ficha del borrador..." });
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
      if (!(await trigger(stepFields[1]))) {
        setCurrentStep(1);
        return;
      }

      const values = getValues();
      const documentFields = getSelectedDocumentFields(values);
      if (documentFields.length > 0 && !(await trigger(documentFields))) {
        setCurrentStep(3);
        return;
      }

      const id = await saveDraft(values);
      await synchronizeDocuments(id, values);
      setNotice({
        kind: "success",
        message: "Borrador guardado correctamente. Volviendo a convenios...",
      });
      await waitForSuccessNotice();
      router.push("/convenios");
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
      const hash = await fileHash(candidate.archivo);
      const tracked = uploadedDocumentsRef.current.get(candidate.clientId);
      const trackedDocument = tracked
        ? serverDocuments.find(
            ({ documento }) => documento.id_documento === tracked.id,
          )
        : undefined;

      if (tracked && !trackedDocument) {
        uploadedDocumentsRef.current.delete(candidate.clientId);
      }

      const matchingDocument =
        trackedDocument ??
        serverDocuments.find(
          ({ documento, ultima_version }) =>
            !claimedIds.has(documento.id_documento) &&
            documento.tipo === candidate.tipo &&
            documento.titulo === candidate.titulo &&
            ultima_version?.hash_sha256.toLowerCase() === hash,
        );

      if (matchingDocument) {
        const documentId = matchingDocument.documento.id_documento;
        const currentHash =
          matchingDocument.ultima_version?.hash_sha256.toLowerCase();
        const sameFile = currentHash === hash;
        const sameDetails =
          matchingDocument.documento.tipo === candidate.tipo &&
          matchingDocument.documento.titulo === candidate.titulo;

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
            } else {
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
      if (!values.archivoPrincipal) {
        setError("archivoPrincipal", {
          type: "required",
          message: "El documento principal PDF es obligatorio.",
        });
        requirementsValid = false;
      }
      values.anexos.forEach((anexo, index) => {
        if (!anexo.archivo) {
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
      <p role="alert" className="rounded-md border border-ui-border bg-surface p-4">
        Solo los usuarios de Gestión o Administración pueden crear convenios.
      </p>
    );
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

  return (
    <div className="mx-auto w-full max-w-5xl">
      <Stepper
        currentStep={currentStep}
        highestStep={highestStep}
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
                Borrador #{instrumentId}. Los cambios se guardarán sobre este
                convenio; no se crearán registros adicionales.
              </p>
            ) : null}

            {currentStep === 1 ? (
              <StepDatosBasicos
                form={form}
                user={user}
                unidades={options.unidades}
                instituciones={options.instituciones}
                conveniosMarco={options.conveniosMarco}
              />
            ) : null}
            {currentStep === 2 ? <StepFichaPlazos form={form} /> : null}
            {currentStep === 3 ? <StepDocumentos form={form} /> : null}
          </div>
        </fieldset>

        <footer className="flex flex-col gap-3 border-t border-ui-border bg-page px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <button
            type="button"
            disabled={busy}
            onClick={() => router.push("/convenios")}
            className="min-h-11 rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
          >
            Cancelar
          </button>

          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            {currentStep > 1 ? (
              <button
                type="button"
                disabled={busy}
                onClick={() => setStep(currentStep - 1)}
                className="min-h-11 rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
              >
                Anterior
              </button>
            ) : null}
            <button
              type="button"
              disabled={busy}
              aria-busy={operation === "saving"}
              onClick={() => void saveAndExit()}
              className="min-h-11 rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
            >
              {operation === "saving" ? "Guardando..." : "Guardar y salir"}
            </button>
            {currentStep < 3 ? (
              <Button
                type="button"
                disabled={busy}
                onClick={() => void goToNextStep()}
                className="!w-full sm:!w-auto"
              >
                {operation === "validating" ? "Validando..." : "Siguiente"}
              </Button>
            ) : (
              <div>
                <Button
                  ref={sendButtonRef}
                  type="button"
                  disabled={!readyToReview || busy}
                  onClick={() => void requestReview()}
                  className="!w-full sm:!w-auto"
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
