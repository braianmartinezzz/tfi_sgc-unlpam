import type { Paginated } from "@/types/api";
import type { InstitucionFirmante } from "@/types/unidad";

export interface Representante {
  id_persona: number;
  cargo: string | null;
  id_institucion: number;
  activo: boolean;
  persona: {
    id_persona: number;
    nombre: string;
    apellido: string;
    dni: string;
    cuil: string | null;
  };
  institucion_firmante: Pick<
    InstitucionFirmante,
    | "id_institucion"
    | "razon_social"
    | "cuit"
    | "domicilio_legal"
    | "tipo_institucion"
    | "activo"
    | "es_propia"
  >;
}

export interface RepresentanteWrite {
  nombre: string;
  apellido: string;
  dni: string;
  cuil?: string | null;
  cargo?: string | null;
  id_institucion: number;
}

export interface RepresentanteResponse {
  data: Representante;
  message?: string;
}

export type RepresentanteListResponse = Paginated<Representante>;
