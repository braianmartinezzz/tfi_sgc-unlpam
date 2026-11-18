type StepperProps = {
  currentStep: number;
  onStepChange: (step: number) => void;
  disabled?: boolean;
};

const steps = [
  "Datos básicos e instituciones",
  "Ficha y plazos",
  "Documentos y envío",
];

export default function Stepper({
  currentStep,
  onStepChange,
  disabled = false,
}: StepperProps) {
  return (
    <nav aria-label="Progreso de carga del convenio" className="mb-5">
      <ol className="flex gap-2 sm:gap-4">
        {steps.map((title, index) => {
          const step = index + 1;
          const isCurrent = currentStep === step;

          return (
            <li key={title} className="min-w-0 flex-1">
              <button
                type="button"
                aria-label={`Paso ${step}: ${title}`}
                aria-current={isCurrent ? "step" : undefined}
                disabled={disabled}
                onClick={() => onStepChange(step)}
                className={`flex min-h-11 w-full enabled:cursor-pointer items-center justify-center gap-2 rounded-md border px-2 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand sm:justify-start sm:px-3 ${
                  isCurrent
                    ? "border-brand bg-brand text-white"
                    : "border-ui-border bg-surface text-foreground/70"
                } disabled:cursor-not-allowed disabled:opacity-60`}
              >
                <span
                  aria-hidden="true"
                  className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs ${
                    isCurrent
                      ? "border-white/60"
                      : "border-ui-border"
                  }`}
                >
                  {step}
                </span>
                <span className="hidden truncate sm:inline">{title}</span>
              </button>
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
