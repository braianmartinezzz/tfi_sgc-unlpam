"use client";

import { useEffect, useId, useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useRouter } from "next/navigation";
import { z } from "zod";
import { useAuth } from "@/context/AuthContext";
import { ApiError } from "@/services/api-client";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import PasswordVisibilityToggle from "@/components/ui/PasswordVisibilityToggle";

const loginSchema = z.object({
  mail: z
    .string()
    .min(1, "El correo electrónico es obligatorio.")
    .email("Ingresá un correo electrónico válido."),
  password: z.string().min(1, "La contraseña es obligatoria."),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export default function LoginForm() {
  const { status, authError, login } = useAuth();
  const router = useRouter();
  const id = useId();
  const mailId = `${id}-mail`;
  const passwordId = `${id}-password`;
  const mailErrorId = `${mailId}-error`;
  const passwordErrorId = `${passwordId}-error`;
  const [passwordVisible, setPasswordVisible] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const {
    clearErrors,
    formState: { errors, isSubmitting },
    handleSubmit,
    register,
    setError,
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
  });

  useEffect(() => {
    if (status === "authenticated") router.replace("/convenios");
  }, [router, status]);

  async function onSubmit(credentials: LoginFormValues) {
    clearErrors();
    setFormError(null);
    try {
      await login(credentials);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        const mailMessage = error.errors?.mail?.[0];
        const passwordMessage = error.errors?.password?.[0];
        if (mailMessage) {
          setError("mail", { type: "server", message: mailMessage });
        }
        if (passwordMessage) {
          setError("password", { type: "server", message: passwordMessage });
        }
        if (!mailMessage && !passwordMessage) {
          setFormError(error.message);
        }
        return;
      }

      if (error instanceof ApiError) {
        setFormError(
          error.status === 401
            ? "Credenciales inválidas."
            : error.status === 0
              ? "No se pudo conectar con el servidor. Intentá nuevamente."
              : error.message,
        );
        return;
      }

      setFormError(
        error instanceof Error
          ? error.message
          : "No se pudo iniciar sesión. Intentá nuevamente.",
      );
    }
  }

  function handleForgotPassword() {
    // TODO: pendiente de endpoint de recuperación
  }

  if (status === "loading") {
    return (
      <p role="status" aria-live="polite" className="mt-6 text-center">
        Verificando sesión...
      </p>
    );
  }

  return (
    <form
      noValidate
      onSubmit={handleSubmit(onSubmit)}
      className="mt-6 space-y-5"
    >
      {formError || authError ? (
        <p role="alert" className="text-sm text-brand-text">
          {formError ?? authError}
        </p>
      ) : null}
      <Input
        label="Correo Electrónico"
        id={mailId}
        icon={
          <svg
            className="h-[18px] w-[18px]"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path d="M3 5.75A1.75 1.75 0 0 1 4.75 4h14.5A1.75 1.75 0 0 1 21 5.75v12.5A1.75 1.75 0 0 1 19.25 20H4.75A1.75 1.75 0 0 1 3 18.25V5.75Zm2 .2v.3l7 5.25 7-5.25v-.3H5Zm14 2.8-6.4 4.8a1 1 0 0 1-1.2 0L5 8.75v9.5h14v-9.5Z" />
          </svg>
        }
        {...register("mail")}
        type="email"
        autoComplete="username"
        placeholder="nombre@unlpam.edu.ar"
        aria-invalid={Boolean(errors.mail)}
        aria-describedby={errors.mail ? mailErrorId : undefined}
      />
      {errors.mail ? (
        <p id={mailErrorId} role="alert" className="-mt-4 text-sm text-brand-text">
          {errors.mail.message}
        </p>
      ) : null}
      <Input
        label="Contraseña"
        id={passwordId}
        icon={
          <svg
            className="h-[18px] w-[18px]"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2ZM10 6a2 2 0 1 1 4 0v2h-4V6Zm2 11a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z" />
          </svg>
        }
        {...register("password")}
        type={passwordVisible ? "text" : "password"}
        className="[&::-ms-reveal]:hidden"
        autoComplete="current-password"
        placeholder="••••••••"
        aria-invalid={Boolean(errors.password)}
        aria-describedby={errors.password ? passwordErrorId : undefined}
        endAdornment={
          <PasswordVisibilityToggle
            visible={passwordVisible}
            onToggle={() => setPasswordVisible((visible) => !visible)}
          />
        }
      />
      {errors.password ? (
        <p
          id={passwordErrorId}
          role="alert"
          className="-mt-4 text-sm text-brand-text"
        >
          {errors.password.message}
        </p>
      ) : null}
      <div className="space-y-3 pt-1 text-center">
        <Button
          type="submit"
          loading={isSubmitting}
          className="enabled:cursor-pointer"
        >
          Iniciar Sesión
        </Button>
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
