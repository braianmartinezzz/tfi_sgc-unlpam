import Dexie, { type Table } from "dexie";

export interface OfflineReadCache {
  key: string;
  value: unknown;
  updatedAt: number;
}

export interface PendingChange {
  id?: number;
  method: "POST" | "PUT" | "DELETE";
  path: string;
  body?: unknown;
  createdAt: number;
}

class OfflineDatabase extends Dexie {
  reads!: Table<OfflineReadCache, string>;
  pendingChanges!: Table<PendingChange, number>;

  constructor() {
    super("sgc-offline-cache");
    this.version(1).stores({
      reads: "&key, updatedAt",
      pendingChanges: "++id, createdAt",
    });
  }
}

export const offlineDb = new OfflineDatabase();
