const TOKEN_STORAGE_KEY = "sgc_token";

export function getAuthToken(): string | null {
  if (typeof window === "undefined") return null;

  try {
    const token = window.localStorage.getItem(TOKEN_STORAGE_KEY);
    return token?.trim() ? token : null;
  } catch (error) {
    throw new Error("No se pudo leer el token de sesión.", { cause: error });
  }
}

export function saveAuthToken(token: string): void {
  try {
    if (typeof window === "undefined") {
      throw new Error("El almacenamiento local no está disponible.");
    }
    if (!token.trim()) {
      throw new Error("La API devolvió un token vacío.");
    }
    window.localStorage.setItem(TOKEN_STORAGE_KEY, token);
  } catch (error) {
    throw new Error("No se pudo guardar el token de sesión.", { cause: error });
  }
}

export function removeAuthToken(): void {
  if (typeof window === "undefined") return;

  try {
    window.localStorage.removeItem(TOKEN_STORAGE_KEY);
  } catch (error) {
    throw new Error("No se pudo borrar el token de sesión.", { cause: error });
  }
}
