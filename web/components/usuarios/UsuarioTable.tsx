import type { Usuario } from "@/types/usuario";

export default function UsuarioTable({ usuarios = [] }: { usuarios?: Usuario[] }) {
  return (
    <section>
      <h2>Usuarios</h2>
      <p>{usuarios.length} registros disponibles.</p>
    </section>
  );
}
