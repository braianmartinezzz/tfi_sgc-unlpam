"use client";

import { useQuery } from "@tanstack/react-query";
import * as vencimientos from "@/services/vencimientos";
import type { VencimientoFilters } from "@/services/vencimientos";

export function useVencimientos(filters: VencimientoFilters = {}) {
  return useQuery({
    queryKey: ["vencimientos", filters],
    queryFn: () => vencimientos.list(filters),
  });
}
