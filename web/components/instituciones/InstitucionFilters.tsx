"use client";

import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import { filterControlInteractionClassName } from "@/components/ui/filter-control-styles";

interface InstitucionFiltersProps {
  buscar: string;
  activo: string;
  onBuscarChange: (value: string) => void;
  onActivoChange: (value: string) => void;
  onLimpiar: () => void;
}

export default function InstitucionFilters({
  buscar,
  activo,
  onBuscarChange,
  onActivoChange,
  onLimpiar,
}: InstitucionFiltersProps) {
  return (
    <section
      aria-label="Filtros de instituciones firmantes"
      className="rounded-lg border border-ui-border bg-surface p-4 text-foreground shadow-sm sm:p-5"
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Input
          id="institucion-buscar"
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
          placeholder="Razón social o CUIT"
          autoComplete="off"
        />
        <Select
          id="institucion-estado-filtro"
          label="Estado"
          className={`${filterControlInteractionClassName} enabled:cursor-pointer`}
          value={activo}
          onChange={(event) => onActivoChange(event.currentTarget.value)}
        >
          <option value="">Todos los estados</option>
          <option value="true">Activas</option>
          <option value="false">Inactivas</option>
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
