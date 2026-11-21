import ConvenioForm from "@/components/convenios/ConvenioForm";

export default function NewConvenioPage() {
  return (
    <section aria-labelledby="new-convenio-title" className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="w-fit">
          <h1
            id="new-convenio-title"
            className="font-condensed text-3xl font-bold tracking-tight sm:text-4xl"
          >
            Carga de nuevo convenio
          </h1>
          <div
            aria-hidden="true"
            className="mt-2 h-1 w-full rounded-full bg-brand"
          />
        </div>
      </div>
      <ConvenioForm />
    </section>
  );
}
