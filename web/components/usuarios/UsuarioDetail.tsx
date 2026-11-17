"use client";

import Link from "next/link";
import UsuarioEditLink from "@/components/usuarios/UsuarioEditLink";
import { ApiError } from "@/services/api-client";
import { usePermissions } from "@/context/PermissionContext";
import { useUsuario } from "@/hooks/useUsuarios";
import type { Usuario } from "@/types/usuario";

function BackToUsersLink() {
  return (
    <Link
      href="/usuarios"
      className="inline-flex min-h-11 items-center justify-center rounded-md border border-ui-border bg-surface px-4 py-2 font-semibold text-foreground hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
    >
      Volver al listado
    </Link>
  );
}

function AccessDenied() {
  return (
    <p
      role="alert"
      className="rounded-lg border border-ui-border bg-surface p-6 text-red-700 dark:text-red-300"
    >
      No tenés permisos para consultar usuarios.
    </p>
  );
}

function DetailContent({ usuario }: { usuario: Usuario }) {
  const unidad = usuario.unidad_academica
    ? `${usuario.unidad_academica.sigla} — ${usuario.unidad_academica.nombre}`
    : "—";

  return (
    <div className="grid gap-5 lg:grid-cols-2">
      <section
        aria-labelledby="usuario-personal-title"
        className="rounded-lg border border-ui-border bg-surface p-5 text-foreground shadow-sm sm:p-6"
      >
        <h2 id="usuario-personal-title" className="text-xl font-bold">
          Información del usuario
        </h2>
        <dl className="mt-4 grid gap-4">
          <div>
            <dt className="text-sm font-semibold text-foreground/70">Nombre</dt>
            <dd className="mt-1 break-words">{usuario.nombre}</dd>
          </div>
          <div>
            <dt className="text-sm font-semibold text-foreground/70">
              Correo electrónico
            </dt>
            <dd className="mt-1 break-all">{usuario.mail}</dd>
          </div>
          <div>
            <dt className="text-sm font-semibold text-foreground/70">Estado</dt>
            <dd className="mt-1">
              <span
                className={`inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${
                  usuario.activo
                    ? "bg-emerald-100 text-emerald-950 dark:bg-emerald-900 dark:text-emerald-100"
                    : "bg-slate-200 text-slate-900 dark:bg-slate-700 dark:text-white"
                }`}
              >
                {usuario.activo ? "Activo" : "Inactivo"}
              </span>
            </dd>
          </div>
        </dl>
      </section>

      <section
        aria-labelledby="usuario-acceso-title"
        className="rounded-lg border border-ui-border bg-surface p-5 text-foreground shadow-sm sm:p-6"
      >
        <h2 id="usuario-acceso-title" className="text-xl font-bold">
          Información de acceso
        </h2>
        <dl className="mt-4 grid gap-4">
          <div>
            <dt className="text-sm font-semibold text-foreground/70">Rol</dt>
            <dd className="mt-1">{usuario.rol?.nombre ?? "—"}</dd>
          </div>
          <div>
            <dt className="text-sm font-semibold text-foreground/70">
              Unidad académica
            </dt>
            <dd className="mt-1">{unidad}</dd>
          </div>
          <div>
            <dt className="text-sm font-semibold text-foreground/70">
              Alcance global
            </dt>
            <dd className="mt-1">
              {usuario.alcance_global ? "Sí" : "No"}
            </dd>
          </div>
        </dl>
      </section>
    </div>
  );
}

function UsuarioDetailQuery({ id }: { id: string }) {
  const usuarioQuery = useUsuario(id);

  if (usuarioQuery.isPending) {
    return (
      <p role="status" aria-live="polite" className="rounded-lg border border-ui-border bg-surface p-6">
        Cargando datos del usuario...
      </p>
    );
  }

  if (usuarioQuery.isError) {
    if (usuarioQuery.error instanceof ApiError && usuarioQuery.error.status === 404) {
      return (
        <p
          role="alert"
          className="rounded-lg border border-ui-border bg-surface p-6 text-foreground"
        >
          No se encontró el usuario solicitado.
        </p>
      );
    }

    const message =
      usuarioQuery.error instanceof ApiError &&
      usuarioQuery.error.status === 403
        ? "No tenés permisos para consultar este usuario."
        : usuarioQuery.error instanceof Error
          ? usuarioQuery.error.message
          : "Ocurrió un error inesperado. Intentá nuevamente.";

    return (
      <div className="space-y-3 rounded-lg border border-ui-border bg-surface p-6 text-foreground">
        <p role="alert" className="text-red-700 dark:text-red-300">
          No se pudieron cargar los datos del usuario: {message}
        </p>
        {!(usuarioQuery.error instanceof ApiError && usuarioQuery.error.status === 403) ? (
          <button
            type="button"
            onClick={() => void usuarioQuery.refetch()}
            className="min-h-11 rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
          >
            Reintentar
          </button>
        ) : null}
      </div>
    );
  }

  return <DetailContent usuario={usuarioQuery.data} />;
}

export default function UsuarioDetail({ id }: { id: string }) {
  const { can } = usePermissions();

  return (
    <section aria-labelledby="usuario-detail-title" className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1
            id="usuario-detail-title"
            className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
          >
            Detalle del usuario
          </h1>
          <div aria-hidden="true" className="mt-2 h-1 w-16 rounded-full bg-brand" />
        </div>
        <div className="flex flex-wrap gap-3">
          {can("usuarios:write") ? (
            <UsuarioEditLink
              id={id}
              className="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
              Editar usuario
            </UsuarioEditLink>
          ) : null}
          <BackToUsersLink />
        </div>
      </div>
      {can("usuarios:write") ? (
        <UsuarioDetailQuery id={id} />
      ) : (
        <AccessDenied />
      )}
    </section>
  );
}
