export default function ErrorState({ message }: { message: string }) {
  return <p role="alert">{message}</p>;
}
