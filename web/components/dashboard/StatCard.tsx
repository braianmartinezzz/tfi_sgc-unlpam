export default function StatCard({
  label,
  value,
}: {
  label: string;
  value: string | number;
}) {
  return (
    <article>
      <h2>{label}</h2>
      <p>{value}</p>
    </article>
  );
}
