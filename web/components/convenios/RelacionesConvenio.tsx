import type { Convenio } from "@/types/convenio";

export default function RelacionesConvenio({
  convenio,
}: {
  convenio?: Convenio;
}) {
  const count = convenio?.instituciones_firmantes?.length ?? 0;
  return <section>Instituciones firmantes: {count}</section>;
}
