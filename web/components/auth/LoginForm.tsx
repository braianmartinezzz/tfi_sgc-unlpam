"use client";

import { useState, type FormEvent } from "react";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";

export default function LoginForm() {
  const [passwordVisible, setPasswordVisible] = useState(false);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    // TODO: conectar con AuthContext.login
  }

  function handleForgotPassword() {
    // TODO: pendiente de endpoint de recuperación
  }

  return (
    <form onSubmit={handleSubmit} className="mt-6 space-y-5">
      <Input
        label="Correo Electrónico"
        icon={
          <svg
            className="h-[18px] w-[18px]"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path d="M3 5.75A1.75 1.75 0 0 1 4.75 4h14.5A1.75 1.75 0 0 1 21 5.75v12.5A1.75 1.75 0 0 1 19.25 20H4.75A1.75 1.75 0 0 1 3 18.25V5.75Zm2 .2v.3l7 5.25 7-5.25v-.3H5Zm14 2.8-6.4 4.8a1 1 0 0 1-1.2 0L5 8.75v9.5h14v-9.5Z" />
          </svg>
        }
        name="mail"
        type="email"
        autoComplete="username"
        placeholder="nombre@unlpam.edu.ar"
      />
      <Input
        label="Contraseña"
        icon={
          <svg
            className="h-[18px] w-[18px]"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2ZM10 6a2 2 0 1 1 4 0v2h-4V6Zm2 11a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z" />
          </svg>
        }
        name="password"
        type={passwordVisible ? "text" : "password"}
        autoComplete="current-password"
        placeholder="••••••••"
        endAdornment={
          <button
            type="button"
            aria-pressed={passwordVisible}
            aria-label={
              passwordVisible ? "Ocultar contraseña" : "Mostrar contraseña"
            }
            onClick={() => setPasswordVisible((visible) => !visible)}
            className="inline-flex items-center gap-1 rounded-sm px-1 py-1 text-xs text-slate-600 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
          >
            <span aria-hidden="true">ver contraseña</span>
            <svg
              aria-hidden="true"
              className="h-[18px] w-[18px]"
              viewBox="0 0 24 24"
              fill="currentColor"
            >
              <path d="M12 5c-5.2 0-9.4 3.2-11 7 1.6 3.8 5.8 7 11 7s9.4-3.2 11-7c-1.6-3.8-5.8-7-11-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-2.2a2.8 2.8 0 1 0 0-5.6 2.8 2.8 0 0 0 0 5.6Z" />
            </svg>
          </button>
        }
      />
      <div className="space-y-3 pt-1 text-center">
        <Button type="submit">Iniciar Sesión</Button>
        <Button
          type="button"
          variant="link"
          onClick={handleForgotPassword}
        >
          Olvidé mi contraseña
        </Button>
      </div>
    </form>
  );
}
