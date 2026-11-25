import AsyncStorage from '@react-native-async-storage/async-storage';

const PREFIX = 'sgc_offline:';

export type PendingOperation = {
  id: string;
  method: 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  path: string;
  body?: unknown;
  createdAt: string;
  attempts: number;
};

export async function guardarOffline<T>(clave: string, valor: T): Promise<void> {
  await AsyncStorage.setItem(PREFIX + clave, JSON.stringify(valor));
}

export async function leerOffline<T>(clave: string): Promise<T | null> {
  const valor = await AsyncStorage.getItem(PREFIX + clave);
  if (!valor) return null;

  try {
    return JSON.parse(valor) as T;
  } catch {
    await AsyncStorage.removeItem(PREFIX + clave);
    return null;
  }
}

export async function eliminarOffline(clave: string): Promise<void> {
  await AsyncStorage.removeItem(PREFIX + clave);
}

export async function leerCola(): Promise<PendingOperation[]> {
  return (await leerOffline<PendingOperation[]>('pending_operations')) ?? [];
}

export async function agregarCola(
  operacion: Omit<PendingOperation, 'id' | 'createdAt' | 'attempts'>,
): Promise<PendingOperation> {
  const cola = await leerCola();
  const nueva: PendingOperation = {
    ...operacion,
    id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
    createdAt: new Date().toISOString(),
    attempts: 0,
  };

  await guardarOffline('pending_operations', [...cola, nueva]);
  return nueva;
}

export async function actualizarCola(cola: PendingOperation[]): Promise<void> {
  await guardarOffline('pending_operations', cola);
}

export async function quitarDeCola(id: string): Promise<void> {
  const cola = await leerCola();
  await actualizarCola(cola.filter((operacion) => operacion.id !== id));
}
