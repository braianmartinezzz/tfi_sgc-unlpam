"use client";

import { useQuery } from "@tanstack/react-query";
import * as unidades from "@/services/unidades";
import type { UnidadFilters } from "@/services/unidades";

export function useUnidades(filters: UnidadFilters = {}) {
  return useQuery({
    queryKey: ["unidades-academicas", filters],
    queryFn: () => unidades.list(filters),
  });
}
