export type Permission =
  | "convenios:read"
  | "convenios:write"
  | "usuarios:write"
  | "unidades:write";

const ROLE_PERMISSIONS: Readonly<Record<string, readonly Permission[]>> = {
  "Administración": [
    "convenios:read",
    "convenios:write",
    "usuarios:write",
    "unidades:write",
  ],
  Gestión: ["convenios:read", "convenios:write"],
  Revisión: ["convenios:read"],
  Consulta: ["convenios:read"],
};

export function getPermissionsForRole(
  role: string | null,
): readonly Permission[] {
  return role ? (ROLE_PERMISSIONS[role] ?? []) : [];
}
