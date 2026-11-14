"use client";

import { useQuery } from "@tanstack/react-query";
import * as convenios from "@/services/convenios";
import type { ConvenioListFilters } from "@/types/convenio";

export function useConvenios(filters: ConvenioListFilters = {}) {
  return useQuery({
    queryKey: ["convenios", filters],
    queryFn: () => convenios.list(filters),
  });
}
