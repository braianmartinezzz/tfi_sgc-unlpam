"use client";

import { useEffect, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/AuthContext";

export default function AuthGuard({ children }: { children: ReactNode }) {
  const { status } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login");
    }
  }, [router, status]);

  if (status === "loading") {
    return (
      <div
        role="status"
        aria-live="polite"
        className="flex min-h-full flex-1 items-center justify-center p-6"
      >
        Verificando sesión...
      </div>
    );
  }
  if (status === "unauthenticated") return null;

  return children;
}
