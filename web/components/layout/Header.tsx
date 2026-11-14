"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/AuthContext";
import ThemeToggle from "@/components/layout/ThemeToggle";

export default function Header() {
  const { user, logout } = useAuth();
  const router = useRouter();
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  async function handleLogout() {
    if (isLoggingOut) return;
    setIsLoggingOut(true);
    try {
      await logout();
    } finally {
      router.replace("/login");
      setIsLoggingOut(false);
    }
  }

  return (
    <header className="flex flex-wrap items-center justify-between gap-3 border-b border-black/10 p-4 dark:border-white/10">
      <p className="font-semibold">Gestión de Convenios</p>
      <div className="flex flex-wrap items-center justify-end gap-3">
        <div className="text-right">
          <p className="text-sm font-medium">{user?.nombre}</p>
          <p className="text-xs text-slate-600 dark:text-slate-300">
            {user?.rol?.nombre ?? "Rol no asignado"}
          </p>
        </div>
        <button
          type="button"
          onClick={() => void handleLogout()}
          disabled={isLoggingOut}
          aria-busy={isLoggingOut}
          className="rounded-sm px-2 py-1 text-sm text-brand hover:text-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
        >
          {isLoggingOut ? "Cerrando sesión..." : "Cerrar sesión"}
        </button>
        <ThemeToggle />
      </div>
    </header>
  );
}
