import type { Paginated } from "@/types/api";

export interface InstitucionFirmante {
  id_institucion: number;
  razon_social: string;
  cuit: string | null;
  domicilio_legal: string | null;
  tipo_institucion: string | null;
  activo: boolean;
  es_propia: boolean;
  representantes?: RepresentanteInstitucion[];
}

export interface InstitucionFirmanteWrite {
  razon_social: string;
  cuit?: string | null;
  domicilio_legal?: string | null;
  tipo_institucion?: string | null;
}

export interface InstitucionFirmanteResponse {
  data: InstitucionFirmante;
  message?: string;
}

export type InstitucionFirmanteListResponse = Paginated<InstitucionFirmante>;

export interface RepresentanteInstitucion {
  id_persona: number;
  id_institucion: number;
  cargo: string | null;
  persona?: {
    id_persona: number;
    nombre: string;
    apellido: string;
  } | null;
}

export interface UnidadAcademica {
  id_unidad_academica: number;
  sigla: string;
  nombre: string;
  id_institucion: number;
  activo: boolean;
  institucion_firmante?: InstitucionFirmante | null;
}

export interface UnidadAcademicaWrite {
  sigla: string;
  nombre: string;
  id_institucion: number;
}

export interface UnidadAcademicaListResponse {
  data: UnidadAcademica[];
}

export interface UnidadAcademicaResponse {
  data: UnidadAcademica;
  message?: string;
}
