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
