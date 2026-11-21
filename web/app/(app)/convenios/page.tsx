import { Suspense } from "react";
import ConveniosPageContent from "@/components/convenios/ConveniosPageContent";

export default function ConveniosPage() {
  return (
    <Suspense
      fallback={
        <p role="status" aria-live="polite" className="p-6">
          Cargando convenios...
        </p>
      }
    >
      <ConveniosPageContent />
    </Suspense>
  );
}
