import UsuarioDetail from "@/components/usuarios/UsuarioDetail";

export default async function UsuarioDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <UsuarioDetail id={id} />;
}
