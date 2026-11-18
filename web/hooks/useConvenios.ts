"use client";

import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import * as convenios from "@/services/convenios";
import type { ConvenioListFilters } from "@/types/convenio";

export function useConvenios(filters: ConvenioListFilters = {}) {
  return useQuery({
    queryKey: ["convenios", filters],
    queryFn: () => convenios.list(filters),
    placeholderData: keepPreviousData,
  });
}

export function useConvenio(id: number, enabled = true) {
  return useQuery({
    queryKey: ["convenio", id],
    queryFn: () => convenios.get(id),
    enabled: enabled && id > 0,
  });
}

export function useConvenioDocuments(id: number, enabled = true) {
  return useQuery({
    queryKey: ["convenio", id, "documentos"],
    queryFn: () => convenios.listDocuments(id),
    enabled: enabled && id > 0,
  });
}

export function useRemoveConvenio() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: convenios.remove,
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ["convenios"] }),
  });
}
