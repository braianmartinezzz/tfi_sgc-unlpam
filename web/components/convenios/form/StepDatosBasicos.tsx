"use client";

import { useState } from "react";
import {
  useWatch,
  type UseFormReturn,
} from "react-hook-form";
import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import {
  currentUser,
  mockConveniosMarco,
  mockInstitucionesFirmantes,
  mockUnidadesAcademicas,
} from "@/components/convenios/form/mock-data";
import {
  isValidPdfFile,
  type ConvenioFormValues,
} from "@/components/convenios/form/schema";

type StepDatosBasicosProps = {
  form: UseFormReturn<ConvenioFormValues>;
};

const textIcon = (
  <svg viewBox="0 0 24 24" fill="currentColor" className="h-[18px] w-[18px]">
    <path d="M5 3h9l5 5v13H5V3Zm8 2v4h4l-4-4ZM8 12v2h8v-2H8Zm0 4v2h8v-2H8Z" />
  </svg>
);

const searchIcon = (
  <svg viewBox="0 0 24 24" fill="currentColor" className="h-[18px] w-[18px]">
    <path d="M10.5 3a7.5 7.5 0 1 0 4.65 13.38L20.77 22 22 20.77l-5.62-5.62A7.5 7.5 0 0 0 10.5 3Zm0 2a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11Z" />
  </svg>
);

