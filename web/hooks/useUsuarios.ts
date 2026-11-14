"use client";

import { useQuery } from "@tanstack/react-query";
import * as usuarios from "@/services/usuarios";
import type { UsuarioFilters } from "@/services/usuarios";

export function useUsuarios(filters: UsuarioFilters = {}) {
  return useQuery({
    queryKey: ["usuarios", filters],
    queryFn: () => usuarios.list(filters),
  });
}
