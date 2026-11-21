export type Permission =
  | "convenios:read"
  | "convenios:write"
  | "usuarios:write"
  | "catalogos:write"
  | "catalogos:toggle";

const ROLE_PERMISSIONS: Readonly<Record<string, readonly Permission[]>> = {
  "Administración": [
    "convenios:read",
    "convenios:write",
    "usuarios:write",
    "catalogos:write",
    "catalogos:toggle",
  ],
  Gestión: ["convenios:read", "convenios:write", "catalogos:write"],
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

export function canEditConvenio(
  role: string | null,
  estado: string,
): boolean {
  return (
    canCreateConvenio(role) &&
    (estado === "Borrador" || estado === "EnCorreccion")
  );
}

function idsMatch(
  userId: number | null | undefined,
  recordId: number | null | undefined,
): boolean {
  return userId !== null && userId !== undefined &&
    recordId !== null && recordId !== undefined &&
    userId === recordId;
}

export function canCreateInstitucion(role: string | null): boolean {
  return role === "Administración";
}

export function canCreateUnidad(role: string | null): boolean {
  return role === "Administración";
}

export function canEditInstitucion(
  role: string | null,
  userInstitucionId: number | null | undefined,
  institucionId: number | null | undefined,
): boolean {
  return role === "Administración" ||
    (role === "Gestión" && idsMatch(userInstitucionId, institucionId));
}

export function canEditUnidad(
  role: string | null,
  userUnidadId: number | null | undefined,
  unidadId: number | null | undefined,
): boolean {
  return role === "Administración" ||
    (role === "Gestión" && idsMatch(userUnidadId, unidadId));
}

export function canManageRepresentantes(
  role: string | null,
  userInstitucionId: number | null | undefined,
  institucionId: number | null | undefined,
): boolean {
  return canEditInstitucion(role, userInstitucionId, institucionId);
}

export function canChangeInstitutionOfRecord(role: string | null): boolean {
  return role === "Administración";
}

export function canChangeInstitucionOfRecord(role: string | null): boolean {
  return canChangeInstitutionOfRecord(role);
}

export function canToggleCatalogo(role: string | null): boolean {
  return role === "Administración";
}

export function canDeleteDraftConvenio(
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