function formatFileSize(bytes: number): string {
  return bytes >= 1024 * 1024
    ? `${(bytes / (1024 * 1024)).toFixed(2)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export default function StepDatosBasicos({ form }: StepDatosBasicosProps) {
  const [busqueda, setBusqueda] = useState("");
  const {
    clearErrors,
    control,
    formState: { errors },
    getValues,
    register,
    setError,
    setValue,
  } = form;
  const claseInstrumento = useWatch({ control, name: "claseInstrumento" });
  const unidadSeleccionada = useWatch({ control, name: "idUnidadAcademica" });
  const institucionesSeleccionadas = useWatch({
    control,
    name: "institucionesFirmantes",
  });
  const archivoPrincipal = useWatch({ control, name: "archivoPrincipal" });
  const unidadAsignada = mockUnidadesAcademicas.find(
    (unidad) => unidad.id_unidad_academica === currentUser.id_unidad,
  );
  const unidadesVisibles =
    currentUser.rol === "Gestión" && unidadAsignada
      ? [unidadAsignada]
      : mockUnidadesAcademicas;
  const institucionesFiltradas = mockInstitucionesFirmantes.filter(
    (institucion) =>
      !institucionesSeleccionadas.includes(String(institucion.id_institucion)) &&
      `${institucion.razon_social} ${institucion.cuit}`
        .toLowerCase()
        .includes(busqueda.trim().toLowerCase()),
  );
  const selectedInstitutions = mockInstitucionesFirmantes.filter((institucion) =>
    institucionesSeleccionadas.includes(String(institucion.id_institucion)),
  );

  function agregarInstitucion(id: string) {
    const actuales = getValues("institucionesFirmantes");
    if (!actuales.includes(id)) {
      setValue("institucionesFirmantes", [...actuales, id], {
        shouldDirty: true,
        shouldValidate: true,
      });
    }
    setBusqueda("");
  }

  function seleccionarUnidad(id: string) {
    setValue("idUnidadAcademica", id, {
      shouldDirty: true,
      shouldValidate: true,
    });

    const unidad = mockUnidadesAcademicas.find(
      (item) => String(item.id_unidad_academica) === id,
    );
    if (unidad) agregarInstitucion(String(unidad.id_institucion));
  }

  function seleccionarPdf(file: File | undefined) {
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
        message:
          file.size > 20 * 1024 * 1024
            ? "El PDF no puede superar los 20 MB."
            : "El archivo debe ser un PDF.",
      });
      return;
    }

    setValue("archivoPrincipal", file, {
      shouldDirty: true,
      shouldValidate: true,
    });
  }

  return (
    <section aria-labelledby="form-step-1-title" className="space-y-6">
      <div>
        <h2
          id="form-step-1-title"
          tabIndex={-1}
          className="text-xl font-bold outline-none focus-visible:ring-2 focus-visible:ring-brand"
        >
          Datos básicos e instituciones
        </h2>
        <p className="mt-1 text-sm text-foreground/70">
          Definí el instrumento y las partes que lo integran.
        </p>
      </div>

      <div className="space-y-2">
        <label htmlFor="pdf-borrador" className="block text-sm font-medium">
          PDF borrador (opcional)
        </label>
        <input
          id="pdf-borrador"
          type="file"
          accept="application/pdf,.pdf"
          aria-invalid={Boolean(errors.archivoPrincipal)}
          aria-describedby="pdf-borrador-ayuda pdf-borrador-error"
          onChange={(event) => seleccionarPdf(event.currentTarget.files?.[0])}
          className="block min-h-11 w-full rounded-sm border border-ui-border bg-surface px-3 py-2 text-sm text-foreground file:mr-3 file:rounded-sm file:border-0 file:bg-page file:px-3 file:py-1.5 file:font-medium file:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
        />
        <p id="pdf-borrador-ayuda" className="text-sm text-foreground/70">
          Si el PDF trae título y asunto en sus propiedades, completaremos esos
          campos.
        </p>
        {/* TODO: Leer los metadatos del PDF para completar título y asunto. */}
        {archivoPrincipal ? (
          <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-ui-border bg-page p-3 text-sm">
            <span className="min-w-0 break-all">
              {archivoPrincipal.name} · {formatFileSize(archivoPrincipal.size)}
            </span>
            <button
              type="button"
              onClick={() => seleccionarPdf(undefined)}
              className="min-h-9 rounded-sm px-2 font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
              Quitar PDF
            </button>
          </div>
        ) : null}
        {errors.archivoPrincipal ? (
          <p
            id="pdf-borrador-error"
            role="alert"
            className="text-sm font-medium text-brand-text"
          >
            {errors.archivoPrincipal.message}
          </p>
        ) : null}
      </div>

      <fieldset
        aria-describedby={
          errors.claseInstrumento ? "clase-instrumento-error" : undefined
        }
        className="space-y-2"
      >
        <legend className="text-sm font-medium">
          Clase de instrumento <span aria-hidden="true">*</span>
        </legend>
        <div className="grid gap-3 sm:grid-cols-2">
          {(["Marco", "Específico"] as const).map((clase) => (
            <label
              key={clase}
              className={`flex min-h-14 cursor-pointer items-center gap-3 rounded-md border px-4 py-3 text-sm font-medium focus-within:ring-2 focus-within:ring-brand ${
                claseInstrumento === clase
                  ? "border-brand bg-brand/5"
                  : "border-ui-border bg-surface hover:bg-row-alternate"
              }`}
            >
              <input
                {...register("claseInstrumento")}
                type="radio"
                value={clase}
                required
                aria-describedby={
                  errors.claseInstrumento
                    ? "clase-instrumento-error"
                    : undefined
                }
                className="h-4 w-4 accent-brand"
              />
              {clase}
            </label>
          ))}
        </div>
        {errors.claseInstrumento ? (
          <p
            id="clase-instrumento-error"
            role="alert"
            className="text-sm font-medium text-brand-text"
          >
            {errors.claseInstrumento.message}
          </p>
        ) : null}
      </fieldset>

      <div className="grid gap-5 md:grid-cols-2">
        <div>
          <Select
            label="Tipo"
            requiredMark
            required
            {...register("tipo")}
            aria-invalid={Boolean(errors.tipo)}
            aria-describedby={errors.tipo ? "tipo-error" : undefined}
          >
            <option value="">Seleccioná un tipo</option>
            <option value="Colaboración">Colaboración</option>
            <option value="Pasantía">Pasantía</option>
          </Select>
          {errors.tipo ? (
            <p id="tipo-error" role="alert" className="mt-1 text-sm text-brand-text">
              {errors.tipo.message}
            </p>
          ) : null}
        </div>

        <div>
          <Input
            label="Título *"
            icon={textIcon}
            maxLength={255}
            required
            placeholder="Título del convenio"
            {...register("titulo")}
            aria-invalid={Boolean(errors.titulo)}
            aria-describedby={errors.titulo ? "titulo-error" : undefined}
          />
          {errors.titulo ? (
            <p
              id="titulo-error"
              role="alert"
              className="mt-1 text-sm text-brand-text"
            >
              {errors.titulo.message}
            </p>
          ) : null}
        </div>

        <div>
          <Select
            label="Unidad académica"
            requiredMark
            required
            value={unidadSeleccionada}
            disabled={currentUser.rol === "Gestión"}
            onChange={(event) => seleccionarUnidad(event.currentTarget.value)}
            aria-invalid={Boolean(errors.idUnidadAcademica)}
            aria-describedby={
              errors.idUnidadAcademica ? "unidad-error" : "unidad-ayuda"
            }
          >
            <option value="">Seleccioná una unidad</option>
            {unidadesVisibles.map((unidad) => (
              <option
                key={unidad.id_unidad_academica}
                value={unidad.id_unidad_academica}
              >
                {unidad.sigla} — {unidad.nombre}
              </option>
            ))}
          </Select>
          {currentUser.rol === "Gestión" ? (
            <p id="unidad-ayuda" className="mt-1 text-sm text-foreground/70">
              Unidad asignada al usuario de muestra.
            </p>
          ) : null}
          {errors.idUnidadAcademica ? (
            <p id="unidad-error" role="alert" className="mt-1 text-sm text-brand-text">
              {errors.idUnidadAcademica.message}
            </p>
          ) : null}
        </div>

        {claseInstrumento === "Específico" ? (
          <div>
            <Select
              label="Convenio marco relacionado"
              requiredMark
              required
              {...register("idConvenioMarco")}
              aria-invalid={Boolean(errors.idConvenioMarco)}
              aria-describedby={
                errors.idConvenioMarco ? "convenio-marco-error" : undefined
              }
            >
              <option value="">Seleccioná un convenio marco</option>
              {mockConveniosMarco.map((convenio) => (
                <option
                  key={convenio.id_instrumento}
                  value={convenio.id_instrumento}
                >
                  {convenio.titulo}
                </option>
              ))}
            </Select>
            {errors.idConvenioMarco ? (
              <p
                id="convenio-marco-error"
                role="alert"
                className="mt-1 text-sm text-brand-text"
              >
                {errors.idConvenioMarco.message}
              </p>
            ) : null}
          </div>
        ) : null}
      </div>

      <fieldset
        aria-describedby={
          errors.institucionesFirmantes
            ? "instituciones-ayuda instituciones-error"
            : "instituciones-ayuda"
        }
        className="space-y-3"
      >
        <legend className="text-sm font-semibold">
          Instituciones firmantes <span aria-hidden="true">*</span>
        </legend>
        <p id="instituciones-ayuda" className="text-sm text-foreground/70">
          Para enviar a revisión se necesitan al menos 2 instituciones.
        </p>
        <Input
          label="Buscar instituciones"
          icon={searchIcon}
          value={busqueda}
          onChange={(event) => setBusqueda(event.currentTarget.value)}
          placeholder="Razón social o CUIT"
          aria-describedby={
            errors.institucionesFirmantes
              ? "instituciones-ayuda instituciones-error"
              : "instituciones-ayuda"
          }
          aria-invalid={Boolean(errors.institucionesFirmantes)}
          autoComplete="off"
        />

        {institucionesFiltradas.length > 0 ? (
          <ul
            aria-label="Resultados de instituciones firmantes"
            className="max-h-52 divide-y divide-ui-border overflow-y-auto rounded-md border border-ui-border"
          >
            {institucionesFiltradas.map((institucion) => (
              <li key={institucion.id_institucion}>
                <button
                  type="button"
                  onClick={() =>
                    agregarInstitucion(String(institucion.id_institucion))
                  }
                  className="flex min-h-12 w-full flex-wrap items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand"
                >
                  <span>{institucion.razon_social}</span>
                  <span className="text-foreground/70">{institucion.cuit}</span>
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <p role="status" className="text-sm text-foreground/70">
            No hay instituciones que coincidan con la búsqueda.
          </p>
        )}

        <ul aria-label="Instituciones seleccionadas" className="flex flex-wrap gap-2">
          {selectedInstitutions.map((institucion) => (
            <li
              key={institucion.id_institucion}
              className="inline-flex max-w-full items-center gap-2 rounded-full border border-ui-border bg-page py-1 pl-3 pr-1 text-sm"
            >
              <span className="break-all">{institucion.razon_social}</span>
              <button
                type="button"
                aria-label={`Quitar ${institucion.razon_social}`}
                onClick={() =>
                  setValue(
                    "institucionesFirmantes",
                    getValues("institucionesFirmantes").filter(
                      (id) => id !== String(institucion.id_institucion),
                    ),
                    { shouldDirty: true, shouldValidate: true },
                  )
                }
                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-brand-text hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
              >
                <span aria-hidden="true">×</span>
              </button>
            </li>
          ))}
        </ul>
        {errors.institucionesFirmantes?.message ? (
          <p
            id="instituciones-error"
            role="alert"
            className="text-sm font-medium text-brand-text"
          >
            {errors.institucionesFirmantes.message}
          </p>
        ) : null}
      </fieldset>
    </section>
  );
}
