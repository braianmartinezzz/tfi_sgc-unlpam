"use client";

import { useState } from "react";
import { useWatch, type UseFormReturn } from "react-hook-form";
import Checkbox from "@/components/ui/Checkbox";
import Input from "@/components/ui/Input";
import Textarea from "@/components/ui/Textarea";
import type { ConvenioFormValues } from "@/components/convenios/form/schema";

type StepFichaPlazosProps = {
  form: UseFormReturn<ConvenioFormValues>;
};

const textIcon = (
  <svg viewBox="0 0 24 24" fill="currentColor" className="h-[18px] w-[18px]">
    <path d="M5 3h14v2H5V3Zm0 5h14v2H5V8Zm0 5h9v2H5v-2Zm0 5h9v2H5v-2Z" />
  </svg>
);

const calendarIcon = (
  <svg viewBox="0 0 24 24" fill="currentColor" className="h-[18px] w-[18px]">
    <path d="M7 2h2v2h6V2h2v2h3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h3V2Zm13 8H4v10h16V10ZM4 8h16V6H4v2Z" />
  </svg>
);

const numberIcon = (
  <svg viewBox="0 0 24 24" fill="currentColor" className="h-[18px] w-[18px]">
    <path d="M9 3h2v5h2V3h2v5h4v2h-4v4h4v2h-4v5h-2v-5h-2v5H9v-5H5v-2h4v-4H5V8h4V3Zm2 7v4h2v-4h-2Z" />
  </svg>
);

function calculateEndDate(startDate: string, months: number): string {
  const [year, month, day] = startDate.split("-").map(Number);
  const targetMonth = month - 1 + months;
  const lastDay = new Date(Date.UTC(year, targetMonth + 1, 0)).getUTCDate();
  const date = new Date(
    Date.UTC(year, targetMonth, Math.min(day, lastDay)),
  );

  return [
    date.getUTCFullYear(),
    String(date.getUTCMonth() + 1).padStart(2, "0"),
    String(date.getUTCDate()).padStart(2, "0"),
  ].join("-");
}

