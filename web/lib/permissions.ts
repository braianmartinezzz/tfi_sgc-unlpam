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

const ALL_CONVENIO_STATES = [
  "Borrador",
  "EnCorreccion",
  "Revisión",
  "Aprobado",
  "Firmado",
  "Rescindido",
  "Rechazado",
  "Archivado",
] as const;

const STATES_BY_ROLE: Readonly<Record<string, readonly string[]>> = {
  Administración: ALL_CONVENIO_STATES,
  Gestión: [
    "Borrador",
    "EnCorreccion",
    "Revisión",
    "Aprobado",
    "Firmado",
    "Rescindido",
  ],
  Revisión: [
    "Revisión",
    "EnCorreccion",
    "Aprobado",
    "Rechazado",
    "Rescindido",
    "Archivado",
  ],
  Consulta: ["Firmado", "Rescindido", "Archivado"],
};

export function getPermissionsForRole(
  role: string | null,
): readonly Permission[] {
  return role ? (ROLE_PERMISSIONS[role] ?? []) : [];
}

export function getVisibleConvenioStates(
  role: string | null,
): readonly string[] {
  return role ? (STATES_BY_ROLE[role] ?? []) : [];
}

export function canCreateConvenio(role: string | null): boolean {
  return role === "Administración" || role === "Gestión";
}

export function canManageDraftConvenio(
  role: string | null,
  estado: string,
): boolean {
  return canCreateConvenio(role) && estado === "Borrador";
}

export function canReviewConvenio(
  role: string | null,
  estado: string,
): boolean {
  return role === "Revisión" && estado === "Revisión";
}

export function canFilterByFaculty(
  role: string | null,
  alcanceGlobal: boolean | undefined,
): boolean {
  return (
    role === "Administración" ||
    ((role === "Revisión" || role === "Consulta") &&
      alcanceGlobal === true)
  );
}

// These checks only shape the UI; the API must enforce authorization and data scope.
