"use client";

import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import * as usuarios from "@/services/usuarios";
import type { UsuarioFilters } from "@/services/usuarios";
import type { UsuarioWrite } from "@/types/usuario";

export function useUsuarios(filters: UsuarioFilters = {}) {
  return useQuery({
    queryKey: ["usuarios", filters],
    queryFn: () => usuarios.list(filters),
    placeholderData: keepPreviousData,
  });
}

export function useUsuario(id: number | string) {
  return useQuery({
    queryKey: ["usuarios", "detail", id],
    queryFn: () => usuarios.get(id),
  });
}

export function useCreateUsuario() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: UsuarioWrite) => usuarios.create(input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["usuarios"] }),
  });
}

export function useUpdateUsuario() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      input,
    }: {
      id: number | string;
      input: Partial<UsuarioWrite>;
    }) => usuarios.update(id, input),
    onSuccess: async (_response, { id }) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["usuarios"] }),
        queryClient.invalidateQueries({
          queryKey: ["usuarios", "detail", id],
          exact: true,
        }),
      ]);
    },
  });
}
