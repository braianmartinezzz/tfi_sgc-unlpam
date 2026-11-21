"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect, useRef, useState } from "react";
import { useForm, type FieldPath } from "react-hook-form";
import { z } from "zod";
import { ApiError } from "@/services/api-client";
import { useCreateUnidad, useUpdateUnidad } from "@/hooks/useUnidades";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import type {
  InstitucionFirmante,
  UnidadAcademica,
  UnidadAcademicaWrite,
} from "@/types/unidad";

const unidadSchema = z.object({
  sigla: z
    .string()
    .trim()
    .min(1, "La sigla es obligatoria.")
    .max(20, "La sigla no puede superar los 20 caracteres."),
  nombre: z
    .string()
    .trim()
    .min(1, "El nombre es obligatorio.")
    .max(150, "El nombre no puede superar los 150 caracteres."),
  id_institucion: z
    .string()
    .regex(/^[1-9]\d*$/, "Seleccioná una institución."),
});

type UnidadFormValues = z.infer<typeof unidadSchema>;
type UnidadField = FieldPath<UnidadFormValues>;

const fieldMap: Readonly<Record<string, UnidadField>> = {
  sigla: "sigla",
  nombre: "nombre",
  id_institucion: "id_institucion",
};

const fieldIcon = (
  <svg aria-hidden="true" className="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="currentColor">
    <path d="M4 4h16v16H4zM7 7v3h3V7zm7 0v3h3V7zM7 14v3h3v-3zm7 0v3h3v-3z" />
  </svg>
);

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 403) {
    return "No tenés permisos para realizar esta acción.";
  }
  if (error instanceof ApiError && error.status === 409) return error.message;
  return "No se pudo guardar la unidad. Intentá nuevamente.";
}

function FieldError({ id, message }: { id: string; message?: string }) {
  return message ? (
    <p id={id} role="alert" className="mt-1 text-sm text-red-700 dark:text-red-300">
      {message}
    </p>
  ) : null;
}

export interface UnidadFormPanelProps {
  unidad: UnidadAcademica | null;
  instituciones: InstitucionFirmante[];
  institutionIsReadOnly: boolean;
  onClose: () => void;
  onSuccess: (message: string) => void;
}

