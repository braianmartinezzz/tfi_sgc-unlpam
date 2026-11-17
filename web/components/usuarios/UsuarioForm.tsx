"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm, type FieldPath } from "react-hook-form";
import { useRouter, useSearchParams } from "next/navigation";
import { z } from "zod";
import { usePermissions } from "@/context/PermissionContext";
import { useCreateUsuario } from "@/hooks/useUsuarios";
import { useRoles } from "@/hooks/useRoles";
import { useUnidades } from "@/hooks/useUnidades";
import { ApiError } from "@/services/api-client";
import Button from "@/components/ui/Button";
import Checkbox from "@/components/ui/Checkbox";
import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import type { UsuarioResponse } from "@/types/usuario";

const usuarioSchema = z.object({
  mail: z
    .string()
    .min(1, "El correo electrónico es obligatorio.")
    .email("Ingresá un correo electrónico válido.")
    .max(150, "El correo electrónico no puede superar los 150 caracteres."),
  nombre: z
    .string()
    .refine((value) => value.trim().length > 0, "El nombre es obligatorio.")
    .max(100, "El nombre no puede superar los 100 caracteres."),
  password: z
    .string()
    .min(8, "La contraseña debe tener al menos 8 caracteres.")
    .max(255, "La contraseña no puede superar los 255 caracteres."),
  id_rol: z
    .string()
    .regex(/^[1-9]\d*$/, "Seleccioná un rol."),
  id_unidad: z
    .string()
    .regex(/^[1-9]\d*$/, "Seleccioná una unidad académica."),
  activo: z.boolean(),
  alcance_global: z.boolean(),
});

type UsuarioFormValues = z.infer<typeof usuarioSchema>;

const initialFormValues: UsuarioFormValues = {
  mail: "",
  nombre: "",
  password: "",
  id_rol: "",
  id_unidad: "",
  activo: true,
  alcance_global: false,
};

const validationFieldMap: Readonly<
  Record<string, FieldPath<UsuarioFormValues>>
> = {
  mail: "mail",
  nombre: "nombre",
  password: "password",
  id_rol: "id_rol",
  id_unidad: "id_unidad",
  activo: "activo",
  alcance_global: "alcance_global",
};

const emailIcon = (
  <svg
    aria-hidden="true"
    className="h-[18px] w-[18px]"
    viewBox="0 0 24 24"
    fill="currentColor"
  >
    <path d="M3 5.75A1.75 1.75 0 0 1 4.75 4h14.5A1.75 1.75 0 0 1 21 5.75v12.5A1.75 1.75 0 0 1 19.25 20H4.75A1.75 1.75 0 0 1 3 18.25V5.75Zm2 .2v.3l7 5.25 7-5.25v-.3H5Zm14 2.8-6.4 4.8a1 1 0 0 1-1.2 0L5 8.75v9.5h14v-9.5Z" />
  </svg>
);

const userIcon = (
  <svg
    aria-hidden="true"
    className="h-[18px] w-[18px]"
    viewBox="0 0 24 24"
    fill="currentColor"
  >
    <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-5.33 0-8 2.67-8 5v3h16v-3c0-2.33-2.67-5-8-5Z" />
  </svg>
);

const passwordIcon = (
  <svg
    aria-hidden="true"
    className="h-[18px] w-[18px]"
    viewBox="0 0 24 24"
    fill="currentColor"
  >
    <path d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2ZM10 6a2 2 0 1 1 4 0v2h-4V6Zm2 11a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z" />
  </svg>
);

function FieldError({
  id,
  message,
}: {
  id: string;
  message: string | undefined;
}) {
  return message ? (
    <p id={id} role="alert" className="mt-1 text-sm text-brand-text">
      {message}
    </p>
  ) : null;
}

function getCatalogError(error: unknown): string {
  if (error instanceof ApiError && error.status === 403) {
    return "No tenés permisos para consultar este catálogo.";
  }
  return error instanceof Error
    ? error.message
    : "Ocurrió un error inesperado. Intentá nuevamente.";
}

function AccessDenied() {
  return (
    <section aria-labelledby="new-user-title" className="space-y-5">
      <h1
        id="new-user-title"
        className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
      >
        Nuevo usuario
      </h1>
      <p
        role="alert"
        className="rounded-lg border border-ui-border bg-surface p-6 text-red-700 dark:text-red-300"
      >
        No tenés permisos para crear usuarios.
      </p>
    </section>
  );
}

