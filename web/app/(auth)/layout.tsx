import type { ReactNode } from "react";
import { Roboto_Condensed } from "next/font/google";

const robotoCondensed = Roboto_Condensed({
  subsets: ["latin"],
  weight: ["400", "500", "700"],
  display: "swap",
  fallback: ["Arial", "sans-serif"],
});

export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div
      className={`${robotoCondensed.className} relative flex min-h-screen flex-col overflow-hidden bg-linear-to-br from-white via-slate-50 to-slate-100 text-slate-900`}
    >
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 overflow-hidden"
      >
        <div className="absolute -left-40 top-[12%] h-[30rem] w-[30rem] rounded-full bg-slate-300/10 blur-3xl" />
        <div className="absolute -right-36 top-[8%] h-[32rem] w-[32rem] rounded-full border-[2.5rem] border-white/45 blur-2xl" />
        <div className="absolute -bottom-48 left-[18%] h-[34rem] w-[68rem] rounded-[50%] border-[2rem] border-slate-300/[0.08] blur-2xl" />
      </div>
      <main className="relative z-10 flex flex-1 items-center justify-center px-4 py-10 sm:py-12">
        {children}
      </main>
      <footer className="relative z-10 px-4 pb-14 text-center text-sm leading-6 text-slate-700 sm:pb-16">
        <p>Acceso exclusivo para personal autorizado.</p>
        <p>UNLPam - Facultad de Ingeniería, 2026.</p>
      </footer>
    </div>
  );
}
