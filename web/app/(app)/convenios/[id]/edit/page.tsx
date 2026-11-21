import { Suspense } from "react";
import ConvenioForm from "@/components/convenios/ConvenioForm";

export default function EditConvenioPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  return (
    <section className="space-y-6">
      <h1 className="font-condensed text-3xl font-bold text-foreground">
        Editar convenio
      </h1>
      <Suspense
        fallback={
          <p role="status" className="rounded-md border border-ui-border bg-surface p-4">
            Cargando el convenio...
          </p>
        }
      >
        {params.then(({ id }) => (
          <ConvenioForm convenioId={Number(id)} />
        ))}
      </Suspense>
    </section>
  );
}
