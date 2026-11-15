"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/AuthContext";

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
    <header className="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 bg-topbar px-4 py-3 text-white sm:px-6">
      <p className="text-2xl font-bold tracking-tight text-brand">UNLPam</p>
      <div className="ml-auto flex flex-wrap items-center justify-end gap-3">
        <p className="max-w-full break-words text-right text-sm font-medium sm:text-base">
          Usuario: {user?.nombre ?? "No disponible"} (
          {user?.rol?.nombre ?? "Rol no asignado"})
        </p>
        <button
          type="button"
          onClick={() => void handleLogout()}
          disabled={isLoggingOut}
          aria-busy={isLoggingOut}
          className="rounded-sm border border-white/30 px-3 py-2 text-sm font-medium text-white hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-topbar disabled:cursor-not-allowed disabled:opacity-70"
        >
          {isLoggingOut ? "Cerrando sesión..." : "Cerrar sesión"}
        </button>
      </div>
    </header>
  );
}
