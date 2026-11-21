import { apiRequest } from "@/services/api-client";
import type { ApiMessageResponse } from "@/types/api";
import type {
  CurrentUserResponse,
  LoginResponse,
} from "@/types/auth";

export function login(mail: string, password: string): Promise<LoginResponse> {
  return apiRequest<LoginResponse>("/login", {
    method: "POST",
    body: JSON.stringify({ mail, password }),
    authenticated: false,
  });
}

export function me(): Promise<CurrentUserResponse> {
  return apiRequest<CurrentUserResponse>("/me");
}

export function logout(): Promise<ApiMessageResponse> {
  return apiRequest<ApiMessageResponse>("/logout", { method: "POST" });
}
