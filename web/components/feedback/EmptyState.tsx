export default function EmptyState({
  title,
}: {
  title: string;
}) {
  return (
    <section>
      <h2>{title}</h2>
      <p>Esta sección está lista para conectar con la API.</p>
    </section>
  );
}
