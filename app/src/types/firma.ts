export type DocumentoFirma = {
  id_documento: number;
  id_version: number;
  nombre_archivo: string;
};

export type FirmantePendiente = {
  id_persona: number;
  nombre: string;
  apellido: string;
  firmado: boolean;
};

export type ConvenioPadreFirma = {
  id_instrumento: number;
  titulo: string;
  expediente: string | null;
};

export type PendienteFirmaCompleto = {
  id_instrumento: number;
  titulo: string;
  expediente: string | null;
  objeto: string | null;
  tipo: string | null;
  unidad_academica: string | null;
  procedencia: string | null;
  inicio: string | null;
  vencimiento: string | null;
  convenio_padre: ConvenioPadreFirma | null;
  aprobado_el: string | null;
  horas_pendiente: number | null;
  demorado: boolean;
  documento: DocumentoFirma | null;
  firmantes: FirmantePendiente[];
};
