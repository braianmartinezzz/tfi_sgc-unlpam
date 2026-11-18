"use client";

import { useFieldArray, useWatch, type UseFormReturn } from "react-hook-form";
import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import {
  isValidPdfFile,
  type ConvenioFormValues,
} from "@/components/convenios/form/schema";

type StepDocumentosProps = {
  form: UseFormReturn<ConvenioFormValues>;
};

const textIcon = (
  <svg viewBox="0 0 24 24" fill="currentColor" className="h-[18px] w-[18px]">
    <path d="M5 3h9l5 5v13H5V3Zm8 2v4h4l-4-4ZM8 12v2h8v-2H8Zm0 4v2h8v-2H8Z" />
  </svg>
);

function formatFileSize(bytes: number): string {
  return bytes >= 1024 * 1024
    ? `${(bytes / (1024 * 1024)).toFixed(2)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function getPdfError(file: File): string {
  return file.size > 20 * 1024 * 1024
    ? "El PDF no puede superar los 20 MB."
    : "El archivo debe ser un PDF.";
}

export default function StepDocumentos({ form }: StepDocumentosProps) {
  const {
    clearErrors,
    control,
    formState: { errors },
    register,
    setError,
    setValue,
  } = form;
  const { append, fields, remove } = useFieldArray({
    control,
    name: "anexos",
  });
  const titulo = useWatch({ control, name: "titulo" });
  const tipo = useWatch({ control, name: "tipo" });
  const idUnidadAcademica = useWatch({ control, name: "idUnidadAcademica" });
  const claseInstrumento = useWatch({ control, name: "claseInstrumento" });
  const idConvenioMarco = useWatch({ control, name: "idConvenioMarco" });
  const institucionesFirmantes = useWatch({
    control,
    name: "institucionesFirmantes",
  });
  const archivoPrincipal = useWatch({ control, name: "archivoPrincipal" });
  const documentoPrincipalId = useWatch({
    control,
    name: "documentoPrincipalId",
  });
  const nombreArchivoPrincipal = useWatch({
    control,
    name: "nombreArchivoPrincipal",
  });
  const anexos = useWatch({ control, name: "anexos" });

  const fichaCompleta =
    titulo.trim() !== "" && tipo !== "" && idUnidadAcademica !== "";
  const institucionesCompletas = institucionesFirmantes.length >= 2;
  const marcoCompleto =
    claseInstrumento !== "Específico" || idConvenioMarco !== "";
  const pdfPrincipalCargado =
    archivoPrincipal !== null || documentoPrincipalId !== null;

  function seleccionarPdfPrincipal(file: File | undefined) {
    clearErrors("archivoPrincipal");
    if (!file) {
      setValue("archivoPrincipal", null, {
        shouldDirty: true,
        shouldValidate: true,
      });
      return;
    }

    if (!isValidPdfFile(file)) {
      setValue("archivoPrincipal", null, { shouldDirty: true });
      setError("archivoPrincipal", {
        type: "validate",
        message: getPdfError(file),
      });
      return;
    }

    setValue("archivoPrincipal", file, {
      shouldDirty: true,
      shouldValidate: true,
    });
  }

  function seleccionarAnexo(index: number, file: File | undefined) {
    const field = `anexos.${index}.archivo` as const;
    clearErrors(field);

    if (!file) {
      setValue(field, null, { shouldDirty: true, shouldValidate: true });
      return;
    }

    if (!isValidPdfFile(file)) {
      setValue(field, null, { shouldDirty: true });
      setError(field, { type: "validate", message: getPdfError(file) });
      return;
    }

    setValue(field, file, { shouldDirty: true, shouldValidate: true });
  }

  return (
    <section aria-labelledby="form-step-3-title" className="space-y-6">
      <div>
        <h2
          id="form-step-3-title"
          tabIndex={-1}
          className="text-xl font-bold outline-none focus-visible:ring-2 focus-visible:ring-brand"
        >
          Documentos y envío
        </h2>
        <p className="mt-1 text-sm text-foreground/70">
          Adjuntá el documento principal y, si corresponde, sus anexos.
        </p>
      </div>

      <section
        aria-labelledby="documento-principal-title"
        className="space-y-4 rounded-md border border-ui-border p-4 sm:p-5"
      >
        <div>
          <h3 id="documento-principal-title" className="font-semibold">
            Documento principal
          </h3>
          <p className="mt-1 text-sm text-foreground/70">
            Tipo fijo: Convenio · PDF de hasta 20 MB.
          </p>
        </div>
        <div>
          <Input
            label="Título del documento principal *"
            icon={textIcon}
            {...register("tituloDocumentoPrincipal")}
            aria-invalid={Boolean(errors.tituloDocumentoPrincipal)}
            aria-describedby={
              errors.tituloDocumentoPrincipal
                ? "titulo-documento-error"
                : undefined
            }
          />
          {errors.tituloDocumentoPrincipal ? (
            <p
              id="titulo-documento-error"
              role="alert"
              className="mt-1 text-sm text-brand-text"
            >
              {errors.tituloDocumentoPrincipal.message}
            </p>
          ) : null}
        </div>

        <div className="space-y-2">
          <label htmlFor="documento-principal-file" className="block text-sm font-medium">
            {documentoPrincipalId === null
              ? "Archivo PDF principal"
              : "Reemplazar archivo PDF principal"}{" "}
            {documentoPrincipalId === null ? (
              <span aria-hidden="true">*</span>
            ) : null}
          </label>
          <input
            id="documento-principal-file"
            type="file"
            accept="application/pdf,.pdf"
            required={documentoPrincipalId === null}
            aria-invalid={Boolean(errors.archivoPrincipal)}
            aria-describedby="documento-principal-ayuda documento-principal-error"
            onChange={(event) =>
              seleccionarPdfPrincipal(event.currentTarget.files?.[0])
            }
            className="block min-h-11 w-full rounded-sm border border-ui-border bg-surface px-3 py-2 text-sm text-foreground file:mr-3 file:rounded-sm file:border-0 file:bg-page file:px-3 file:py-1.5 file:font-medium file:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
          />
          <p id="documento-principal-ayuda" className="text-sm text-foreground/70">
            {documentoPrincipalId !== null
              ? "El archivo existente se conserva si no seleccionás uno nuevo."
              : "Si seleccionaste un PDF borrador en el paso anterior, ya está disponible como documento principal."}
          </p>
          {archivoPrincipal ? (
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-ui-border bg-page p-3 text-sm">
              <span className="min-w-0 break-all">
                {archivoPrincipal.name} · {formatFileSize(archivoPrincipal.size)}
              </span>
              <button
                type="button"
                onClick={() => seleccionarPdfPrincipal(undefined)}
                className="min-h-9 rounded-sm px-2 font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
              >
                Quitar PDF
              </button>
            </div>
          ) : null}
          {!archivoPrincipal && nombreArchivoPrincipal ? (
            <p className="break-all text-sm">
              Archivo actual: {nombreArchivoPrincipal}
            </p>
          ) : null}
          {errors.archivoPrincipal ? (
            <p
              id="documento-principal-error"
              role="alert"
              className="text-sm font-medium text-brand-text"
            >
              {errors.archivoPrincipal.message}
            </p>
          ) : null}
        </div>
      </section>

      <section aria-labelledby="anexos-title" className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 id="anexos-title" className="font-semibold">
              Anexos
            </h3>
            <p className="mt-1 text-sm text-foreground/70">
              Cada anexo debe ser un PDF de hasta 20 MB.
            </p>
          </div>
          <button
            type="button"
            onClick={() =>
              append({
                clientId: crypto.randomUUID(),
                documentoId: null,
                nombreArchivo: null,
                tipo: "Otro",
                titulo: "",
                archivo: null,
              })
            }
            className="min-h-11 rounded-sm border border-ui-border px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
          >
            Agregar anexo
          </button>
        </div>

        {fields.length === 0 ? (
          <p className="rounded-md border border-dashed border-ui-border px-4 py-5 text-sm text-foreground/70">
            No agregaste anexos.
          </p>
        ) : null}

        <div className="space-y-4">
          {fields.map((field, index) => {
            const anexoError = errors.anexos?.[index];
            const archivo = anexos[index]?.archivo;
            const nombreArchivo = anexos[index]?.nombreArchivo;
            const tipoAnexo = anexos[index]?.tipo;
            const tipoAnexoNoListado =
              tipoAnexo &&
              ![
                "Acta de aprobación",
                "Documentación de la parte externa",
                "Otro",
              ].includes(tipoAnexo);
            const tituloId = `anexo-${field.id}-titulo`;
            const tipoId = `anexo-${field.id}-tipo`;
            const archivoId = `anexo-${field.id}-archivo`;

            return (
              <fieldset
                key={field.id}
                className="space-y-4 rounded-md border border-ui-border p-4"
              >
                <legend className="px-1 text-sm font-semibold">
                  Anexo {index + 1}
                </legend>
                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <Select
                      id={tipoId}
                      label="Tipo de anexo"
                      requiredMark
                      required
                      {...register(`anexos.${index}.tipo`)}
                      aria-invalid={Boolean(anexoError?.tipo)}
                      aria-describedby={
                        anexoError?.tipo ? `${tipoId}-error` : undefined
                      }
                    >
                      <option value="Acta de aprobación">Acta de aprobación</option>
                      <option value="Documentación de la parte externa">
                        Documentación de la parte externa
                      </option>
                      <option value="Otro">Otro</option>
                      {tipoAnexo && tipoAnexoNoListado ? (
                        <option value={tipoAnexo}>
                          {tipoAnexo}
                        </option>
                      ) : null}
                    </Select>
                    {anexoError?.tipo ? (
                      <p
                        id={`${tipoId}-error`}
                        role="alert"
                        className="mt-1 text-sm text-brand-text"
                      >
                        {anexoError.tipo.message}
                      </p>
                    ) : null}
                  </div>
                  <div>
                    <Input
                      id={tituloId}
                      label="Título del anexo *"
                      icon={textIcon}
                      required
                      {...register(`anexos.${index}.titulo`)}
                      aria-invalid={Boolean(anexoError?.titulo)}
                      aria-describedby={
                        anexoError?.titulo ? `${tituloId}-error` : undefined
                      }
                    />
                    {anexoError?.titulo ? (
                      <p
                        id={`${tituloId}-error`}
                        role="alert"
                        className="mt-1 text-sm text-brand-text"
                      >
                        {anexoError.titulo.message}
                      </p>
                    ) : null}
                  </div>
                </div>

                <div className="space-y-2">
                  <label htmlFor={archivoId} className="block text-sm font-medium">
                    Archivo PDF del anexo <span aria-hidden="true">*</span>
                  </label>
                  <input
                    id={archivoId}
                    type="file"
                    accept="application/pdf,.pdf"
                    aria-invalid={Boolean(anexoError?.archivo)}
                    aria-describedby={
                      anexoError?.archivo
                        ? `${archivoId}-ayuda ${archivoId}-error`
                        : `${archivoId}-ayuda`
                    }
                    onChange={(event) =>
                      seleccionarAnexo(index, event.currentTarget.files?.[0])
                    }
                    className="block min-h-11 w-full rounded-sm border border-ui-border bg-surface px-3 py-2 text-sm text-foreground file:mr-3 file:rounded-sm file:border-0 file:bg-page file:px-3 file:py-1.5 file:font-medium file:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                  />
                  <p id={`${archivoId}-ayuda`} className="text-sm text-foreground/70">
                    Seleccioná un documento en formato PDF.
                  </p>
                  {archivo ? (
                    <p className="break-all text-sm">
                      {archivo.name} · {formatFileSize(archivo.size)}
                    </p>
                  ) : nombreArchivo ? (
                    <p className="break-all text-sm">
                      Archivo actual: {nombreArchivo}. Se conserva si no
                      seleccionás uno nuevo.
                    </p>
                  ) : null}
                  {anexoError?.archivo ? (
                    <p
                      id={`${archivoId}-error`}
                      role="alert"
                      className="text-sm font-medium text-brand-text"
                    >
                      {anexoError.archivo.message}
                    </p>
                  ) : null}
                </div>

                <div className="flex justify-end">
                  <button
                    type="button"
                    onClick={() => remove(index)}
                    className="min-h-10 rounded-sm px-3 text-sm font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                  >
                    Quitar anexo {index + 1}
                  </button>
                </div>
              </fieldset>
            );
          })}
        </div>
      </section>

      <section
        aria-labelledby="requisitos-title"
        className="rounded-md border border-ui-border bg-page p-4 sm:p-5"
      >
        <h3 id="requisitos-title" className="font-semibold">
          Resumen de requisitos para enviar a revisión
        </h3>
        <ul className="mt-3 space-y-2 text-sm">
          <Requirement
            complete={fichaCompleta}
            label="Ficha básica completa (título, tipo y unidad)"
          />
          <Requirement
            complete={institucionesCompletas}
            label="Al menos 2 instituciones firmantes"
          />
          <Requirement
            complete={marcoCompleto}
            label="Convenio marco seleccionado si es específico"
          />
          <Requirement
            complete={pdfPrincipalCargado}
            label="Documento principal PDF cargado"
          />
        </ul>
      </section>
    </section>
  );
}

function Requirement({
  complete,
  label,
}: {
  complete: boolean;
  label: string;
}) {
  return (
    <li className="flex items-start gap-2">
      <span
        aria-hidden="true"
        className={`mt-px font-bold ${
          complete ? "text-brand-text" : "text-foreground/60"
        }`}
      >
        {complete ? "✓" : "×"}
      </span>
      <span>{label}</span>
    </li>
  );
}
