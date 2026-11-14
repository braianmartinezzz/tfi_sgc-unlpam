import type { Vencimiento } from "@/services/vencimientos";

export default function VencimientoList({
  items = [],
}: {
  items?: Vencimiento[];
}) {
  return (
    <section>
      <h2>Vencimientos</h2>
      <p>{items.length} vencimientos próximos.</p>
    </section>
  );
}
