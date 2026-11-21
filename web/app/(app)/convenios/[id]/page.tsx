import { Suspense } from "react";
import DetalleConvenio from "@/components/convenios/DetalleConvenio";

export default function ConvenioDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  return (
    <Suspense
      fallback={
        <p role="status" aria-live="polite">
          Cargando convenio...
        </p>
      }
    >
      <ConvenioDetailContent params={params} />
    </Suspense>
  );
}

async function ConvenioDetailContent({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <DetalleConvenio id={id} />;
}
