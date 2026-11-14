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
import { ApiError, registerUnauthorizedHandler } from "@/services/api-client";
import * as authService from "@/services/auth";
import {
  getAuthToken,
  removeAuthToken,
  saveAuthToken,
} from "@/services/token-storage";
import type { LoginCredentials, UsuarioAutenticado } from "@/types/auth";

type AuthStatus = "loading" | "authenticated" | "unauthenticated";

interface AuthContextValue {
  status: AuthStatus;
  user: UsuarioAutenticado | null;
  authError: string | null;
  login: (credentials: LoginCredentials) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function getErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  return "No se pudo completar la operación de autenticación.";
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>("loading");
  const [user, setUser] = useState<UsuarioAutenticado | null>(null);
  const [authError, setAuthError] = useState<string | null>(null);

  const invalidateSession = useCallback(() => {
    let storageError: string | null = null;
    try {
      removeAuthToken();
    } catch (error) {
      storageError = getErrorMessage(error);
    }

    setUser(null);
    setStatus("unauthenticated");
    setAuthError(storageError);
  }, []);

  useEffect(
    () => registerUnauthorizedHandler(invalidateSession),
    [invalidateSession],
  );

  useEffect(() => {
    let active = true;

    async function restoreSession() {
      try {
        const token = getAuthToken();
        if (!token) {
          if (active) {
            setUser(null);
            setAuthError(null);
            setStatus("unauthenticated");
          }
          return;
        }

        const response = await authService.me();
        if (active) {
          setUser(response.user);
          setAuthError(null);
          setStatus("authenticated");
        }
      } catch (error) {
        if (!active) return;

        if (
          error instanceof ApiError &&
          (error.status === 401 || error.status === 403)
        ) {
          invalidateSession();
        } else {
          setUser(null);
          setAuthError(getErrorMessage(error));
          setStatus("unauthenticated");
        }
      }
    }

    void restoreSession();
    return () => {
      active = false;
    };
  }, [invalidateSession]);

  const login = useCallback(async (credentials: LoginCredentials) => {
    setAuthError(null);
    try {
      const response = await authService.login(
        credentials.mail,
        credentials.password,
      );
      saveAuthToken(response.access_token);
      setUser(response.user);
      setStatus("authenticated");
    } catch (error) {
      setUser(null);
      setStatus("unauthenticated");
      throw error;
    }
  }, []);

  const logout = useCallback(async () => {
    let logoutError: string | null = null;
    try {
      if (getAuthToken()) await authService.logout();
    } catch (error) {
      logoutError = getErrorMessage(error);
    } finally {
      try {
        removeAuthToken();
      } catch (error) {
        logoutError = getErrorMessage(error);
      }
      setUser(null);
      setStatus("unauthenticated");
      setAuthError(logoutError);
    }
  }, []);

  const value = useMemo(
    () => ({ status, user, authError, login, logout }),
    [status, user, authError, login, logout],
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
