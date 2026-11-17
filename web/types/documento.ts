export interface Documento {
  id_documento: number;
  tipo: string;
  titulo: string;
  id_instrumento: number;
}

export interface DocumentoVersion {
  id_version: number;
  numero: number;
  nombre_archivo: string;
  tamanio_bytes: number;
  hash_sha256: string;
  fecha_carga: string;
  id_documento: number;
  id_usuario: number;
}

export interface DocumentoUploadInput {
  tipo: string;
  titulo: string;
  archivo: File;
}

export interface DocumentoListItem {
  documento: Documento;
  ultima_version: DocumentoVersion | null;
}

export interface DocumentoListResponse {
  data: DocumentoListItem[];
}

export interface DocumentoMutationResponse {
  message: string;
  data: {
    documento: Documento;
    version: DocumentoVersion | null;
  };
}

export interface EnviarRevisionResponse {
  message: string;
  estado: string;
  historial: {
    estado_anterior: string;
    estado_nuevo: string;
  };
}
