export interface CrearObservacionInput {
  contenido: string;
}

export interface CrearObservacionResponse {
  message: string;
  data: {
    id_observacion: number;
    contenido: string;
  };
}

export interface DevolverConvenioInput {
  motivo: string;
}