export default function StepFichaPlazos({ form }: StepFichaPlazosProps) {
  const [duracionMeses, setDuracionMeses] = useState("");
  const {
    control,
    formState: { errors },
    register,
    setValue,
  } = form;
  const fechaInicio = useWatch({ control, name: "fechaInicio" });
  const fechaInicioRegistration = register("fechaInicio");
  const fechaVencimientoRegistration = register("fechaVencimiento");

  function recalcularVencimiento(inicio: string, meses: string) {
    const cantidad = Number(meses);
    if (inicio && Number.isInteger(cantidad) && cantidad > 0) {
      setValue("fechaVencimiento", calculateEndDate(inicio, cantidad), {
        shouldDirty: true,
        shouldValidate: true,
      });
    } else {
      setValue("fechaVencimiento", "", {
        shouldDirty: true,
        shouldValidate: true,
      });
    }
  }

  return (
    <section aria-labelledby="form-step-2-title" className="space-y-6">
      <div>
        <h2
          id="form-step-2-title"
          tabIndex={-1}
          className="text-xl font-bold outline-none focus-visible:ring-2 focus-visible:ring-brand"
        >
          Ficha y plazos
        </h2>
        <p className="mt-1 text-sm text-foreground/70">
          Estos datos son opcionales y se pueden completar más adelante.
        </p>
      </div>

      <div className="grid gap-5 md:grid-cols-2">
        <div>
          <Input
            label="Número de expediente"
            icon={textIcon}
            maxLength={100}
            placeholder="Ej.: EXP-2026-001"
            {...register("expediente")}
            aria-invalid={Boolean(errors.expediente)}
            aria-describedby={errors.expediente ? "expediente-error" : undefined}
          />
          {errors.expediente ? (
            <p
              id="expediente-error"
              role="alert"
              className="mt-1 text-sm text-brand-text"
            >
              {errors.expediente.message}
            </p>
          ) : null}
        </div>
        <div>
          <Input
            label="Número de resolución del Consejo Superior"
            icon={textIcon}
            maxLength={100}
            placeholder="Ej.: Resolución 123/2026"
            {...register("resolucionConsejoSuperior")}
            aria-invalid={Boolean(errors.resolucionConsejoSuperior)}
            aria-describedby={
              errors.resolucionConsejoSuperior
                ? "resolucion-error"
                : undefined
            }
          />
          {errors.resolucionConsejoSuperior ? (
            <p
              id="resolucion-error"
              role="alert"
              className="mt-1 text-sm text-brand-text"
            >
              {errors.resolucionConsejoSuperior.message}
            </p>
          ) : null}
        </div>
      </div>

      <div className="grid gap-5 md:grid-cols-2">
        <Textarea label="Objeto" rows={4} {...register("objeto")} />
        <Textarea label="Alcance" rows={4} {...register("alcance")} />
        <Textarea
          label="Obligaciones de las partes"
          rows={4}
          {...register("obligaciones")}
        />
        <Textarea label="Marco legal" rows={4} {...register("marcoLegal")} />
        <Textarea
          label="Condiciones de rescisión"
          rows={4}
          {...register("condicionesRescision")}
        />
        <Textarea
          label="Condición de renovación"
          rows={4}
          {...register("condicionRenovacion")}
        />
      </div>

      <Checkbox label="El convenio incluye cláusula de confidencialidad" {...register("confidencialidad")} />

      <div className="grid gap-5 md:grid-cols-2">
        <div>
          <Input
            label="Fecha de inicio"
            icon={calendarIcon}
            type="date"
            {...fechaInicioRegistration}
            aria-invalid={Boolean(errors.fechaInicio)}
            aria-describedby={errors.fechaInicio ? "inicio-error" : undefined}
            onChange={(event) => {
              fechaInicioRegistration.onChange(event);
              if (duracionMeses) {
                recalcularVencimiento(event.currentTarget.value, duracionMeses);
              }
            }}
          />
          {errors.fechaInicio ? (
            <p id="inicio-error" role="alert" className="mt-1 text-sm text-brand-text">
              {errors.fechaInicio.message}
            </p>
          ) : null}
        </div>

        <div>
          <Input
            label="Fecha de vencimiento"
            icon={calendarIcon}
            type="date"
            min={fechaInicio || undefined}
            {...fechaVencimientoRegistration}
            aria-invalid={Boolean(errors.fechaVencimiento)}
            aria-describedby={
              errors.fechaVencimiento
                ? "vencimiento-ayuda vencimiento-error"
                : "vencimiento-ayuda"
            }
            onChange={(event) => {
              fechaVencimientoRegistration.onChange(event);
              setDuracionMeses("");
            }}
          />
          <p id="vencimiento-ayuda" className="mt-1 text-sm text-foreground/70">
            Si queda vacío, el vencimiento se considera indefinido.
          </p>
          {errors.fechaVencimiento ? (
            <p
              id="vencimiento-error"
              role="alert"
              className="mt-1 text-sm text-brand-text"
            >
              {errors.fechaVencimiento.message}
            </p>
          ) : null}
        </div>

        <div>
          <Input
            label="Duración (meses)"
            icon={numberIcon}
            type="number"
            min={1}
            step={1}
            value={duracionMeses}
            onChange={(event) => {
              const value = event.currentTarget.value;
              setDuracionMeses(value);
              recalcularVencimiento(fechaInicio, value);
            }}
            aria-describedby="duracion-ayuda"
          />
          <p id="duracion-ayuda" className="mt-1 text-sm text-foreground/70">
            Opcional. Calcula la fecha de vencimiento a partir del inicio.
          </p>
        </div>

        <div>
          <Input
            label="Días de preaviso"
            icon={numberIcon}
            type="number"
            min={0}
            step={1}
            {...register("diasPreaviso", {
              setValueAs: (value) => (value === "" ? "" : Number(value)),
            })}
            aria-invalid={Boolean(errors.diasPreaviso)}
            aria-describedby={errors.diasPreaviso ? "preaviso-error" : undefined}
          />
          {errors.diasPreaviso ? (
            <p
              id="preaviso-error"
              role="alert"
              className="mt-1 text-sm text-brand-text"
            >
              {errors.diasPreaviso.message}
            </p>
          ) : null}
        </div>
      </div>

      <Textarea label="Notas" rows={4} {...register("notas")} />
    </section>
  );
}
