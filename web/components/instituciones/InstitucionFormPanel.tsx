"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect, useRef, useState } from "react";
import { useForm, type FieldPath } from "react-hook-form";
import { z } from "zod";
import { useCreateInstitucion, useUpdateInstitucion } from "@/hooks/useInstituciones";
import { ApiError } from "@/services/api-client";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import Tabs from "@/components/ui/Tabs";
import RepresentantesSection from "@/components/instituciones/RepresentantesSection";
import type {
  InstitucionFirmante,
  InstitucionFirmanteWrite,
} from "@/types/unidad";

const institucionSchema = z.object({
  razon_social: z
    .string()
    .trim()
    .min(1, "La razón social es obligatoria.")
    .max(255, "La razón social no puede superar los 255 caracteres."),
  cuit: z.string().trim().max(13, "El CUIT no puede superar los 13 caracteres."),
  domicilio_legal: z
    .string()
    .trim()
    .max(255, "El domicilio legal no puede superar los 255 caracteres."),
  tipo_institucion: z
    .string()
    .trim()
    .max(50, "El tipo de institución no puede superar los 50 caracteres."),
});

type InstitucionFormValues = z.infer<typeof institucionSchema>;
type InstitucionField = FieldPath<InstitucionFormValues>;

const fieldMap: Readonly<Record<string, InstitucionField>> = {
  razon_social: "razon_social",
  cuit: "cuit",
  domicilio_legal: "domicilio_legal",
  tipo_institucion: "tipo_institucion",
};

const institutionIcon = (
  <svg aria-hidden="true" className="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="currentColor">
    <path d="M3 21h18v-2H3v2Zm2-4h14V7l-7-4-7 4v10Zm3-8h2v2H8V9Zm0 4h2v2H8v-2Zm6-4h2v2h-2V9Zm0 4h2v2h-2v-2Z" />
  </svg>
);

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 403) {
    return "No tenés permisos para realizar esta acción.";
  }
  if (error instanceof ApiError && error.status === 409) return error.message;
  return "No se pudo guardar la institución. Intentá nuevamente.";
}

function FieldError({ id, message }: { id: string; message?: string }) {
  return message ? (
    <p id={id} role="alert" className="mt-1 text-sm text-red-700 dark:text-red-300">
      {message}
    </p>
  ) : null;
}

interface InstitucionFormPanelProps {
  institucion: InstitucionFirmante | null;
  initialTab?: "institucion" | "representantes";
  onClose: () => void;
  onCreated: (institucion: InstitucionFirmante, message: string) => void;
  onSaved: (message: string) => void;
}

