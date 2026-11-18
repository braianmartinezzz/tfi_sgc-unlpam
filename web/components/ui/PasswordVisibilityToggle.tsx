"use client";

interface PasswordVisibilityToggleProps {
  visible: boolean;
  onToggle: () => void;
}

export default function PasswordVisibilityToggle({
  visible,
  onToggle,
}: PasswordVisibilityToggleProps) {
  return (
    <button
      type="button"
      aria-pressed={visible}
      aria-label={visible ? "Ocultar contraseña" : "Mostrar contraseña"}
      onMouseDown={(event) => event.preventDefault()}
      onClick={onToggle}
      className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-sm text-foreground hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
    >
      <svg
        aria-hidden="true"
        className="h-[18px] w-[18px]"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
        <circle cx="12" cy="12" r="3" />
        {visible ? <path d="m3 3 18 18" /> : null}
      </svg>
    </button>
  );
}
