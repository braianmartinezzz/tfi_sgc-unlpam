import { z } from "zod";

export const MAX_PDF_SIZE = 20 * 1024 * 1024;

export function isValidPdfFile(file: File): boolean {
  const hasPdfType =
    file.type === "application/pdf" ||
    (file.type === "" && file.name.toLowerCase().endsWith(".pdf"));

  return hasPdfType && file.size <= MAX_PDF_SIZE;
}

const optionalPdfSchema = z.custom<File | null>(
  (value) =>
    value === null ||
    (typeof File !== "undefined" &&
      value instanceof File &&
      isValidPdfFile(value)),
  { error: "El archivo debe ser un PDF de hasta 20 MB." },
);

const anexoSchema = z.object({
  clientId: z.string().uuid(),
  tipo: z.enum([
    "Acta de aprobación",
    "Documentación de la parte externa",
    "Otro",
  ]),
  titulo: z
    .string()
    .trim()
    .min(1, "El título del anexo es obligatorio.")
    .max(255, "El título del anexo no puede superar los 255 caracteres."),
  archivo: optionalPdfSchema,
});

export const convenioFormSchema = z
  .object({
    claseInstrumento: z.enum(["", "Marco", "Específico"]),
    tipo: z.enum(["", "Colaboración", "Pasantía"]),
    titulo: z
      .string()
      .trim()
      .min(1, "El título es obligatorio.")
      .max(255, "El título no puede superar los 255 caracteres."),
    idUnidadAcademica: z.string().min(1, "Seleccioná una unidad académica."),
    idConvenioMarco: z.string(),
    institucionesFirmantes: z
      .array(z.string())
      .min(1, "Seleccioná al menos una institución firmante."),
    archivoPrincipal: optionalPdfSchema,
    expediente: z
      .string()
      .max(100, "El expediente no puede superar los 100 caracteres."),
    resolucionConsejoSuperior: z
      .string()
      .max(100, "La resolución no puede superar los 100 caracteres."),
    objeto: z.string(),
    alcance: z.string(),
    obligaciones: z.string(),
    marcoLegal: z.string(),
    condicionesRescision: z.string(),
    condicionRenovacion: z.string(),
    confidencialidad: z.boolean(),
    fechaInicio: z.string(),
    fechaVencimiento: z.string(),
    diasPreaviso: z
      .number()
      .int("Ingresá una cantidad entera de días.")
      .min(0, "Los días de preaviso no pueden ser negativos."),
    notas: z.string(),
    tituloDocumentoPrincipal: z
      .string()
      .trim()
      .min(1, "El título del documento principal es obligatorio.")
      .max(
        255,
        "El título del documento principal no puede superar los 255 caracteres.",
      ),
    anexos: z.array(anexoSchema),
  })
  .superRefine((values, context) => {
    if (values.claseInstrumento === "") {
      context.addIssue({
        code: "custom",
        path: ["claseInstrumento"],
        message: "Seleccioná la clase de instrumento.",
      });
    }

    if (values.tipo === "") {
      context.addIssue({
        code: "custom",
        path: ["tipo"],
        message: "Seleccioná el tipo de convenio.",
      });
    }

    if (
      values.claseInstrumento === "Específico" &&
      values.idConvenioMarco === ""
    ) {
      context.addIssue({
        code: "custom",
        path: ["idConvenioMarco"],
        message: "Seleccioná el convenio marco relacionado.",
      });
    }

    if (
      values.fechaInicio !== "" &&
      values.fechaVencimiento !== "" &&
      values.fechaVencimiento < values.fechaInicio
    ) {
      context.addIssue({
        code: "custom",
        path: ["fechaVencimiento"],
        message: "La fecha de vencimiento debe ser igual o posterior al inicio.",
      });
    }
  });

export type ConvenioFormValues = z.infer<typeof convenioFormSchema>;