function UsuarioCreateForm() {
  const router = useRouter();
  const rolesQuery = useRoles();
  const unidadesQuery = useUnidades();
  const createMutation = useCreateUsuario();
  const [formError, setFormError] = useState<string | null>(null);
  const [createdUsuario, setCreatedUsuario] =
    useState<UsuarioResponse | null>(null);
  const {
    clearErrors,
    formState: { errors },
    handleSubmit,
    register,
    setError,
  } = useForm<UsuarioFormValues>({
    resolver: zodResolver(usuarioSchema),
    mode: "onTouched",
    defaultValues: initialFormValues,
  });

  async function onSubmit(values: UsuarioFormValues) {
    if (createMutation.isPending) return;
    clearErrors();
    setFormError(null);

    try {
      const response = await createMutation.mutateAsync({
        ...values,
        id_rol: Number(values.id_rol),
        id_unidad: Number(values.id_unidad),
      });
      setCreatedUsuario(response);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        let hasMappedError = false;
        for (const [field, messages] of Object.entries(error.errors ?? {})) {
          const formField = validationFieldMap[field];
          const message = messages[0];
          if (!formField || !message) continue;

          setError(formField, { type: "server", message });
          hasMappedError = true;
        }

        setFormError(
          error.message ||
            (hasMappedError
              ? "Revisá los campos indicados."
              : "Los datos enviados no son válidos."),
        );
        return;
      }

      setFormError(
        error instanceof ApiError && error.status === 403
          ? "No tenés permisos para crear usuarios."
          : error instanceof Error
            ? error.message
            : "No se pudo crear el usuario. Intentá nuevamente.",
      );
    }
  }

  const catalogsReady =
    Boolean(rolesQuery.data?.length) &&
    Boolean(unidadesQuery.data?.length) &&
    !rolesQuery.isPending &&
    !unidadesQuery.isPending &&
    !rolesQuery.isError &&
    !unidadesQuery.isError;

  if (createdUsuario) {
    return (
      <section aria-labelledby="new-user-title" className="mx-auto w-full max-w-3xl space-y-5">
        <h1
          id="new-user-title"
          className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
        >
          Nuevo usuario
        </h1>
        <div className="space-y-4 rounded-lg border border-ui-border bg-surface p-5 text-foreground shadow-sm sm:p-6">
          <p role="status" aria-live="polite" className="font-semibold text-green-800 dark:text-green-300">
            {createdUsuario.message ?? "Usuario creado correctamente."}
          </p>
          <p>
            Se creó el usuario {createdUsuario.data.nombre} (
            {createdUsuario.data.mail}).
          </p>
          <Button
            variant="link"
            onClick={() => router.push("/usuarios")}
            className="min-h-11 rounded-md border border-ui-border px-4 py-2 font-semibold no-underline text-foreground hover:bg-row-alternate"
          >
            Volver al listado
          </Button>
        </div>
      </section>
    );
  }

  return (
    <section aria-labelledby="new-user-title" className="mx-auto w-full max-w-4xl space-y-5">
      <div>
        <h1
          id="new-user-title"
          className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
        >
          Nuevo usuario
        </h1>
        <div aria-hidden="true" className="mt-2 h-1 w-16 rounded-full bg-brand" />
      </div>

      <form
        noValidate
        onSubmit={handleSubmit(onSubmit)}
        aria-label="Formulario de creación de usuario"
        className="overflow-hidden rounded-lg border border-ui-border bg-surface text-foreground shadow-sm"
      >
        <div className="space-y-6 p-4 sm:p-6">
          {formError ? (
            <p role="alert" aria-live="assertive" className="text-sm text-red-700 dark:text-red-300">
              {formError}
            </p>
          ) : null}

          <div className="grid gap-5 md:grid-cols-2">
            <div>
              <Input
                id="usuario-mail"
                label="Correo electrónico *"
                icon={emailIcon}
                type="email"
                autoComplete="email"
                maxLength={150}
                required
                placeholder="nombre@unlpam.edu.ar"
                {...register("mail")}
                aria-invalid={Boolean(errors.mail)}
                aria-describedby={errors.mail ? "usuario-mail-error" : undefined}
              />
              <FieldError
                id="usuario-mail-error"
                message={errors.mail?.message}
              />
            </div>

            <div>
              <Input
                id="usuario-nombre"
                label="Nombre *"
                icon={userIcon}
                type="text"
                autoComplete="name"
                maxLength={100}
                required
                placeholder="Nombre del usuario"
                {...register("nombre")}
                aria-invalid={Boolean(errors.nombre)}
                aria-describedby={
                  errors.nombre ? "usuario-nombre-error" : undefined
                }
              />
              <FieldError
                id="usuario-nombre-error"
                message={errors.nombre?.message}
              />
            </div>

            <div>
              <Input
                id="usuario-password"
                label="Contraseña *"
                icon={passwordIcon}
                type="password"
                autoComplete="new-password"
                minLength={8}
                maxLength={255}
                required
                placeholder="Al menos 8 caracteres"
                {...register("password")}
                aria-invalid={Boolean(errors.password)}
                aria-describedby={
                  errors.password ? "usuario-password-error" : undefined
                }
              />
              <FieldError
                id="usuario-password-error"
                message={errors.password?.message}
              />
            </div>

            <div>
              <Select
                id="usuario-rol"
                label="Rol *"
                requiredMark
                required
                disabled={
                  rolesQuery.isPending ||
                  rolesQuery.isError ||
                  !rolesQuery.data?.length
                }
                {...register("id_rol")}
                aria-invalid={Boolean(errors.id_rol)}
                aria-describedby={
                  errors.id_rol ? "usuario-rol-error" : undefined
                }
              >
                <option value="">Seleccioná un rol</option>
                {(rolesQuery.data ?? []).map((rol) => (
                  <option key={rol.id_rol} value={rol.id_rol}>
                    {rol.nombre}
                  </option>
                ))}
              </Select>
              {rolesQuery.isPending ? (
                <p role="status" className="mt-1 text-sm">
                  Cargando roles...
                </p>
              ) : null}
              {rolesQuery.isError ? (
                <div className="mt-1 space-y-1">
                  <p role="alert" className="text-sm text-red-700 dark:text-red-300">
                    No se pudieron cargar los roles:{" "}
                    {getCatalogError(rolesQuery.error)}
                  </p>
                  <button
                    type="button"
                    onClick={() => void rolesQuery.refetch()}
                    className="text-sm font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                  >
                    Reintentar carga de roles
                  </button>
                </div>
              ) : null}
              <FieldError
                id="usuario-rol-error"
                message={errors.id_rol?.message}
              />
            </div>

            <div>
              <Select
                id="usuario-unidad"
                label="Unidad académica *"
                requiredMark
                required
                disabled={
                  unidadesQuery.isPending ||
                  unidadesQuery.isError ||
                  !unidadesQuery.data?.length
                }
                {...register("id_unidad")}
                aria-invalid={Boolean(errors.id_unidad)}
                aria-describedby={
                  errors.id_unidad ? "usuario-unidad-error" : undefined
                }
              >
                <option value="">Seleccioná una unidad académica</option>
                {(unidadesQuery.data ?? []).map((unidad) => (
                  <option
                    key={unidad.id_unidad_academica}
                    value={unidad.id_unidad_academica}
                  >
                    {unidad.sigla} — {unidad.nombre}
                  </option>
                ))}
              </Select>
              {unidadesQuery.isPending ? (
                <p role="status" className="mt-1 text-sm">
                  Cargando unidades académicas...
                </p>
              ) : null}
              {unidadesQuery.isError ? (
                <div className="mt-1 space-y-1">
                  <p role="alert" className="text-sm text-red-700 dark:text-red-300">
                    No se pudieron cargar las unidades:{" "}
                    {getCatalogError(unidadesQuery.error)}
                  </p>
                  <button
                    type="button"
                    onClick={() => void unidadesQuery.refetch()}
                    className="text-sm font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                  >
                    Reintentar carga de unidades
                  </button>
                </div>
              ) : null}
              <FieldError
                id="usuario-unidad-error"
                message={errors.id_unidad?.message}
              />
            </div>
          </div>

          <fieldset className="space-y-2 border-t border-ui-border pt-5">
            <legend className="mb-2 text-sm font-semibold">Estado y alcance</legend>
            <Checkbox
              label="Usuario activo"
              {...register("activo")}
              aria-invalid={Boolean(errors.activo)}
              aria-describedby={
                errors.activo ? "usuario-activo-error" : undefined
              }
            />
            <FieldError
              id="usuario-activo-error"
              message={errors.activo?.message}
            />
            <Checkbox
              label="Alcance global"
              {...register("alcance_global")}
              aria-invalid={Boolean(errors.alcance_global)}
              aria-describedby={
                errors.alcance_global ? "usuario-alcance-error" : undefined
              }
            />
            <FieldError
              id="usuario-alcance-error"
              message={errors.alcance_global?.message}
            />
          </fieldset>
        </div>

        <footer className="flex flex-col gap-3 border-t border-ui-border bg-page px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
          <button
            type="button"
            disabled={createMutation.isPending}
            onClick={() => router.push("/usuarios")}
            className="min-h-11 rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
          >
            Cancelar
          </button>
          <Button
            type="submit"
            loading={createMutation.isPending}
            disabled={!catalogsReady}
            className="!w-full sm:!w-auto"
          >
            {createMutation.isPending ? "Creando usuario..." : "Crear usuario"}
          </Button>
        </footer>
      </form>
    </section>
  );
}

export default function UsuarioForm() {
  const { can } = usePermissions();
  const searchParams = useSearchParams();
  const visitKey = searchParams.get("visit") ?? "direct";
  if (!can("usuarios:write")) return <AccessDenied />;

  return <UsuarioCreateForm key={visitKey} />;
}
