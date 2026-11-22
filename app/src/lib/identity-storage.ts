import * as SecureStore from 'expo-secure-store';

import { isSecureStoreAvailable } from '@/lib/auth-storage';
import type {
  BiometriaRegistrada,
  CampoBiometrico,
  FirmaRegistrada,
  IdentidadRegistrada,
} from '@/types/identidad';

/**
 * Identidad del usuario (firma, rostro y huella) guardada solo en el dispositivo,
 * en el almacenamiento seguro (Keychain en iOS, Keystore en Android).
 *
 * - Las claves llevan el id del usuario: en un dispositivo compartido, cada cuenta
 *   ve únicamente lo suyo.
 * - La imagen de la firma es más grande que lo que conviene guardar en un solo valor
 *   de SecureStore (~2 KB), así que se parte en bloques y se arma de nuevo al leerla.
 * - Del rostro y la huella NO se guarda ningún dato biométrico (el sistema operativo
 *   no lo permite): solo queda constancia de que el usuario los verificó y cuándo.
 */

const TAMANIO_BLOQUE = 1500;
const MAX_BLOQUES = 200;

const clave = (idUsuario: number, campo: string) => `identidad_${idUsuario}_${campo}`;

async function exigirAlmacenSeguro(): Promise<void> {
  if (!(await isSecureStoreAvailable())) {
    throw new Error('El almacenamiento seguro solo está disponible en la app para Android e iOS.');
  }
}

type MetaFirma = { bloques: number; fecha: string };

export async function leerFirmaRegistrada(idUsuario: number): Promise<FirmaRegistrada | null> {
  const bruto = await SecureStore.getItemAsync(clave(idUsuario, 'firma_meta'));
  if (!bruto) return null;

  try {
    const meta = JSON.parse(bruto) as MetaFirma;
    const bloques = await Promise.all(
      Array.from({ length: meta.bloques }, (_, i) =>
        SecureStore.getItemAsync(clave(idUsuario, `firma_${i}`)),
      ),
    );
    if (bloques.some((bloque) => bloque === null)) return null;

    return { imagen: bloques.join(''), fecha: meta.fecha };
  } catch {
    return null;
  }
}

export async function borrarFirmaRegistrada(idUsuario: number): Promise<void> {
  await exigirAlmacenSeguro();

  const bruto = await SecureStore.getItemAsync(clave(idUsuario, 'firma_meta'));
  if (!bruto) return;

  let bloques = 0;
  try {
    bloques = (JSON.parse(bruto) as MetaFirma).bloques;
  } catch {
    // Metadatos dañados: solo se elimina la entrada.
  }

  await Promise.all([
    SecureStore.deleteItemAsync(clave(idUsuario, 'firma_meta')),
    ...Array.from({ length: bloques }, (_, i) =>
      SecureStore.deleteItemAsync(clave(idUsuario, `firma_${i}`)),
    ),
  ]);
}

export async function guardarFirmaRegistrada(
  idUsuario: number,
  imagen: string,
): Promise<FirmaRegistrada> {
  await exigirAlmacenSeguro();

  const bloques = Math.ceil(imagen.length / TAMANIO_BLOQUE);
  if (bloques === 0) throw new Error('La firma está vacía.');
  if (bloques > MAX_BLOQUES) {
    throw new Error('La firma es demasiado grande. Probá con un trazo más simple.');
  }

  // Primero se borra la anterior; los metadatos se escriben al final, así que una
  // interrupción a mitad de camino nunca deja una firma "a medias" como válida.
  await borrarFirmaRegistrada(idUsuario);
  await Promise.all(
    Array.from({ length: bloques }, (_, i) =>
      SecureStore.setItemAsync(
        clave(idUsuario, `firma_${i}`),
        imagen.slice(i * TAMANIO_BLOQUE, (i + 1) * TAMANIO_BLOQUE),
      ),
    ),
  );

  const fecha = new Date().toISOString();
  const meta: MetaFirma = { bloques, fecha };
  await SecureStore.setItemAsync(clave(idUsuario, 'firma_meta'), JSON.stringify(meta));

  return { imagen, fecha };
}

async function leerBiometria(
  idUsuario: number,
  campo: CampoBiometrico,
): Promise<BiometriaRegistrada | null> {
  const bruto = await SecureStore.getItemAsync(clave(idUsuario, campo));
  if (!bruto) return null;

  try {
    const dato = JSON.parse(bruto) as BiometriaRegistrada;
    return typeof dato.fecha === 'string' ? dato : null;
  } catch {
    return null;
  }
}

export async function guardarBiometriaRegistrada(
  idUsuario: number,
  campo: CampoBiometrico,
): Promise<BiometriaRegistrada> {
  await exigirAlmacenSeguro();

  const registro: BiometriaRegistrada = { fecha: new Date().toISOString() };
  await SecureStore.setItemAsync(clave(idUsuario, campo), JSON.stringify(registro));
  return registro;
}

export async function borrarBiometriaRegistrada(
  idUsuario: number,
  campo: CampoBiometrico,
): Promise<void> {
  await exigirAlmacenSeguro();
  await SecureStore.deleteItemAsync(clave(idUsuario, campo));
}

export async function leerIdentidad(idUsuario: number): Promise<IdentidadRegistrada> {
  if (!(await isSecureStoreAvailable())) {
    return { firma: null, rostro: null, huella: null };
  }

  const [firma, rostro, huella] = await Promise.all([
    leerFirmaRegistrada(idUsuario),
    leerBiometria(idUsuario, 'rostro'),
    leerBiometria(idUsuario, 'huella'),
  ]);

  return { firma, rostro, huella };
}
