"use client";

import { useQuery } from "@tanstack/react-query";
import * as estadisticas from "@/services/estadisticas";
import type { EstadisticaFilters } from "@/services/estadisticas";

export function useEstadisticas(filters: EstadisticaFilters = {}) {
  return useQuery({
    queryKey: ["estadisticas", filters],
    queryFn: () => estadisticas.get(filters),
  });
}
