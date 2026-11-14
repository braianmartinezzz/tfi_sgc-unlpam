"use client";

import { useMemo } from "react";
import { useAuth } from "@/context/AuthContext";
import {
  getPermissionsForRole,
  type Permission,
} from "@/lib/permissions";

export function usePermissions(): {
  role: string | null;
  unitId: number | null;
  can: (permission: Permission) => boolean;
} {
  const { user } = useAuth();
  const role = user?.rol?.nombre ?? null;
  const permissions = useMemo(
    () => getPermissionsForRole(role),
    [role],
  );

  return {
    role,
    unitId: user?.id_unidad ?? null,
    can: (permission) => permissions.includes(permission),
  };
}
