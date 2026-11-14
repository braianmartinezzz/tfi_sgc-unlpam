import type { ApiError as ApiErrorPayload } from "@/types/api";
import { getAuthToken } from "@/services/token-storage";

export class ApiError extends Error implements ApiErrorPayload {
  readonly status: number;
  readonly errors?: Record<string, string[]>;

  constructor(
    status: number,
    message: string,
    errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

export { ApiError as ApiHttpError };

interface ApiRequestOptions extends RequestInit {
  authenticated?: boolean;
}

type UnauthorizedHandler = () => void;

let unauthorizedHandler: UnauthorizedHandler | null = null;

export function registerUnauthorizedHandler(
  handler: UnauthorizedHandler,
): () => void {
  unauthorizedHandler = handler;
  return () => {
    if (unauthorizedHandler === handler) unauthorizedHandler = null;
  };
}

function getApiBaseUrl(): string {
  const baseUrl = process.env.NEXT_PUBLIC_API_URL?.trim();
  if (!baseUrl) {
    throw new ApiError(0, "Falta configurar NEXT_PUBLIC_API_URL.");
  }
  return baseUrl.replace(/\/+$/, "");
}

function isLoginRequest(path: string): boolean {
  return path.split("?")[0].replace(/\/+$/, "") === "/login";
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function isStringArrayRecord(
  value: unknown,
): value is Record<string, string[]> {
  if (!isRecord(value)) return false;
  return Object.values(value).every(
    (entry) =>
      Array.isArray(entry) &&
      entry.every((message) => typeof message === "string"),
  );
}

async function toApiError(response: Response): Promise<ApiError> {
  let payload: unknown;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  const record = isRecord(payload) ? payload : null;
  const defaultMessage =
    response.status === 401
      ? "La sesión no está autorizada."
      : response.status === 422
        ? "Los datos enviados no son válidos."
        : `Error de API (${response.status}).`;

  return new ApiError(
    response.status,
    record && typeof record.message === "string"
      ? record.message
      : defaultMessage,
    record && isStringArrayRecord(record.errors) ? record.errors : undefined,
  );
}

function createRequestHeaders(
  requestHeaders: HeadersInit | undefined,
  body: BodyInit | null | undefined,
  authenticated: boolean,
): Headers {
  const headers = new Headers(requestHeaders);
  headers.set("Accept", "application/json");

  if (body !== undefined && body !== null) {
    headers.set("Content-Type", "application/json");
  }

  if (authenticated) {
    const token = getAuthToken();
    if (token) headers.set("Authorization", `Bearer ${token}`);
  }

  return headers;
}

async function fetchApi(
  path: string,
  options: ApiRequestOptions = {},
): Promise<Response> {
  const {
    authenticated = true,
    headers: requestHeaders,
    ...requestOptions
  } = options;
  const baseUrl = getApiBaseUrl();
  const requestPath = path.startsWith("/") ? path : `/${path}`;
  const headers = createRequestHeaders(
    requestHeaders,
    requestOptions.body,
    authenticated,
  );

  let response: Response;
  try {
    response = await fetch(`${baseUrl}${requestPath}`, {
      ...requestOptions,
      headers,
    });
  } catch {
    throw new ApiError(
      0,
      "No se pudo conectar con el servidor. Intentá nuevamente.",
    );
  }

  if (response.status === 401 && !isLoginRequest(path)) {
    unauthorizedHandler?.();
  }

  if (!response.ok) throw await toApiError(response);
  return response;
}

export async function apiRequest<T>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  const response = await fetchApi(path, options);
  try {
    return (await response.json()) as T;
  } catch {
    throw new ApiError(
      response.status,
      "La API devolvió una respuesta inválida.",
    );
  }
}

export async function apiDownload(path: string): Promise<void> {
  const response = await fetchApi(path);
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
