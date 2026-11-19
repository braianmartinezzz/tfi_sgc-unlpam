"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect, useRef, useState } from "react";
import { useForm, type FieldPath } from "react-hook-form";
import { z } from "zod";
import { useCreateRepresentante, useUpdateRepresentante } from "@/hooks/useRepresentantes";
import { ApiError } from "@/services/api-client";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import type { Representante, RepresentanteWrite } from "@/types/representante";

const representanteSchema = z.object({
  nombre: z
    .string()
    .trim()
    .min(1, "El nombre es obligatorio.")
    .max(100, "El nombre no puede superar los 100 caracteres."),
  apellido: z
    .string()
    .trim()
    .min(1, "El apellido es obligatorio.")
    .max(100, "El apellido no puede superar los 100 caracteres."),
  dni: z
    .string()
    .trim()
    .min(1, "El DNI es obligatorio.")
    .max(15, "El DNI no puede superar los 15 caracteres."),
  cuil: z.string().trim().max(13, "El CUIL no puede superar los 13 caracteres."),
  cargo: z.string().trim().max(100, "El cargo no puede superar los 100 caracteres."),
});

type RepresentanteFormValues = z.infer<typeof representanteSchema>;
type RepresentanteField = FieldPath<RepresentanteFormValues>;

const fieldMap: Readonly<Record<string, RepresentanteField>> = {
  nombre: "nombre",
  apellido: "apellido",
  dni: "dni",
  cuil: "cuil",
  cargo: "cargo",
};

const personIcon = (
  <svg aria-hidden="true" className="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="currentColor">
    <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-5.33 0-8 2.67-8 5v3h16v-3c0-2.33-2.67-5-8-5Z" />
  </svg>
);

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 403) {
    return "No tenés permisos para realizar esta acción.";
  }
  if (error instanceof ApiError && error.status === 409) return error.message;
  return "No se pudo guardar el representante. Intentá nuevamente.";
}

function FieldError({ id, message }: { id: string; message?: string }) {
  return message ? (
    <p id={id} role="alert" className="mt-1 text-sm text-red-700 dark:text-red-300">
      {message}
    </p>
  ) : null;
}

interface RepresentanteFormProps {
  institucionId: number;
  representante: Representante | null;
  onClose: () => void;
  onSuccess: (message: string) => void;
}

