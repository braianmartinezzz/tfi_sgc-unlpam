export type MockRole = "Gestión" | "Administración";

// Datos temporales para la vista previa; reemplazar por respuestas de la API.
export const currentUser: { rol: MockRole; id_unidad: number } = {
  rol: "Gestión",
  id_unidad: 1,
};

export const mockUnidadesAcademicas = [
  {
    id_unidad_academica: 1,
    sigla: "FCH",
    nombre: "Facultad de Ciencias Humanas",
    id_institucion: 101,
  },
  {
    id_unidad_academica: 2,
    sigla: "FCEyJ",
    nombre: "Facultad de Ciencias Económicas y Jurídicas",
    id_institucion: 102,
  },
  {
    id_unidad_academica: 3,
    sigla: "FIA",
    nombre: "Facultad de Ingeniería",
    id_institucion: 103,
  },
] as const;

export const mockInstitucionesFirmantes = [
  {
    id_institucion: 101,
    razon_social: "Universidad Nacional de La Pampa",
    cuit: "30-99900000-1",
  },
  {
    id_institucion: 102,
    razon_social: "Facultad de Ciencias Económicas y Jurídicas",
    cuit: "30-71000001-2",
  },
  {
    id_institucion: 103,
    razon_social: "Facultad de Ingeniería",
    cuit: "30-71000002-0",
  },
  {
    id_institucion: 104,
    razon_social: "Municipalidad de Santa Rosa",
    cuit: "30-99900003-9",
  },
  {
    id_institucion: 105,
    razon_social: "Fundación para la Innovación Pampeana",
    cuit: "30-71500004-8",
  },
] as const;

export const mockConveniosMarco = [
  {
    id_instrumento: 501,
    titulo: "Convenio Marco de Cooperación Académica",
  },
  {
    id_instrumento: 502,
    titulo: "Convenio Marco de Prácticas Profesionales",
  },
  {
    id_instrumento: 503,
    titulo: "Convenio Marco de Vinculación Institucional",
  },
] as const;
