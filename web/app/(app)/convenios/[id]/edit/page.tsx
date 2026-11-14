import ConvenioForm from "@/components/convenios/ConvenioForm";

export default async function EditConvenioPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return (
    <section>
      <h1>Editar convenio {id}</h1>
      <ConvenioForm />
    </section>
  );
}
