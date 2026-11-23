import * as FileSystem from 'expo-file-system/legacy';
import * as SecureStore from 'expo-secure-store';

const PHOTO_KEY = 'sgc_profile_photo';

function getWebStorage(): Storage | null {
  try {
    if (typeof window === 'undefined') return null;
    return window.localStorage;
  } catch {
    return null;
  }
}

export async function getProfilePhotoUri(): Promise<string | null> {
  try {
    if (await SecureStore.isAvailableAsync()) {
      const uri = await SecureStore.getItemAsync(PHOTO_KEY);
      if (uri) return uri;
    }
  } catch {
    // Respaldo web.
  }

  return getWebStorage()?.getItem(PHOTO_KEY) ?? null;
}

export async function saveProfilePhoto(sourceUri: string): Promise<string> {
  const directory = FileSystem.documentDirectory;
  if (!directory) {
    throw new Error('No se pudo acceder al almacenamiento del dispositivo.');
  }

  const destinationUri = `${directory}sgc_profile_photo.jpg`;
  const directoryInfo = await FileSystem.getInfoAsync(directory);
  if (!directoryInfo.exists) {
    await FileSystem.makeDirectoryAsync(directory, { intermediates: true });
  }

  await FileSystem.copyAsync({ from: sourceUri, to: destinationUri });

  try {
    if (await SecureStore.isAvailableAsync()) {
      await SecureStore.setItemAsync(PHOTO_KEY, destinationUri);
    } else {
      getWebStorage()?.setItem(PHOTO_KEY, destinationUri);
    }
  } catch {
    getWebStorage()?.setItem(PHOTO_KEY, destinationUri);
  }

  return destinationUri;
}

export async function clearProfilePhoto(): Promise<void> {
  try {
    const uri = await getProfilePhotoUri();
    if (uri?.startsWith('file://')) {
      await FileSystem.deleteAsync(uri, { idempotent: true });
    }
  } catch {
    // No impide limpiar la referencia.
  }

  try {
    if (await SecureStore.isAvailableAsync()) {
      await SecureStore.deleteItemAsync(PHOTO_KEY);
    }
  } catch {
    // Respaldo web.
  }
  getWebStorage()?.removeItem(PHOTO_KEY);
}
