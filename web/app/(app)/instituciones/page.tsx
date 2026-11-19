import { Suspense } from "react";
import InstitucionesPageContent from "@/components/instituciones/InstitucionesPageContent";

export default function InstitucionesPage() {
  return (
    <Suspense
      fallback={
        <p role="status" aria-live="polite" className="p-6">
          Cargando instituciones firmantes...
        </p>
      }
    >
      <InstitucionesPageContent />
    </Suspense>
  );
}