export default function InstitucionFormPanel({
  institucion,
  initialTab = "institucion",
  onClose,
  onCreated,
  onSaved,
}: InstitucionFormPanelProps) {
  const [activeTab, setActiveTab] = useState(initialTab);
  const firstFieldRef = useRef<HTMLInputElement>(null);
  const createMutation = useCreateInstitucion();
  const updateMutation = useUpdateInstitucion();
  const [formError, setFormError] = useState<string | null>(null);
  const isPending = createMutation.isPending || updateMutation.isPending;
  const {
    clearErrors,
    formState: { errors },
    handleSubmit,
    register,
    setError,
  } = useForm<InstitucionFormValues>({
    resolver: zodResolver(institucionSchema),
    mode: "onTouched",
    defaultValues: {
      razon_social: institucion?.razon_social ?? "",
      cuit: institucion?.cuit ?? "",
      domicilio_legal: institucion?.domicilio_legal ?? "",
      tipo_institucion: institucion?.tipo_institucion ?? "",
    },
  });
  const razonSocialRegistration = register("razon_social");

  useEffect(() => {
    if (activeTab === "institucion") firstFieldRef.current?.focus();
  }, [activeTab]);

  async function onSubmit(values: InstitucionFormValues) {
    if (isPending) return;
    clearErrors();
    setFormError(null);
    const cuit = values.cuit || null;
    const domicilioLegal = values.domicilio_legal || null;
    const tipoInstitucion = values.tipo_institucion || null;

    try {
      if (institucion) {
        const input: Partial<InstitucionFirmanteWrite> = {};
        if (values.razon_social !== institucion.razon_social) {
          input.razon_social = values.razon_social;
        }
        if (cuit !== institucion.cuit) input.cuit = cuit;
        if (domicilioLegal !== institucion.domicilio_legal) {
          input.domicilio_legal = domicilioLegal;
        }
        if (tipoInstitucion !== institucion.tipo_institucion) {
          input.tipo_institucion = tipoInstitucion;
        }
        if (Object.keys(input).length === 0) {
          onSaved("No hay cambios para guardar.");
          return;
        }
        const response = await updateMutation.mutateAsync({
          id: institucion.id_institucion,
          input,
        });
        onSaved(response.message ?? "Institución actualizada correctamente.");
      } else {
        const input: InstitucionFirmanteWrite = {
          razon_social: values.razon_social,
          cuit,
          domicilio_legal: domicilioLegal,
          tipo_institucion: tipoInstitucion,
        };
        const response = await createMutation.mutateAsync(input);
        onCreated(
          response.data,
          response.message ?? "Institución creada correctamente.",
        );
      }
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

  const tabs = [
    { value: "institucion", label: "Institución" },
    {
      value: "representantes",
      label: "Representantes",
      disabled: !institucion,
      disabledReason: !institucion
        ? "Guardá la institución para administrar sus representantes."
        : undefined,
    },
  ];

  return (
    <section
      aria-label={institucion ? "Edición de institución" : "Alta de institución"}
      className="rounded-lg border border-ui-border bg-surface p-4 text-foreground shadow-sm sm:p-6"
    >
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-xl font-bold">
          {institucion ? "Editar institución" : "Nueva institución"}
        </h2>
        <button
          type="button"
          disabled={isPending}
          onClick={onClose}
          className="min-h-11 rounded-md border border-ui-border px-4 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-60"
        >
          Cerrar
        </button>
      </div>
      <Tabs
        label="Secciones de la institución"
        tabs={tabs}
        value={activeTab}
        focusSelectedOnMount={initialTab === "representantes"}
        onChange={(value) => {
          if (value === "institucion" || value === "representantes") {
            setActiveTab(value);
          }
        }}
      >
        {activeTab === "institucion" ? (
          <form
            noValidate
            onSubmit={handleSubmit(onSubmit)}
            aria-label={
              institucion
                ? "Formulario de edición de institución"
                : "Formulario de creación de institución"
            }
            className="overflow-hidden rounded-lg border border-ui-border bg-surface text-foreground"
          >
            <div className="space-y-5 p-4 sm:p-6">
              {formError ? (
                <p role="alert" aria-live="assertive" className="text-sm text-red-700 dark:text-red-300">
                  {formError}
                </p>
              ) : null}
              <div className="grid gap-5 md:grid-cols-2">
                <div className="md:col-span-2">
                  <Input
                    id="institucion-razon-social"
                    label="Razón social *"
                    icon={institutionIcon}
                    maxLength={255}
                    required
                    {...razonSocialRegistration}
                    ref={(element) => {
                      razonSocialRegistration.ref(element);
                      firstFieldRef.current = element;
                    }}
                    aria-invalid={Boolean(errors.razon_social)}
                    aria-describedby={
                      errors.razon_social
                        ? "institucion-razon-social-error"
                        : undefined
                    }
                  />
                  <FieldError
                    id="institucion-razon-social-error"
                    message={errors.razon_social?.message}
                  />
                </div>
                <div>
                  <Input
                    id="institucion-cuit"
                    label="CUIT"
                    icon={institutionIcon}
                    maxLength={13}
                    {...register("cuit")}
                    aria-invalid={Boolean(errors.cuit)}
                    aria-describedby={
                      errors.cuit ? "institucion-cuit-error" : undefined
                    }
                  />
                  <FieldError
                    id="institucion-cuit-error"
                    message={errors.cuit?.message}
                  />
                </div>
                <div>
                  <Input
                    id="institucion-tipo"
                    label="Tipo de institución"
                    icon={institutionIcon}
                    maxLength={50}
                    {...register("tipo_institucion")}
                    aria-invalid={Boolean(errors.tipo_institucion)}
                    aria-describedby={
                      errors.tipo_institucion ? "institucion-tipo-error" : undefined
                    }
                  />
                  <FieldError
                    id="institucion-tipo-error"
                    message={errors.tipo_institucion?.message}
                  />
                </div>
                <div className="md:col-span-2">
                  <Input
                    id="institucion-domicilio"
                    label="Domicilio legal"
                    icon={institutionIcon}
                    maxLength={255}
                    {...register("domicilio_legal")}
                    aria-invalid={Boolean(errors.domicilio_legal)}
                    aria-describedby={
                      errors.domicilio_legal
                        ? "institucion-domicilio-error"
                        : undefined
                    }
                  />
                  <FieldError
                    id="institucion-domicilio-error"
                    message={errors.domicilio_legal?.message}
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
              <Button type="submit" loading={isPending} className="min-h-11 !w-full sm:!w-auto">
                {isPending ? "Guardando..." : "Guardar institución"}
              </Button>
            </footer>
          </form>
        ) : institucion ? (
          <RepresentantesSection institucion={institucion} />
        ) : null}
      </Tabs>
    </section>
  );
}
