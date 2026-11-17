"use client";

import { useEffect, useRef, useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter } from "next/navigation";
import { useForm, useWatch, type FieldPath } from "react-hook-form";
import ConfirmDialog from "@/components/feedback/ConfirmDialog";
import StepDatosBasicos from "@/components/convenios/form/StepDatosBasicos";
import StepDocumentos from "@/components/convenios/form/StepDocumentos";
import StepFichaPlazos from "@/components/convenios/form/StepFichaPlazos";
import Stepper from "@/components/convenios/form/Stepper";
import {
  currentUser,
  mockUnidadesAcademicas,
} from "@/components/convenios/form/mock-data";
import {
  convenioFormSchema,
  type ConvenioFormValues,
} from "@/components/convenios/form/schema";
import Button from "@/components/ui/Button";

const stepFields: Record<number, FieldPath<ConvenioFormValues>[]> = {
  1: [
    "claseInstrumento",
    "tipo",
    "titulo",
    "idUnidadAcademica",
    "idConvenioMarco",
    "institucionesFirmantes",
  ],
  2: [
    "expediente",
    "resolucionConsejoSuperior",
    "objeto",
    "alcance",
    "obligaciones",
    "marcoLegal",
    "condicionesRescision",
    "condicionRenovacion",
    "confidencialidad",
    "fechaInicio",
    "fechaVencimiento",
    "diasPreaviso",
    "notas",
  ],
  3: ["tituloDocumentoPrincipal", "archivoPrincipal", "anexos"],
};

