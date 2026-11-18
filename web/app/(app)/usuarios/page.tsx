import { Suspense } from "react";
import UsuariosPageContent from "@/components/usuarios/UsuariosPageContent";

export default function UsuariosPage() {
  return (
    <Suspense
      fallback={
        <p role="status" aria-live="polite" className="p-6">
          Cargando usuarios...
        </p>
      }
    >
      <UsuariosPageContent />
    </Suspense>
  );
}
