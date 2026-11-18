"use client";

import { useTheme } from "@/context/ThemeContext";

export default function ThemeToggle({
  variant = "topbar",
}: {
  variant?: "topbar" | "surface";
}) {
  const { toggleTheme } = useTheme();
  const colorClassName =
    variant === "surface"
      ? "border-ui-border text-foreground hover:bg-row-alternate focus-visible:ring-brand focus-visible:ring-offset-background"
      : "border-white/30 text-white hover:bg-white/10 focus-visible:ring-white focus-visible:ring-offset-topbar";

  return (
    <button
      type="button"
      onClick={toggleTheme}
      className={`inline-flex min-h-11 min-w-11 shrink-0 cursor-pointer items-center justify-center rounded-md border focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ${colorClassName}`}
    >
      <span className="theme-toggle-label-dark sr-only">
        Cambiar a modo oscuro
      </span>
      <span className="theme-toggle-label-light sr-only">
        Cambiar a modo claro
      </span>
      <svg
        aria-hidden="true"
        className="theme-toggle-sun h-5 w-5"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
      >
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" />
      </svg>
      <svg
        aria-hidden="true"
        className="theme-toggle-moon h-5 w-5"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <path d="M20.5 15.5A8.5 8.5 0 0 1 8.5 3.5 8.5 8.5 0 1 0 20.5 15.5Z" />
      </svg>
    </button>
  );
}
