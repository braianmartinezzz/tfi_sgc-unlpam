import { ApiHttpError } from "@/services/api-client";

export function isConflictError(error: unknown): error is ApiHttpError {
  return error instanceof ApiHttpError && error.status === 409;
}
