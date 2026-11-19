import UsuarioEditForm from "@/components/usuarios/UsuarioEditForm";

export default async function EditUsuarioPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ [key: string]: string | string[] | undefined }>;
}) {
  const [{ id }, query] = await Promise.all([params, searchParams]);
  const visit = typeof query.visit === "string" ? query.visit : "direct";

  return <UsuarioEditForm key={`${id}:${visit}`} id={id} />;
}
