export interface InstitucionFirmante {
  id_institucion: number;
  razon_social: string;
  cuit: string | null;
  domicilio_legal: string | null;
  tipo_institucion: string | null;
}

export interface UnidadAcademica {
  id_unidad_academica: number;
  sigla: string;
  nombre: string;
  id_institucion: number;
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
