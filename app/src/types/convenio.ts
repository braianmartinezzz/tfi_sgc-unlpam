export type UnidadAcademica = {
  id_unidad_academica: number;
  sigla: string;
  nombre: string;
};

export type ConvenioLista = {
  id_instrumento: number;
  tipo: string;
  id_unidad_academica: number;
  instrumento: {
    id_instrumento: number;
    titulo: string;
    estado: string;
    vencimiento: string | null;
  } | null;
  unidad_academica: UnidadAcademica | null;
  instituciones_firmantes: { id_institucion: number; razon_social: string }[];
  marco: { id_instrumento: number } | null;
  especifico: { id_instrumento: number } | null;
};

/** Ficha de un convenio (GET /convenios/{id}); por ahora comparte la forma de la lista. */
export type ConvenioDetalle = ConvenioLista;

/** Respuesta paginada de Laravel (15 por página). */
export type PaginaConvenios = {
  data: ConvenioLista[];
  current_page: number;
  last_page: number;
  total: number;
};

export type FiltrosConvenios = {
  buscar?: string;
  estado?: string;
  /** true = solo marco, false = solo específicos. */
  es_marco?: boolean;
  id_unidad_academica?: number;
  page?: number;
};
