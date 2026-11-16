import type { Metadata } from "next";
import LoginForm from "@/components/auth/LoginForm";

export const metadata: Metadata = {
  title: "UNLPam",
};

export default function LoginPage() {
  return (
    <section
      aria-labelledby="login-title"
      className="w-full max-w-[420px] overflow-hidden rounded-md border border-ui-border bg-surface text-foreground shadow-xl"
    >
      <div aria-hidden="true" className="h-[5px] w-full bg-brand" />
      <div className="px-6 pb-8 pt-7 sm:px-7">
        <header className="text-center">
          <p className="text-[2.65rem] font-bold leading-none tracking-tight text-brand-text">
            UNLPam
          </p>
          <p className="mt-1 text-xs font-medium text-foreground">
            Sistema de Gestión de Convenios
          </p>
          <h1
            id="login-title"
            className="mt-6 text-[1.65rem] font-medium leading-tight text-foreground"
          >
            Sistema de Gestión de Convenios
          </h1>
          <h2 className="mt-1 text-xl leading-tight text-foreground">
            Iniciar Sesión
          </h2>
        </header>
        <LoginForm />
      </div>
    </section>
  );
}
