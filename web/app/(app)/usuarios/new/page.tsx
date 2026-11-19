import { Suspense } from "react";
import UsuarioForm from "@/components/usuarios/UsuarioForm";

export default function NewUsuarioPage() {
  return (
    <Suspense
      fallback={
        <p role="status" aria-live="polite" className="p-6">
          Cargando formulario de usuario...
        </p>
      }
    >
      <UsuarioForm />
    </Suspense>
  );
}
