export type Rol = {
  id_rol: number;
  nombre: string;
};

export type UsuarioAutenticado = {
  id_usuario: number;
  mail: string;
  nombre: string;
  activo: boolean;
  id_rol: number;
  id_unidad: number;
  rol: Rol | null;
  unidad_academica: { id_unidad_academica: number; sigla: string; nombre: string } | null;
  alcance_global?: boolean;
};
