"use client";

import {
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import * as unidades from "@/services/unidades";
import type { UnidadFilters } from "@/services/unidades";
import type { UnidadAcademicaWrite } from "@/types/unidad";

export function useUnidades(filters: UnidadFilters = {}) {
  return useQuery({
    queryKey: ["unidades-academicas", filters],
    queryFn: () => unidades.list(filters),
  });
}

export function useUnidad(id: number | string) {
  return useQuery({
    queryKey: ["unidades-academicas", "detail", id],
    queryFn: () => unidades.get(id),
  });
}

export function useCreateUnidad() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: UnidadAcademicaWrite) => unidades.create(input),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ["unidades-academicas"] }),
  });
}

export function useUpdateUnidad() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      input,
    }: {
      id: number | string;
      input: Partial<UnidadAcademicaWrite>;
    }) => unidades.update(id, input),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ["unidades-academicas"] }),
  });
}

export function useSetUnidadActivo() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      activo,
    }: {
      id: number | string;
      activo: boolean;
    }) => unidades.setActivo(id, activo),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["unidades-academicas"] }),
        queryClient.invalidateQueries({ queryKey: ["usuarios"] }),
      ]);
    },
  });
}