export default function UnidadFormPanel({
  unidad,
  instituciones,
  institutionIsReadOnly,
  onClose,
  onSuccess,
}: UnidadFormPanelProps) {
  const firstFieldRef = useRef<HTMLInputElement>(null);
  const createMutation = useCreateUnidad();
  const updateMutation = useUpdateUnidad();
  const [formError, setFormError] = useState<string | null>(null);
  const isPending = createMutation.isPending || updateMutation.isPending;
  const form = useForm<UnidadFormValues>({
    resolver: zodResolver(unidadSchema),
    mode: "onTouched",
    defaultValues: {
      sigla: unidad?.sigla ?? "",
      nombre: unidad?.nombre ?? "",
      id_institucion: unidad ? String(unidad.id_institucion) : "",
    },
  });
  const {
    clearErrors,
    formState: { errors },
    handleSubmit,
    register,
    setError,
  } = form;
  const siglaRegistration = register("sigla");

  useEffect(() => {
    firstFieldRef.current?.focus();
  }, []);

  async function onSubmit(values: UnidadFormValues) {
    if (isPending) return;
    clearErrors();
    setFormError(null);
    const input: Partial<UnidadAcademicaWrite> = {};

    if (!unidad || values.sigla !== unidad.sigla) input.sigla = values.sigla;
    if (!unidad || values.nombre !== unidad.nombre) input.nombre = values.nombre;
    if (
      !unidad ||
      (!institutionIsReadOnly &&
        Number(values.id_institucion) !== unidad.id_institucion)
    ) {
      input.id_institucion = Number(values.id_institucion);
    }

    try {
      if (unidad && Object.keys(input).length === 0) {
        onSuccess("No hay cambios para guardar.");
        return;
      }

      const response = unidad
        ? await updateMutation.mutateAsync({
            id: unidad.id_unidad_academica,
            input,
          })
        : await createMutation.mutateAsync({
            sigla: values.sigla,
            nombre: values.nombre,
            id_institucion: Number(values.id_institucion),
          });
      onSuccess(
        response.message ??
          (unidad
            ? "Unidad académica actualizada correctamente."
            : "Unidad académica creada correctamente."),
      );
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        let hasMappedError = false;
        for (const [field, messages] of Object.entries(error.errors ?? {})) {
          const formField = fieldMap[field];
          const message = messages[0];
          if (formField && message) {
            setError(formField, { type: "server", message });
            hasMappedError = true;
          }
        }
        setFormError(
          error.message ||
            (hasMappedError
              ? "Revisá los campos indicados."
              : "Los datos enviados no son válidos."),
        );
        return;
      }
      setFormError(getErrorMessage(error));
    }
  }

  return (
    <form
      noValidate
      onSubmit={handleSubmit(onSubmit)}
      aria-label={unidad ? "Editar unidad académica" : "Crear unidad académica"}
      className="overflow-hidden rounded-lg border border-ui-border bg-surface text-foreground shadow-sm"
    >
      <div className="space-y-5 p-4 sm:p-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="text-xl font-bold">
            {unidad ? "Editar unidad académica" : "Nueva unidad académica"}
          </h2>
          <button
            type="button"
            onClick={onClose}
            disabled={isPending}
            className="min-h-11 rounded-md border border-ui-border px-4 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-60"
          >
            Cerrar
          </button>
        </div>
        {formError ? (
          <p role="alert" aria-live="assertive" className="text-sm text-red-700 dark:text-red-300">
            {formError}
          </p>
        ) : null}
        <div className="grid gap-5 md:grid-cols-2">
          <div>
            <Input
              id="unidad-sigla"
              label="Sigla *"
              icon={fieldIcon}
              maxLength={20}
              required
              {...siglaRegistration}
              ref={(element) => {
                siglaRegistration.ref(element);
                firstFieldRef.current = element;
              }}
              aria-invalid={Boolean(errors.sigla)}
              aria-describedby={errors.sigla ? "unidad-sigla-error" : undefined}
            />
            <FieldError id="unidad-sigla-error" message={errors.sigla?.message} />
          </div>
          <div>
            <Input
              id="unidad-nombre"
              label="Nombre *"
              icon={fieldIcon}
              maxLength={150}
              required
              {...register("nombre")}
              aria-invalid={Boolean(errors.nombre)}
              aria-describedby={errors.nombre ? "unidad-nombre-error" : undefined}
            />
            <FieldError id="unidad-nombre-error" message={errors.nombre?.message} />
          </div>
          <div className="md:col-span-2">
            {unidad && institutionIsReadOnly ? (
              <>
                <Select
                  id="unidad-institucion"
                  label="Institución"
                  disabled
                  value={String(unidad.id_institucion)}
                >
                  <option value={unidad.id_institucion}>
                    {instituciones.find(
                      (item) => item.id_institucion === unidad.id_institucion,
                    )?.razon_social ??
                      unidad.institucion_firmante?.razon_social ??
                      "Institución asignada"}
                  </option>
                </Select>
                <input
                  type="hidden"
                  {...register("id_institucion")}
                />
                <p className="mt-1 text-sm text-foreground/80">
                  La institución no se puede cambiar desde este perfil.
                </p>
              </>
            ) : (
              <Select
                id="unidad-institucion"
                label="Institución"
                requiredMark
                required
                className="enabled:cursor-pointer"
                {...register("id_institucion")}
                aria-invalid={Boolean(errors.id_institucion)}
                aria-describedby={
                  errors.id_institucion
                    ? "unidad-institucion-error"
                    : undefined
                }
              >
                <option value="">Seleccioná una institución</option>
                {instituciones.map((item) => (
                  <option key={item.id_institucion} value={item.id_institucion}>
                    {item.razon_social}
                    {!item.activo ? " (Inactiva)" : ""}
                  </option>
                ))}
              </Select>
            )}
            <FieldError
              id="unidad-institucion-error"
              message={errors.id_institucion?.message}
            />
          </div>
        </div>
      </div>
      <footer className="flex flex-col gap-3 border-t border-ui-border bg-page px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
        <button
          type="button"
          disabled={isPending}
          onClick={onClose}
          className="min-h-11 rounded-md border border-ui-border bg-surface px-4 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-60"
        >
          Cancelar
        </button>
        <Button
          type="submit"
          loading={isPending}
          className="min-h-11 !w-full sm:!w-auto"
        >
          {isPending ? "Guardando..." : "Guardar"}
        </Button>
      </footer>
    </form>
  );
}
