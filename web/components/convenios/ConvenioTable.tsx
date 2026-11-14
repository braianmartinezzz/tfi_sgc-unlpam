import type { Convenio } from "@/types/convenio";

export default function ConvenioTable({
  convenios = [],
}: {
  convenios?: Convenio[];
}) {
  return (
    <div>
      <h2>Convenios</h2>
      <p>{convenios.length} registros disponibles.</p>
    </div>
  );
}
