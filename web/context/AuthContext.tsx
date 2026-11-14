"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { ApiHttpError } from "@/services/api-client";
import * as authService from "@/services/auth";
import type { LoginCredentials, UsuarioAutenticado } from "@/types/auth";

const TOKEN_STORAGE_KEY = "sgc-access-token";

interface AuthContextValue {
  token: string | null;
  user: UsuarioAutenticado | null;
  isLoading: boolean;
  authError: string | null;
  login: (credentials: LoginCredentials) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function readStoredToken(): string | null {
  try {
    return window.localStorage.getItem(TOKEN_STORAGE_KEY);
  } catch (error) {
    throw new Error("No se pudo leer el token de sesión.", { cause: error });
  }
}

function writeStoredToken(token: string | null): void {
  try {
    if (token) {
      window.localStorage.setItem(TOKEN_STORAGE_KEY, token);
    } else {
      window.localStorage.removeItem(TOKEN_STORAGE_KEY);
    }
  } catch (error) {
    throw new Error("No se pudo guardar el token de sesión.", { cause: error });
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<UsuarioAutenticado | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [authError, setAuthError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;

    async function restoreSession() {
      try {
        const storedToken = readStoredToken();
        if (!storedToken) {
          if (active) setIsLoading(false);
          return;
        }

        if (active) setToken(storedToken);
        const response = await authService.me();
        if (active) setUser(response.user);
      } catch (error) {
        if (error instanceof ApiHttpError && error.status === 401) {
          try {
            writeStoredToken(null);
          } catch (storageError) {
            if (active) {
              setAuthError(
                storageError instanceof Error
                  ? storageError.message
                  : "No se pudo limpiar el token de sesión.",
              );
            }
          }
          if (active) setToken(null);
        } else if (active) {
          setAuthError(
            error instanceof Error
              ? error.message
              : "No se pudo restaurar la sesión.",
          );
        }
      } finally {
        if (active) setIsLoading(false);
      }
    }

    void restoreSession();
    return () => {
      active = false;
    };
  }, []);

  const login = useCallback(async (credentials: LoginCredentials) => {
    setIsLoading(true);
    setAuthError(null);
    try {
      const response = await authService.login(credentials);
      writeStoredToken(response.access_token);
      setToken(response.access_token);
      setUser(response.user);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      if (token) await authService.logout();
    } finally {
      writeStoredToken(null);
      setToken(null);
      setUser(null);
      setAuthError(null);
    }
  }, [token]);

  const value = useMemo(
    () => ({ token, user, isLoading, authError, login, logout }),
    [token, user, isLoading, authError, login, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth debe usarse dentro de AuthProvider.");
  }
  return context;
}
