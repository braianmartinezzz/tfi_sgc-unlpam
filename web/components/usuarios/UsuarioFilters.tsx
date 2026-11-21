"use client";

import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import { filterControlInteractionClassName } from "@/components/ui/filter-control-styles";
import type { Rol } from "@/types/auth";
import type { UnidadAcademica } from "@/types/unidad";

interface UsuarioFiltersProps {
  buscar: string;
  rol: string;
  unidad: string;
  estado: string;
  roles: Rol[];
  unidades: UnidadAcademica[];
  rolesCargando: boolean;
  unidadesCargando: boolean;
  errorRoles: string | null;
  errorUnidades: string | null;
  onBuscarChange: (value: string) => void;
  onRolChange: (value: string) => void;
  onUnidadChange: (value: string) => void;
  onEstadoChange: (value: string) => void;
  onLimpiar: () => void;
  onReintentarRoles: () => void;
  onReintentarUnidades: () => void;
}

function RetryMessage({
  message,
  onRetry,
  retryLabel,
}: {
  message: string;
  onRetry: () => void;
  retryLabel: string;
}) {
  return (
    <div className="mt-1 space-y-1">
      <p role="alert" className="text-sm text-red-700 dark:text-red-300">
        {message}
      </p>
      <button
        type="button"
        onClick={onRetry}
        className="text-sm font-semibold text-brand-text underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
      >
        {retryLabel}
      </button>
    </div>
  );
}

export default function UsuarioFilters({
  buscar,
  rol,
  unidad,
  estado,
  roles,
  unidades,
  rolesCargando,
  unidadesCargando,
  errorRoles,
  errorUnidades,
  onBuscarChange,
  onRolChange,
  onUnidadChange,
  onEstadoChange,
  onLimpiar,
  onReintentarRoles,
  onReintentarUnidades,
}: UsuarioFiltersProps) {
  return (
    <section
      aria-label="Filtros de usuarios"
      className="rounded-lg border border-ui-border bg-surface p-4 text-foreground shadow-sm sm:p-5"
    >
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div>
          <Input
            id="usuario-buscar"
            label="Búsqueda"
            floatingLabel={false}
            className={filterControlInteractionClassName}
            icon={
              <svg
                aria-hidden="true"
                className="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-4-4" />
              </svg>
            }
            type="search"
            value={buscar}
            onChange={(event) => onBuscarChange(event.currentTarget.value)}
            placeholder="Nombre o correo electrónico"
            autoComplete="off"
          />
          <p className="mt-1 text-xs text-foreground/70">
            La búsqueda disponible usa nombre y correo electrónico.
          </p>
        </div>
        <div>
          <Select
            id="usuario-rol"
            label="Rol"
            className={`${filterControlInteractionClassName} enabled:cursor-pointer`}
            value={rol}
            onChange={(event) => onRolChange(event.currentTarget.value)}
            disabled={rolesCargando || Boolean(errorRoles)}
          >
            <option value="">Todos los roles</option>
            {roles.map((opcion) => (
              <option key={opcion.id_rol} value={opcion.id_rol}>
                {opcion.nombre}
              </option>
            ))}
          </Select>
          {rolesCargando ? (
            <p role="status" className="mt-1 text-sm">
              Cargando roles...
            </p>
          ) : null}
          {errorRoles ? (
            <RetryMessage
              message={errorRoles}
              onRetry={onReintentarRoles}
              retryLabel="Reintentar carga de roles"
            />
          ) : null}
        </div>
        <div>
          <Select
            id="usuario-unidad"
            label="Unidad académica"
            className={`${filterControlInteractionClassName} enabled:cursor-pointer`}
            value={unidad}
            onChange={(event) => onUnidadChange(event.currentTarget.value)}
            disabled={unidadesCargando || Boolean(errorUnidades)}
          >
            <option value="">Todas las unidades</option>
            {unidades.map((opcion) => (
              <option
                key={opcion.id_unidad_academica}
                value={opcion.id_unidad_academica}
              >
                {opcion.sigla} — {opcion.nombre}
                {!opcion.activo ? " (inactiva)" : ""}
              </option>
            ))}
          </Select>
          {unidadesCargando ? (
            <p role="status" className="mt-1 text-sm">
              Cargando unidades académicas...
            </p>
          ) : null}
          {errorUnidades ? (
            <RetryMessage
              message={errorUnidades}
              onRetry={onReintentarUnidades}
              retryLabel="Reintentar carga de unidades"
            />
          ) : null}
        </div>
        <Select
          id="usuario-estado"
          label="Estado"
          className={`${filterControlInteractionClassName} enabled:cursor-pointer`}
          value={estado}
          onChange={(event) => onEstadoChange(event.currentTarget.value)}
        >
          <option value="">Todos los estados</option>
          <option value="true">Activo</option>
          <option value="false">Inactivo</option>
        </Select>
      </div>
      <div className="mt-4 flex justify-end">
        <Button
          variant="link"
          onClick={onLimpiar}
          className="min-h-11 cursor-pointer !rounded-md border border-ui-border px-4 py-2 font-semibold !no-underline text-foreground hover:bg-row-alternate"
        >
          Limpiar filtros
        </Button>
      </div>
    </section>
  );
}
