"use client";

import { useEffect, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/AuthContext";

export default function AuthGuard({ children }: { children: ReactNode }) {
  const { token, user, isLoading, authError } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && !token && !authError) {
      router.replace("/login");
    }
  }, [authError, isLoading, router, token]);

  if (isLoading) {
    return <p role="status">Verificando sesión…</p>;
  }
  if (authError) {
    return <p role="alert">{authError}</p>;
  }
  if (!token || !user) return null;

  return children;
}
