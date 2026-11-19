import { Suspense } from "react";
import UnidadesPageContent from "@/components/unidades/UnidadesPageContent";

export default function UnidadesPage() {
  return (
    <Suspense
      fallback={
        <p role="status" aria-live="polite" className="p-6">
          Cargando unidades académicas...
        </p>
      }
    >
      <UnidadesPageContent />
    </Suspense>
  );
}
