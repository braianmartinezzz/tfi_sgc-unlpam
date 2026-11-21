"use client";

import { useOnlineStatus } from "@/hooks/useOnlineStatus";

export default function OfflineBanner() {
  const isOnline = useOnlineStatus();
  if (isOnline) return null;

  return (
    <div className="bg-amber-100 px-4 py-2 text-amber-950" role="status">
      Sin conexión. Algunas funciones pueden no estar disponibles.
    </div>
  );
}
