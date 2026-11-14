import type { ApiError } from "@/types/api";

const TOKEN_STORAGE_KEY = "sgc-access-token";

export class ApiHttpError extends Error implements ApiError {
  readonly status: number;
  readonly errors?: Record<string, string[]>;

  constructor(status: number, payload: ApiError) {
    super(payload.message);
    this.name = "ApiHttpError";
    this.status = status;
    this.errors = payload.errors;
  }
}

interface ApiRequestOptions extends RequestInit {
  authenticated?: boolean;
}

function getApiBaseUrl(): string {
  const baseUrl = process.env.NEXT_PUBLIC_API_URL;
  if (!baseUrl) {
    throw new Error("Falta configurar NEXT_PUBLIC_API_URL.");
  }
  return baseUrl.replace(/\/+$/, "");
}

function readAccessToken(): string | null {
  if (typeof window === "undefined") return null;

  try {
    return window.localStorage.getItem(TOKEN_STORAGE_KEY);
  } catch (error) {
    throw new Error("No se pudo leer el token de sesión.", { cause: error });
  }
}

function isStringArrayRecord(
  value: unknown,
): value is Record<string, string[]> {
  if (typeof value !== "object" || value === null) return false;
  return Object.values(value).every(
    (entry) =>
      Array.isArray(entry) &&
      entry.every((message) => typeof message === "string"),
  );
}

async function toApiError(response: Response): Promise<ApiHttpError> {
  let payload: unknown;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  const record =
    typeof payload === "object" && payload !== null
      ? (payload as Record<string, unknown>)
      : null;
  const defaultMessage =
    response.status === 401
      ? "La sesión no está autorizada."
      : response.status === 409
        ? "La operación entra en conflicto con el estado actual."
        : response.status === 422
          ? "Los datos enviados no son válidos."
          : `Error de API (${response.status}).`;

  return new ApiHttpError(response.status, {
    message:
      record && typeof record.message === "string"
        ? record.message
        : defaultMessage,
    ...(record && isStringArrayRecord(record.errors)
      ? { errors: record.errors }
      : {}),
  });
}

export async function apiRequest<T>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  const { authenticated = true, headers: requestHeaders, ...requestOptions } =
    options;
  const headers = new Headers(requestHeaders);
  headers.set("Accept", "application/json");

  if (authenticated) {
    const token = readAccessToken();
    if (token) headers.set("Authorization", `Bearer ${token}`);
  }

  if (requestOptions.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const response = await fetch(`${getApiBaseUrl()}${path}`, {
    ...requestOptions,
    headers,
  });
  if (!response.ok) throw await toApiError(response);
  return (await response.json()) as T;
}

export async function apiDownload(path: string): Promise<void> {
  const headers = new Headers();
  const token = readAccessToken();
  if (token) headers.set("Authorization", `Bearer ${token}`);

  const response = await fetch(`${getApiBaseUrl()}${path}`, { headers });
  if (!response.ok) throw await toApiError(response);

  const contentDisposition = response.headers.get("Content-Disposition");
  const filename =
    contentDisposition?.match(/filename="?([^";]+)"?/i)?.[1] ??
    "convenios.csv";
  const objectUrl = URL.createObjectURL(await response.blob());
  const link = document.createElement("a");
  link.href = objectUrl;
  link.download = filename;
  document.body.append(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 0);
}
