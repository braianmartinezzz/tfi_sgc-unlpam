"use client";

import { useEffect } from "react";

export default function Error({
  error,
  retry,
}: {
  error: Error & { digest?: string };
  retry: () => void;
}) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <section role="alert">
      <h2>Ocurrió un error inesperado.</h2>
      <button type="button" onClick={retry}>
        Reintentar
      </button>
    </section>
  );
}
