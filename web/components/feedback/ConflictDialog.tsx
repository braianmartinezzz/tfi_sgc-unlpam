export default function ConflictDialog({
  message = "El recurso cambió en el servidor.",
}: {
  message?: string;
}) {
  return <section aria-label="Conflicto">{message}</section>;
}
