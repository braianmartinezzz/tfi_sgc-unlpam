import { Suspense } from "react";
import Link from "next/link";
import DetalleConvenio from "@/components/convenios/DetalleConvenio";

export default function RevisarConvenioPage({
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
      <RevisarConvenioContent params={params} />
    </Suspense>
  );
}

async function RevisarConvenioContent({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return (
    <section className="space-y-5">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div className="w-fit">
          <h1 className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl">
            Revisión de convenio
          </h1>
          <div
            aria-hidden="true"
            className="mt-2 h-1 w-full rounded-full bg-brand"
          />
        </div>
        <Link
          href={`/convenios/${encodeURIComponent(id)}`}
          className="inline-flex min-h-11 items-center justify-center rounded-md border border-ui-border bg-surface px-4 py-2 font-semibold text-foreground hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
        >
          Volver
        </Link>
      </header>
      <DetalleConvenio id={id} reviewMode />
    </section>
  );
}
