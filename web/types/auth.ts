import type { UnidadAcademica } from "@/types/unidad";

export interface Rol {
  id_rol: number;
  nombre: string;
}

export type UnidadAcademicaAutenticada = Pick<
  UnidadAcademica,
  "id_unidad_academica" | "sigla" | "nombre" | "id_institucion"
>;

export interface UsuarioAutenticado {
  id_usuario: number;
  mail: string;
  nombre: string;
  activo: boolean;
  id_rol: number;
  id_unidad: number;
  rol: Rol | null;
  unidad_academica: UnidadAcademicaAutenticada | null;
}

export interface LoginCredentials {
  mail: string;
  password: string;
}

export interface LoginResponse {
  message: string;
  access_token: string;
  token_type: "Bearer";
  user: UsuarioAutenticado;
}

export interface CurrentUserResponse {
  user: UsuarioAutenticado;
}
