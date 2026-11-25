import * as Location from 'expo-location';

/** No se pudo obtener la ubicación para firmar. `bloqueado`: solo se arregla desde los ajustes del celular. */
export class UbicacionError extends Error {
  bloqueado: boolean;

  constructor(message: string, bloqueado = false) {
    super(message);
    this.name = 'UbicacionError';
    this.bloqueado = bloqueado;
  }
}

/** Pide permiso (si hace falta) y devuelve la posición actual, con mensajes claros para cada falla. */
export async function obtenerUbicacionParaFirma(): Promise<{ latitud: number; longitud: number }> {
  const permiso = await Location.requestForegroundPermissionsAsync();

  if (permiso.status !== 'granted') {
    if (permiso.canAskAgain === false) {
      throw new UbicacionError(
        'El permiso de ubicación está bloqueado. Habilitalo en los ajustes de la aplicación para poder firmar.',
        true,
      );
    }

    throw new UbicacionError('Hace falta permiso de ubicación para registrar la firma.');
  }

  if (!(await Location.hasServicesEnabledAsync())) {
    throw new UbicacionError('La ubicación (GPS) del dispositivo está desactivada. Activala y reintentá.');
  }

  try {
    const posicion = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
    return { latitud: posicion.coords.latitude, longitud: posicion.coords.longitude };
  } catch {
    throw new UbicacionError('No se pudo obtener tu ubicación. Probá en un lugar con mejor señal.');
  }
}
