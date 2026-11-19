"use client";

import { useState } from "react";
import {
  useWatch,
  type UseFormReturn,
} from "react-hook-form";
import type { ConvenioListItem } from "@/types/convenio";
import type { UsuarioAutenticado } from "@/types/auth";
import type { InstitucionFirmante, UnidadAcademica } from "@/types/unidad";
import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import type { ConvenioFormValues } from "@/components/convenios/form/schema";

type StepDatosBasicosProps = {
  form: UseFormReturn<ConvenioFormValues>;
  user: UsuarioAutenticado;
  unidades: UnidadAcademica[];
  instituciones: InstitucionFirmante[];
  conveniosMarco: ConvenioListItem[];
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

export default function StepDatosBasicos({
  form,
  user,
  unidades,
  instituciones,
  conveniosMarco,
}: StepDatosBasicosProps) {
  const [busqueda, setBusqueda] = useState("");
  const {
    control,
    formState: { errors },
    getValues,
    register,
    setValue,
  } = form;
  const claseInstrumento = useWatch({ control, name: "claseInstrumento" });
  const unidadSeleccionada = useWatch({ control, name: "idUnidadAcademica" });
  const institucionesSeleccionadas = useWatch({
    control,
    name: "institucionesFirmantes",
  });
  const unidadAsignada = unidades.find(
    (unidad) => unidad.id_unidad_academica === user.id_unidad,
  );
  const unidadesVisibles =
    user.rol?.nombre === "Gestión" && unidadAsignada
      ? [unidadAsignada]
      : unidades;
  const institucionesFiltradas = instituciones.filter(
    (institucion) =>
      institucion.activo &&
      !institucionesSeleccionadas.includes(String(institucion.id_institucion)) &&
      `${institucion.razon_social} ${institucion.cuit}`
        .toLowerCase()
        .includes(busqueda.trim().toLowerCase()),
  );
  const selectedInstitutions = instituciones.filter((institucion) =>
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

    const unidad = unidades.find(
      (item) => String(item.id_unidad_academica) === id,
    );
    const institucion = instituciones.find(
      (item) => item.id_institucion === unidad?.id_institucion,
    );
    if (unidad && institucion?.activo) {
      agregarInstitucion(String(unidad.id_institucion));
    }
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
            className="enabled:cursor-pointer"
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
            floatingLabel={false}
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
            className="enabled:cursor-pointer"
            value={unidadSeleccionada}
            disabled={user.rol?.nombre === "Gestión"}
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
                {!unidad.activo ? " (inactiva)" : ""}
              </option>
            ))}
          </Select>
          {user.rol?.nombre === "Gestión" ? (
            <p id="unidad-ayuda" className="mt-1 text-sm text-foreground/70">
              Unidad asignada al usuario autenticado.
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
              className="enabled:cursor-pointer"
              {...register("idConvenioMarco")}
              aria-invalid={Boolean(errors.idConvenioMarco)}
              aria-describedby={
                errors.idConvenioMarco ? "convenio-marco-error" : undefined
              }
            >
              <option value="">Seleccioná un convenio marco</option>
              {conveniosMarco.map((convenio) => (
                <option
                  key={convenio.id_instrumento}
                  value={convenio.id_instrumento}
                >
                  {convenio.instrumento?.titulo ?? "Convenio marco"}
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
                  className="flex min-h-12 w-full cursor-pointer flex-wrap items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand"
                >
                  <span>
                    {institucion.razon_social}
                    {!institucion.activo ? " (inactiva)" : ""}
                  </span>
                  {institucion.cuit ? (
                    <span className="text-foreground/70">{institucion.cuit}</span>
                  ) : null}
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
                className="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-full text-brand-text hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
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
