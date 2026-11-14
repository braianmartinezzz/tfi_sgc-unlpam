export default function PreavisoTag({
  diasRestantes,
}: {
  diasRestantes?: number | null;
}) {
  return (
    <span>
      {diasRestantes === undefined || diasRestantes === null
        ? "Preaviso sin datos"
        : `${diasRestantes} días`}
    </span>
  );
}
