"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useState } from "react";
import { useForm, type FieldPath } from "react-hook-form";
import { useRouter } from "next/navigation";
import { z } from "zod";
import { usePermissions } from "@/context/PermissionContext";
import { useRoles } from "@/hooks/useRoles";
import { useUnidades } from "@/hooks/useUnidades";
import { useUpdateUsuario, useUsuario } from "@/hooks/useUsuarios";
import { ApiError } from "@/services/api-client";
import Button from "@/components/ui/Button";
import Checkbox from "@/components/ui/Checkbox";
import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import type { Usuario, UsuarioWrite } from "@/types/usuario";

const usuarioEditSchema = z.object({
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
    .max(255, "La contraseña no puede superar los 255 caracteres.")
    .refine(
      (value) => value.length === 0 || value.length >= 8,
      "La contraseña debe tener al menos 8 caracteres.",
    ),
  id_rol: z.string().regex(/^[1-9]\d*$/, "Seleccioná un rol."),
  id_unidad: z
    .string()
    .regex(/^[1-9]\d*$/, "Seleccioná una unidad académica."),
  activo: z.boolean(),
  alcance_global: z.boolean(),
});

type UsuarioEditValues = z.infer<typeof usuarioEditSchema>;

const validationFieldMap: Readonly<Record<string, FieldPath<UsuarioEditValues>>> =
  {
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

function AccessDenied() {
  return (
    <p
      role="alert"
      className="rounded-lg border border-ui-border bg-surface p-6 text-red-700 dark:text-red-300"
    >
      No tenés permisos para editar usuarios.
    </p>
  );
}

function UsuarioEditContent({ id }: { id: string }) {
  const usuarioQuery = useUsuario(id);

  if (usuarioQuery.isPending) {
    return (
      <p role="status" aria-live="polite" className="rounded-lg border border-ui-border bg-surface p-6">
        Cargando datos del usuario...
      </p>
    );
  }

  if (usuarioQuery.isError) {
    if (usuarioQuery.error instanceof ApiError && usuarioQuery.error.status === 403) {
      return <AccessDenied />;
    }

    if (usuarioQuery.error instanceof ApiError && usuarioQuery.error.status === 404) {
      return (
        <p role="alert" className="rounded-lg border border-ui-border bg-surface p-6">
          No se encontró el usuario solicitado.
        </p>
      );
    }

    return (
      <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6">
        <p role="alert" className="text-red-700 dark:text-red-300">
          No se pudieron cargar los datos del usuario:{" "}
          {usuarioQuery.error instanceof Error
            ? usuarioQuery.error.message
            : "Ocurrió un error inesperado."}
        </p>
        <button
          type="button"
          onClick={() => void usuarioQuery.refetch()}
          className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
        >
          Reintentar
        </button>
      </div>
    );
  }

  return <UsuarioEditCatalogs id={id} usuario={usuarioQuery.data} />;
}

function UsuarioEditCatalogs({
  id,
  usuario,
}: {
  id: string;
  usuario: Usuario;
}) {
  const rolesQuery = useRoles();
  const unidadesQuery = useUnidades();

  if (rolesQuery.isPending || unidadesQuery.isPending) {
    return (
      <p role="status" aria-live="polite" className="rounded-lg border border-ui-border bg-surface p-6">
        Cargando roles y unidades académicas...
      </p>
    );
  }

  if (rolesQuery.isError || unidadesQuery.isError) {
    const rolesForbidden =
      rolesQuery.isError &&
      rolesQuery.error instanceof ApiError &&
      rolesQuery.error.status === 403;
    const unidadesForbidden =
      unidadesQuery.isError &&
      unidadesQuery.error instanceof ApiError &&
      unidadesQuery.error.status === 403;

    if (rolesForbidden || unidadesForbidden) {
      return <AccessDenied />;
    }

    const errors = [
      rolesQuery.isError
        ? `roles: ${
            rolesQuery.error instanceof Error
              ? rolesQuery.error.message
              : "error inesperado"
          }`
        : null,
      unidadesQuery.isError
        ? `unidades académicas: ${
            unidadesQuery.error instanceof Error
              ? unidadesQuery.error.message
              : "error inesperado"
          }`
        : null,
    ].filter(Boolean);

    return (
      <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6">
        <p role="alert" className="text-red-700 dark:text-red-300">
          No se pudieron cargar los catálogos: {errors.join("; ")}.
        </p>
        {rolesQuery.isError ? (
          <button
            type="button"
            onClick={() => void rolesQuery.refetch()}
            className="mr-3 font-semibold text-brand-text underline underline-offset-2"
          >
            Reintentar roles
          </button>
        ) : null}
        {unidadesQuery.isError ? (
          <button
            type="button"
            onClick={() => void unidadesQuery.refetch()}
            className="font-semibold text-brand-text underline underline-offset-2"
          >
            Reintentar unidades
          </button>
        ) : null}
      </div>
    );
  }

  if (!rolesQuery.data?.length || !unidadesQuery.data?.length) {
    return (
      <p role="alert" className="rounded-lg border border-ui-border bg-surface p-6">
        No hay roles o unidades académicas disponibles para editar el usuario.
      </p>
    );
  }

  return (
    <UsuarioEditFields
      id={id}
      usuario={usuario}
      roles={rolesQuery.data ?? []}
      unidades={unidadesQuery.data ?? []}
    />
  );
}

function UsuarioEditFields({
  id,
  usuario,
  roles,
  unidades,
}: {
  id: string;
  usuario: Usuario;
  roles: { id_rol: number; nombre: string }[];
  unidades: {
    id_unidad_academica: number;
    sigla: string;
    nombre: string;
  }[];
}) {
  const router = useRouter();
  const updateMutation = useUpdateUsuario();
  const [formError, setFormError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const {
    clearErrors,
    formState: { errors },
    handleSubmit,
    register,
    setError,
  } = useForm<UsuarioEditValues>({
    resolver: zodResolver(usuarioEditSchema),
    mode: "onTouched",
    defaultValues: {
      mail: usuario.mail,
      nombre: usuario.nombre,
      password: "",
      id_rol: String(usuario.id_rol),
      id_unidad: String(usuario.id_unidad),
      activo: usuario.activo,
      alcance_global: usuario.alcance_global ?? false,
    },
  });

  async function onSubmit(values: UsuarioEditValues) {
    if (updateMutation.isPending) return;
    clearErrors();
    setFormError(null);
    setSuccessMessage(null);

    const input: Partial<UsuarioWrite> = {
      mail: values.mail,
      nombre: values.nombre,
      id_rol: Number(values.id_rol),
      id_unidad: Number(values.id_unidad),
      activo: values.activo,
      alcance_global: values.alcance_global,
    };

    if (values.password !== "") {
      input.password = values.password;
    }

    try {
      const response = await updateMutation.mutateAsync({ id, input });
      setSuccessMessage(
        response.message ?? "Usuario actualizado correctamente.",
      );
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        for (const [field, messages] of Object.entries(error.errors ?? {})) {
          const formField = validationFieldMap[field];
          const message = messages[0];
          if (formField && message) {
            setError(formField, { type: "server", message });
          }
        }
        setFormError(error.message || "Revisá los campos indicados.");
        return;
      }

      if (error instanceof ApiError && error.status === 403) {
        setFormError("No tenés permisos para editar este usuario.");
        return;
      }

      if (error instanceof ApiError && error.status === 404) {
        setFormError("No se encontró el usuario solicitado.");
        return;
      }

      setFormError(
        error instanceof ApiError && error.status === 409
          ? error.message
          : error instanceof Error
            ? error.message
            : "No se pudo actualizar el usuario. Intentá nuevamente.",
      );
    }
  }

  if (successMessage) {
    return (
      <div className="space-y-4 rounded-lg border border-ui-border bg-surface p-5 text-foreground shadow-sm sm:p-6">
        <p role="status" aria-live="polite" className="font-semibold text-green-800 dark:text-green-300">
          {successMessage}
        </p>
        <Link
          href={`/usuarios/${id}`}
          className="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
        >
          Volver al detalle
        </Link>
      </div>
    );
  }

  return (
    <form
      noValidate
      onSubmit={handleSubmit(onSubmit)}
      aria-label="Formulario de edición de usuario"
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
              id="usuario-edit-mail"
              label="Correo electrónico *"
              icon={emailIcon}
              type="email"
              autoComplete="email"
              maxLength={150}
              required
              {...register("mail")}
              aria-invalid={Boolean(errors.mail)}
              aria-describedby={errors.mail ? "usuario-edit-mail-error" : undefined}
            />
            <FieldError
              id="usuario-edit-mail-error"
              message={errors.mail?.message}
            />
          </div>

          <div>
            <Input
              id="usuario-edit-nombre"
              label="Nombre *"
              icon={userIcon}
              type="text"
              autoComplete="name"
              maxLength={100}
              required
              {...register("nombre")}
              aria-invalid={Boolean(errors.nombre)}
              aria-describedby={
                errors.nombre ? "usuario-edit-nombre-error" : undefined
              }
            />
            <FieldError
              id="usuario-edit-nombre-error"
              message={errors.nombre?.message}
            />
          </div>

          <div>
            <Input
              id="usuario-edit-password"
              label="Contraseña nueva (opcional)"
              icon={passwordIcon}
              type="password"
              autoComplete="new-password"
              minLength={8}
              maxLength={255}
              placeholder="Dejar vacío para conservar la actual"
              {...register("password")}
              aria-invalid={Boolean(errors.password)}
              aria-describedby={
                errors.password ? "usuario-edit-password-error" : undefined
              }
            />
            <FieldError
              id="usuario-edit-password-error"
              message={errors.password?.message}
            />
          </div>

          <div>
            <Select
              id="usuario-edit-rol"
              label="Rol"
              requiredMark
              floatingLabel
              required
              className="enabled:cursor-pointer"
              {...register("id_rol")}
              aria-invalid={Boolean(errors.id_rol)}
              aria-describedby={
                errors.id_rol ? "usuario-edit-rol-error" : undefined
              }
            >
              {roles.map((rol) => (
                <option key={rol.id_rol} value={rol.id_rol}>
                  {rol.nombre}
                </option>
              ))}
            </Select>
            <FieldError
              id="usuario-edit-rol-error"
              message={errors.id_rol?.message}
            />
          </div>

          <div>
            <Select
              id="usuario-edit-unidad"
              label="Unidad académica"
              requiredMark
              floatingLabel
              required
              className="enabled:cursor-pointer"
              {...register("id_unidad")}
              aria-invalid={Boolean(errors.id_unidad)}
              aria-describedby={
                errors.id_unidad ? "usuario-edit-unidad-error" : undefined
              }
            >
              {unidades.map((unidad) => (
                <option
                  key={unidad.id_unidad_academica}
                  value={unidad.id_unidad_academica}
                >
                  {unidad.sigla} — {unidad.nombre}
                </option>
              ))}
            </Select>
            <FieldError
              id="usuario-edit-unidad-error"
              message={errors.id_unidad?.message}
            />
          </div>
        </div>

        <fieldset className="space-y-2 border-t border-ui-border pt-5">
          <legend className="mb-2 text-sm font-semibold">Estado y alcance</legend>
          <Checkbox
            label="Usuario activo"
            className="cursor-pointer"
            labelClassName="cursor-pointer"
            {...register("activo")}
            aria-invalid={Boolean(errors.activo)}
            aria-describedby={
              errors.activo ? "usuario-edit-activo-error" : undefined
            }
          />
          <FieldError
            id="usuario-edit-activo-error"
            message={errors.activo?.message}
          />
          <Checkbox
            label="Alcance global"
            className="cursor-pointer"
            labelClassName="cursor-pointer"
            {...register("alcance_global")}
            aria-invalid={Boolean(errors.alcance_global)}
            aria-describedby={
              errors.alcance_global
                ? "usuario-edit-alcance-error"
                : undefined
            }
          />
          <FieldError
            id="usuario-edit-alcance-error"
            message={errors.alcance_global?.message}
          />
        </fieldset>
      </div>

      <footer className="flex flex-col gap-3 border-t border-ui-border bg-page px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
        <button
          type="button"
          disabled={updateMutation.isPending}
          onClick={() => router.push(`/usuarios/${id}`)}
          className="min-h-11 enabled:cursor-pointer rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
        >
          Cancelar
        </button>
        <Button
          type="submit"
          loading={updateMutation.isPending}
          className="!w-full enabled:cursor-pointer sm:!w-auto"
        >
          {updateMutation.isPending ? "Guardando cambios..." : "Guardar cambios"}
        </Button>
      </footer>
    </form>
  );
}

export default function UsuarioEditForm({ id }: { id: string }) {
  const { can } = usePermissions();

  return (
    <section aria-labelledby="usuario-edit-title" className="mx-auto w-full max-w-4xl space-y-5">
      <div>
        <h1
          id="usuario-edit-title"
          className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
        >
          Editar usuario
        </h1>
        <div aria-hidden="true" className="mt-2 h-1 w-16 rounded-full bg-brand" />
      </div>
      {can("usuarios:write") ? (
        <UsuarioEditContent id={id} />
      ) : (
        <AccessDenied />
      )}
    </section>
  );
}
