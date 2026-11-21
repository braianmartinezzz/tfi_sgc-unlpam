"use client";

import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import * as representantes from "@/services/representantes";
import type { RepresentanteFilters } from "@/services/representantes";
import type { RepresentanteWrite } from "@/types/representante";

export function useRepresentantes(
  filters: Omit<RepresentanteFilters, "page"> = {},
) {
  return useQuery({
    queryKey: ["representantes", filters],
    queryFn: () => representantes.listAll(filters),
    placeholderData: keepPreviousData,
  });
}

export function useCreateRepresentante() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: RepresentanteWrite) =>
      representantes.create(input),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ["representantes"] }),
  });
}

export function useUpdateRepresentante() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      input,
    }: {
      id: number | string;
      input: Partial<RepresentanteWrite>;
    }) => representantes.update(id, input),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ["representantes"] }),
  });
}

export function useSetRepresentanteActivo() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      activo,
    }: {
      id: number | string;
      activo: boolean;
    }) => representantes.setActivo(id, activo),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ["representantes"] }),
  });
}
