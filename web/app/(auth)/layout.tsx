import type { ReactNode } from "react";

export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div
      className="relative flex min-h-screen flex-col overflow-hidden bg-linear-to-br from-page via-background to-row-alternate text-foreground"
    >
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 overflow-hidden"
      >
        <div className="absolute -left-40 top-[12%] h-[30rem] w-[30rem] rounded-full bg-foreground/5 blur-3xl" />
        <div className="absolute -right-36 top-[8%] h-[32rem] w-[32rem] rounded-full border-[2.5rem] border-foreground/10 blur-2xl" />
        <div className="absolute -bottom-48 left-[18%] h-[34rem] w-[68rem] rounded-[50%] border-[2rem] border-foreground/5 blur-2xl" />
      </div>
      <main className="relative z-10 flex flex-1 items-center justify-center px-4 py-10 sm:py-12">
        {children}
      </main>
      <footer className="relative z-10 px-4 pb-14 text-center text-sm leading-6 text-foreground sm:pb-16">
        <p>Acceso exclusivo para personal autorizado.</p>
        <p>UNLPam - Facultad de Ingeniería, 2026.</p>
      </footer>
    </div>
  );
}
