import type { Convenio } from "@/types/convenio";

export default function FichaConvenio({
  convenio,
}: {
  convenio?: Convenio;
}) {
  return (
    <section>
      <h2>Ficha del convenio</h2>
      <p>{convenio?.instrumento?.titulo ?? "Detalle pendiente de cargar."}</p>
    </section>
  );
}
