import type { InstitucionFirmante, UnidadAcademica } from "@/types/unidad";

export interface ConvenioInstrumento {
  id_instrumento: number;
  titulo: string;
  objeto: string | null;
  estado: string;
  fecha_firma: string | null;
  inicio: string | null;
  vencimiento: string | null;
  dias_preaviso: number | null;
  expediente: string | null;
  resolucion: string | null;
  notas: string | null;
  version_registro: number;
}

export interface ConvenioMarco {
  id_instrumento: number;
}

export interface ConvenioEspecifico {
  id_instrumento: number;
  id_convenio_marco: number;
}

export interface HistorialEstado {
  id_historial: number;
  estado_anterior: string | null;
  estado_nuevo: string;
  fecha_hora: string | null;
  motivo: string | null;
  id_instrumento: number;
  id_usuario: number;
}

export interface Convenio {
  id_instrumento: number;
  tipo: string;
  alcance: string | null;
  obligaciones: string | null;
  condiciones_recision: string | null;
  marco_legal: string | null;
  condicion_renovacion: string | null;
  confidencialidad: boolean | null;
  id_unidad_academica: number;
  instrumento?: ConvenioInstrumento | null;
  unidad_academica?: UnidadAcademica | null;
  instituciones_firmantes?: InstitucionFirmante[];
  marco?: ConvenioMarco | null;
  especifico?: ConvenioEspecifico | null;
  historial_estados?: HistorialEstado[];
}

export interface ConvenioListFilters {
  estado?: string;
  tipo?: string;
  es_marco?: boolean;
  id_unidad_academica?: number;
  buscar?: string;
  page?: number;
}

export interface ConvenioCreateInput {
  titulo: string;
  objeto?: string | null;
  id_unidad_academica: number;
  tipo: string;
  es_marco: boolean;
  id_convenio_marco?: number;
  instituciones_ids: number[];
  dias_preaviso?: number | null;
}

export interface ConvenioUpdateInput {
  titulo?: string;
  objeto?: string | null;
  expediente?: string | null;
  resolucion?: string | null;
  fecha_firma?: string | null;
  inicio?: string | null;
  vencimiento?: string | null;
  dias_preaviso?: number | null;
  notas?: string | null;
  tipo?: string;
  alcance?: string | null;
  obligaciones?: string | null;
  condiciones_recision?: string | null;
  marco_legal?: string | null;
  condicion_renovacion?: string | null;
  confidencialidad?: boolean | null;
  id_unidad_academica?: number;
  id_convenio_marco?: number;
  instituciones_ids?: number[];
}

export interface ConvenioResponse {
  data: Convenio;
}

export interface ConvenioMutationResponse {
  message: string;
  data: {
    instrumento: ConvenioInstrumento;
    convenio: Convenio;
    subtipo: ConvenioMarco | ConvenioEspecifico | null;
    instituciones_firmantes: InstitucionFirmante[];
  };
}
