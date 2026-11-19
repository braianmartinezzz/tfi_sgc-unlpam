"use client";

import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import * as instituciones from "@/services/instituciones-firmantes";
import type { InstitucionFirmanteFilters } from "@/services/instituciones-firmantes";
import type { InstitucionFirmanteWrite } from "@/types/unidad";

export function useInstituciones(
  filters: InstitucionFirmanteFilters = {},
) {
  return useQuery({
    queryKey: ["instituciones-firmantes", filters],
    queryFn: () => instituciones.list(filters),
    placeholderData: keepPreviousData,
  });
}

export function useInstitucion(id: number | string) {
  return useQuery({
    queryKey: ["instituciones-firmantes", "detail", id],
    queryFn: () => instituciones.get(id),
  });
}

export function useCreateInstitucion() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: InstitucionFirmanteWrite) =>
      instituciones.create(input),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: ["instituciones-firmantes"],
        }),
        queryClient.invalidateQueries({ queryKey: ["unidades-academicas"] }),
      ]);
    },
  });
}

export function useUpdateInstitucion() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      input,
    }: {
      id: number | string;
      input: Partial<InstitucionFirmanteWrite>;
    }) => instituciones.update(id, input),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: ["instituciones-firmantes"],
        }),
        queryClient.invalidateQueries({ queryKey: ["unidades-academicas"] }),
      ]);
    },
  });
}

export function useSetInstitucionActivo() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      activo,
    }: {
      id: number | string;
      activo: boolean;
    }) => instituciones.setActivo(id, activo),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: ["instituciones-firmantes"],
        }),
        queryClient.invalidateQueries({ queryKey: ["unidades-academicas"] }),
        queryClient.invalidateQueries({ queryKey: ["representantes"] }),
        queryClient.invalidateQueries({ queryKey: ["usuarios"] }),
      ]);
    },
  });
}
