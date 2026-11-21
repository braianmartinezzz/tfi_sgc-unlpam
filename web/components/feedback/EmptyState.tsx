export default function EmptyState({
  title,
  description = "Esta sección está lista para conectar con la API.",
}: {
  title: string;
  description?: string;
}) {
  return (
    <section>
      <h2>{title}</h2>
      <p>{description}</p>
    </section>
  );
}
