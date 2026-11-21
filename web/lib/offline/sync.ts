import { offlineDb } from "@/lib/offline/db";

export async function syncPendingChanges(): Promise<void> {
  const pendingCount = await offlineDb.pendingChanges.count();
  if (pendingCount > 0) {
    throw new Error(
      "El reenvío de cambios pendientes todavía no está implementado.",
    );
  }
}
