import AsyncStorage from '@react-native-async-storage/async-storage';

import { olvidarInstantaneaEnMemoria } from '@/lib/panel-memoria';

const PREFIX = 'sgc_offline:';
const CLAVE_DUENO = 'duenio_cache';

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

/** Cambia solo una operación de la cola, sobre lo que hay guardado ahora (no sobre una copia vieja). */
export async function actualizarOperacion(
  id: string,
  cambios: Partial<Pick<PendingOperation, 'attempts'>>,
): Promise<void> {
  const cola = await leerCola();
  await actualizarCola(cola.map((operacion) => (operacion.id === id ? { ...operacion, ...cambios } : operacion)));
}

/** Borra todo lo guardado para uso offline (panel, convenios, cola) y la copia en memoria. */
export async function limpiarOffline(): Promise<void> {
  olvidarInstantaneaEnMemoria();

  const claves = (await AsyncStorage.getAllKeys()).filter((clave) => clave.startsWith(PREFIX));
  if (claves.length > 0) await AsyncStorage.multiRemove(claves);
}

/**
 * Los datos offline pertenecen a un usuario. Si entra otro en el mismo celular se borran
 * antes de mostrarlos, para que no vea el panel ni dispare la cola del anterior.
 * Si es el mismo usuario (por ejemplo, tras vencer la sesión) se conservan.
 */
export async function asegurarCacheDelUsuario(idUsuario: number): Promise<void> {
  const dueno = await leerOffline<number>(CLAVE_DUENO);

  if (dueno !== idUsuario) {
    await limpiarOffline();
    await guardarOffline(CLAVE_DUENO, idUsuario);
  }
}

export async function quitarDeCola(id: string): Promise<void> {
  const cola = await leerCola();
  await actualizarCola(cola.filter((operacion) => operacion.id !== id));
}
