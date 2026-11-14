import type { Rol, UsuarioAutenticado } from "@/types/auth";
import type { UnidadAcademica } from "@/types/unidad";

export interface Usuario
  extends Omit<UsuarioAutenticado, "rol" | "unidad_academica"> {
  rol?: Rol | null;
  unidad_academica?: UnidadAcademica | null;
}

export interface UsuarioWrite {
  mail: string;
  nombre: string;
  password: string;
  id_rol: number;
  id_unidad: number;
  activo?: boolean;
}

export interface UsuarioResponse {
  data: Usuario;
  message?: string;
}
