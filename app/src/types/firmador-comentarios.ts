export type ComentarioFirmador = {
  id_comentario: number;
  contenido: string;
  ofensivo: boolean;
  fecha: string;
  usuario: {
    id_usuario: number;
    nombre: string;
  };
};