export default function ConvenioForm() {
  const router = useRouter();
  const sendButtonRef = useRef<HTMLButtonElement>(null);
  const previousStep = useRef(1);
  const [currentStep, setCurrentStep] = useState(1);
  const [highestStep, setHighestStep] = useState(1);
  const [notice, setNotice] = useState<string | null>(null);
  const [confirmationOpen, setConfirmationOpen] = useState(false);
  const assignedUnit = mockUnidadesAcademicas.find(
    (unit) => unit.id_unidad_academica === currentUser.id_unidad,
  );
  const defaultInstitutions = assignedUnit
    ? [String(assignedUnit.id_institucion)]
    : [];
  const form = useForm<ConvenioFormValues>({
    resolver: zodResolver(convenioFormSchema),
    mode: "onTouched",
    defaultValues: {
      claseInstrumento: "",
      tipo: "",
      titulo: "",
      idUnidadAcademica: String(currentUser.id_unidad),
      idConvenioMarco: "",
      institucionesFirmantes: defaultInstitutions,
      archivoPrincipal: null,
      expediente: "",
      resolucionConsejoSuperior: "",
      objeto: "",
      alcance: "",
      obligaciones: "",
      marcoLegal: "",
      condicionesRescision: "",
      condicionRenovacion: "",
      confidencialidad: false,
      fechaInicio: "",
      fechaVencimiento: "",
      diasPreaviso: 30,
      notas: "",
      tituloDocumentoPrincipal: "",
      anexos: [],
    },
  });
  const {
    control,
    formState: { errors },
    getValues,
    setError,
    trigger,
  } = form;
  const titulo = useWatch({ control, name: "titulo" });
  const tipo = useWatch({ control, name: "tipo" });
  const idUnidadAcademica = useWatch({ control, name: "idUnidadAcademica" });
  const claseInstrumento = useWatch({ control, name: "claseInstrumento" });
  const idConvenioMarco = useWatch({ control, name: "idConvenioMarco" });
  const institucionesFirmantes = useWatch({
    control,
    name: "institucionesFirmantes",
  });
  const archivoPrincipal = useWatch({ control, name: "archivoPrincipal" });

  useEffect(() => {
    if (previousStep.current !== currentStep) {
      document
        .getElementById(`form-step-${currentStep}-title`)
        ?.focus();
      previousStep.current = currentStep;
    }
  }, [currentStep]);

  const readyToReview =
    titulo.trim() !== "" &&
    tipo !== "" &&
    idUnidadAcademica !== "" &&
    institucionesFirmantes.length >= 2 &&
    (claseInstrumento !== "Específico" || idConvenioMarco !== "") &&
    archivoPrincipal !== null;

  async function goToNextStep() {
    setNotice(null);
    if (!(await trigger(stepFields[currentStep]))) return;

    const nextStep = currentStep + 1;
    setCurrentStep(nextStep);
    setHighestStep((highest) => Math.max(highest, nextStep));
  }

  async function saveAndExit() {
    setNotice(null);
    if (!(await trigger(stepFields[1]))) {
      setCurrentStep(1);
      return;
    }

    setNotice(
      "Vista previa: todavía no está conectado a la API. No se guardaron los datos.",
    );
  }

  async function requestReview() {
    setNotice(null);
    const isValid = await trigger(stepFields[3]);
    if (!getValues("archivoPrincipal")) {
      setError("archivoPrincipal", {
        type: "required",
        message: "El documento principal PDF es obligatorio.",
      });
    }
    if (isValid && getValues("archivoPrincipal")) {
      setConfirmationOpen(true);
    }
  }

  function confirmReview() {
    setConfirmationOpen(false);
    setNotice(
      "Vista previa: todavía no está conectado a la API. No se envió el convenio.",
    );
  }

  function setStep(step: number) {
    if (step <= highestStep) {
      setNotice(null);
      setCurrentStep(step);
    }
  }

  return (
    <div className="mx-auto w-full max-w-5xl">
      <Stepper
        currentStep={currentStep}
        highestStep={highestStep}
        onStepChange={setStep}
      />

      <form
        noValidate
        aria-label="Formulario de convenio"
        onSubmit={(event) => event.preventDefault()}
        className="overflow-hidden rounded-lg border border-ui-border bg-surface text-foreground shadow-sm"
      >
        <div className="space-y-6 p-4 sm:p-6">
          {notice ? (
            <p
              role="status"
              aria-live="polite"
              className="rounded-md border border-ui-border bg-page px-4 py-3 text-sm"
            >
              {notice}
            </p>
          ) : null}

          {currentStep === 1 ? <StepDatosBasicos form={form} /> : null}
          {currentStep === 2 ? <StepFichaPlazos form={form} /> : null}
          {currentStep === 3 ? <StepDocumentos form={form} /> : null}
        </div>

        <footer className="flex flex-col gap-3 border-t border-ui-border bg-page px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <button
            type="button"
            onClick={() => router.push("/convenios")}
            className="min-h-11 rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand sm:w-auto"
          >
            Cancelar
          </button>

          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            {currentStep > 1 ? (
              <button
                type="button"
                onClick={() => setStep(currentStep - 1)}
                className="min-h-11 rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand sm:w-auto"
              >
                Anterior
              </button>
            ) : null}
            <button
              type="button"
              onClick={saveAndExit}
              className="min-h-11 rounded-sm border border-ui-border bg-surface px-4 py-2 text-sm font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand sm:w-auto"
            >
              Guardar y salir
            </button>
            {currentStep < 3 ? (
              <Button
                type="button"
                onClick={goToNextStep}
                className="!w-full sm:!w-auto"
              >
                Siguiente
              </Button>
            ) : (
              <div>
                <Button
                  ref={sendButtonRef}
                  type="button"
                  disabled={!readyToReview}
                  onClick={requestReview}
                  className="!w-full sm:!w-auto"
                >
                  Enviar a revisión
                </Button>
                {errors.archivoPrincipal ? (
                  <p className="sr-only" role="alert">
                    {errors.archivoPrincipal.message}
                  </p>
                ) : null}
              </div>
            )}
          </div>
        </footer>
      </form>

      <ConfirmDialog
        open={confirmationOpen}
        title="Enviar convenio a revisión"
        description="¿Confirmás el envío de este convenio a revisión? Esta vista previa no guardará ni enviará datos a la API."
        confirmLabel="Confirmar envío"
        loading={false}
        errorMessage={null}
        returnFocusRef={sendButtonRef}
        onConfirm={confirmReview}
        onCancel={() => setConfirmationOpen(false)}
      />
    </div>
  );
}