export default function RepresentanteForm({
  institucionId,
  representante,
  onClose,
  onSuccess,
}: RepresentanteFormProps) {
  const firstFieldRef = useRef<HTMLInputElement>(null);
  const createMutation = useCreateRepresentante();
  const updateMutation = useUpdateRepresentante();
  const [formError, setFormError] = useState<string | null>(null);
  const isPending = createMutation.isPending || updateMutation.isPending;
  const {
    clearErrors,
    formState: { errors },
    handleSubmit,
    register,
    setError,
  } = useForm<RepresentanteFormValues>({
    resolver: zodResolver(representanteSchema),
    mode: "onTouched",
    defaultValues: {
      nombre: representante?.persona.nombre ?? "",
      apellido: representante?.persona.apellido ?? "",
      dni: representante?.persona.dni ?? "",
      cuil: representante?.persona.cuil ?? "",
      cargo: representante?.cargo ?? "",
    },
  });
  const nombreRegistration = register("nombre");

  useEffect(() => {
    firstFieldRef.current?.focus();
  }, []);

  async function onSubmit(values: RepresentanteFormValues) {
    if (isPending) return;
    clearErrors();
    setFormError(null);
    const cuil = values.cuil || null;
    const cargo = values.cargo || null;

    try {
      let responseMessage: string | undefined;
      if (representante) {
        const input: Partial<RepresentanteWrite> = {};
        if (values.nombre !== representante.persona.nombre) {
          input.nombre = values.nombre;
        }
        if (values.apellido !== representante.persona.apellido) {
          input.apellido = values.apellido;
        }
        if (values.dni !== representante.persona.dni) input.dni = values.dni;
        if (cuil !== representante.persona.cuil) input.cuil = cuil;
        if (cargo !== representante.cargo) input.cargo = cargo;

        if (Object.keys(input).length === 0) {
          onSuccess("No hay cambios para guardar.");
          return;
        }

        const response = await updateMutation.mutateAsync({
          id: representante.id_persona,
          input,
        });
        responseMessage = response.message;
      } else {
        const input: RepresentanteWrite = {
          nombre: values.nombre,
          apellido: values.apellido,
          dni: values.dni,
          cuil,
          cargo,
          id_institucion: institucionId,
        };
        const response = await createMutation.mutateAsync(input);
        responseMessage = response.message;
      }

      onSuccess(
        responseMessage ??
          (representante
            ? "Representante actualizado correctamente."
            : "Representante creado correctamente."),
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
      aria-label={
        representante ? "Editar representante" : "Crear representante"
      }
      className="rounded-lg border border-ui-border bg-page p-4 text-foreground sm:p-5"
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h3 className="text-lg font-bold">
          {representante ? "Editar representante" : "Nuevo representante"}
        </h3>
        <button
          type="button"
          disabled={isPending}
          onClick={onClose}
          className="min-h-11 rounded-md border border-ui-border bg-surface px-4 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-60"
        >
          Cerrar
        </button>
      </div>
      <p className="mt-1 text-sm text-foreground/80">
        La institución asignada es la actual y no se puede cambiar acá.
      </p>
      {formError ? (
        <p role="alert" aria-live="assertive" className="mt-4 text-sm text-red-700 dark:text-red-300">
          {formError}
        </p>
      ) : null}
      <div className="mt-5 grid gap-5 md:grid-cols-2">
        <div>
          <Input
            id="representante-nombre"
            label="Nombre *"
            icon={personIcon}
            maxLength={100}
            required
            {...nombreRegistration}
            ref={(element) => {
              nombreRegistration.ref(element);
              firstFieldRef.current = element;
            }}
            aria-invalid={Boolean(errors.nombre)}
            aria-describedby={errors.nombre ? "representante-nombre-error" : undefined}
          />
          <FieldError
            id="representante-nombre-error"
            message={errors.nombre?.message}
          />
        </div>
        <div>
          <Input
            id="representante-apellido"
            label="Apellido *"
            icon={personIcon}
            maxLength={100}
            required
            {...register("apellido")}
            aria-invalid={Boolean(errors.apellido)}
            aria-describedby={
              errors.apellido ? "representante-apellido-error" : undefined
            }
          />
          <FieldError
            id="representante-apellido-error"
            message={errors.apellido?.message}
          />
        </div>
        <div>
          <Input
            id="representante-dni"
            label="DNI *"
            icon={personIcon}
            maxLength={15}
            required
            {...register("dni")}
            aria-invalid={Boolean(errors.dni)}
            aria-describedby={errors.dni ? "representante-dni-error" : undefined}
          />
          <FieldError id="representante-dni-error" message={errors.dni?.message} />
        </div>
        <div>
          <Input
            id="representante-cuil"
            label="CUIL"
            icon={personIcon}
            maxLength={13}
            {...register("cuil")}
            aria-invalid={Boolean(errors.cuil)}
            aria-describedby={errors.cuil ? "representante-cuil-error" : undefined}
          />
          <FieldError
            id="representante-cuil-error"
            message={errors.cuil?.message}
          />
        </div>
        <div className="md:col-span-2">
          <Input
            id="representante-cargo"
            label="Cargo"
            icon={personIcon}
            maxLength={100}
            {...register("cargo")}
            aria-invalid={Boolean(errors.cargo)}
            aria-describedby={errors.cargo ? "representante-cargo-error" : undefined}
          />
          <FieldError
            id="representante-cargo-error"
            message={errors.cargo?.message}
          />
        </div>
      </div>
      <div className="mt-5 flex flex-wrap justify-end gap-3">
        <button
          type="button"
          disabled={isPending}
          onClick={onClose}
          className="min-h-11 rounded-md border border-ui-border bg-surface px-4 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-60"
        >
          Cancelar
        </button>
        <Button type="submit" loading={isPending} className="min-h-11 w-auto">
          {isPending ? "Guardando..." : "Guardar representante"}
        </Button>
      </div>
    </form>
  );
}
