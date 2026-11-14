import EmptyState from "@/components/feedback/EmptyState";

export default async function ConvenioDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <EmptyState title={`Detalle del convenio ${id}`} />;
}
