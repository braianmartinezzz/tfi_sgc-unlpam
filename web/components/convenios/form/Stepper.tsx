type StepperProps = {
  currentStep: number;
  highestStep: number;
  onStepChange: (step: number) => void;
};

const steps = [
  "Datos básicos e instituciones",
  "Ficha y plazos",
  "Documentos y envío",
];

export default function Stepper({
  currentStep,
  highestStep,
  onStepChange,
}: StepperProps) {
  return (
    <nav aria-label="Progreso de carga del convenio" className="mb-5">
      <ol className="flex gap-2 sm:gap-4">
        {steps.map((title, index) => {
          const step = index + 1;
          const isCurrent = currentStep === step;
          const isComplete = step < currentStep;
          const isAvailable = step <= highestStep;

          return (
            <li key={title} className="min-w-0 flex-1">
              <button
                type="button"
                aria-label={`Paso ${step}: ${title}`}
                aria-current={isCurrent ? "step" : undefined}
                disabled={!isAvailable}
                onClick={() => onStepChange(step)}
                className={`flex min-h-11 w-full items-center justify-center gap-2 rounded-md border px-2 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand sm:justify-start sm:px-3 ${
                  isCurrent
                    ? "border-brand bg-brand text-brand-text"
                    : isComplete
                      ? "border-brand/50 bg-surface text-brand-text"
                      : "border-ui-border bg-surface text-foreground/70"
                } disabled:cursor-not-allowed`}
              >
                <span
                  aria-hidden="true"
                  className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs ${
                    isCurrent
                      ? "border-brand-text/60"
                      : isComplete
                        ? "border-brand/50"
                        : "border-ui-border"
                  }`}
                >
                  {isComplete ? (
                    <svg viewBox="0 0 20 20" fill="none" className="h-4 w-4">
                      <path
                        d="m4 10 4 4 8-8"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      />
                    </svg>
                  ) : (
                    step
                  )}
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
