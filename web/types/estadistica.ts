export interface EstadisticaConteo {
  total: number;
}

export interface EstadisticaPorEstado extends EstadisticaConteo {
  estado: string;
}

export interface EstadisticaPorTipo extends EstadisticaConteo {
  tipo: string;
}

export interface EstadisticaPorAnio extends EstadisticaConteo {
  anio: number;
}

export interface EstadisticaPorUnidad extends EstadisticaConteo {
  id_unidad_academica: number | null;
  sigla: string | null;
  nombre: string | null;
}

export interface EstadisticaIndicadores {
  convenios_vigentes: number;
  convenios_en_tramite: number;
  firmados_ultimo_anio: number;
  personas_unicas: number;
  pasantes_unicos: number;
}

export interface Estadistica {
  totales: {
    convenios: number;
    marco: number;
    especificos: number;
  };
  por_estado: EstadisticaPorEstado[];
  por_tipo: EstadisticaPorTipo[];
  por_anio: EstadisticaPorAnio[];
  por_unidad_academica: EstadisticaPorUnidad[];
  vencimientos: {
    dias: number;
    vencidos: number;
    proximos: number;
    en_preaviso: number;
  };
  indicadores: EstadisticaIndicadores;
}

export interface EstadisticaResponse {
  message: string;
  data: Estadistica;
}
