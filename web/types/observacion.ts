export interface Observacion {
  id_observacion: number;
  contenido: string;
  fecha: string;
  estado_tramite: string;
  resolucion: string | null;
  respuesta: string | null;
  fecha_resolucion: string | null;
  usuario: {
    id_usuario: number;
    nombre: string;
  };
}

export interface ObservacionesResponse {
  data: Observacion[];
}

export interface ResponderObservacionInput {
  respuesta: string;
}

export interface ResponderObservacionResponse {
  message: string;
  data: {
    id_observacion: number;
    respuesta: string;
    estado_tramite: string;
    fecha_resolucion: string;
  };
}

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
