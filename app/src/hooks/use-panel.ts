import { useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { AppState } from 'react-native';
import NetInfo from '@react-native-community/netinfo';

import { clearToken } from '@/lib/auth-storage';
import { guardarOffline, leerOffline } from '@/lib/offline-storage';
import { ApiError, NetworkError } from '@/services/api-client';
import { sincronizarCola } from '@/services/offline-sync';
import { obtenerUsuarioActual } from '@/services/auth';
import { obtenerPanel } from '@/services/panel';
import type { UsuarioAutenticado } from '@/types/auth';
import type { PanelData } from '@/types/panel';

type Instantanea = {
  me: UsuarioAutenticado;
  panel: PanelData;
  actualizado: Date;
};

/**
 * Última respuesta exitosa. Vive en memoria: sirve para mostrar los pendientes de firma
 * cuando se pierde la conexión. Para que sobreviva al cierre de la app hay que guardarla
 * en un almacenamiento persistente (por ejemplo AsyncStorage) en este mismo punto.
 */
let ultimaInstantanea: Instantanea | null = null;

const REINTENTO_SIN_CONEXION_MS = 15000;

export type EstadoPanel = 'cargando' | 'listo' | 'error';

export function usePanel() {
  const router = useRouter();
  const [instantanea, setInstantanea] = useState<Instantanea | null>(ultimaInstantanea);
  const [estado, setEstado] = useState<EstadoPanel>(ultimaInstantanea ? 'listo' : 'cargando');
  const [sinConexion, setSinConexion] = useState(false);
  const [actualizando, setActualizando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const cargar = useCallback(
    async (manual: boolean) => {
      if (manual) setActualizando(true);

      try {
        const [me, panel] = await Promise.all([obtenerUsuarioActual(), obtenerPanel()]);
        const nueva: Instantanea = { me, panel, actualizado: new Date() };

        ultimaInstantanea = nueva;
        setInstantanea(nueva);
        setSinConexion(false);
        setError(null);
        setEstado('listo');
        void guardarOffline('panel_latest', nueva);
      } catch (causa) {
        if (causa instanceof ApiError && causa.status === 401) {
          await clearToken();
          router.replace('/');
          return;
        }

        if (causa instanceof NetworkError) {
          setSinConexion(true);
          setEstado('listo');
        } else {
          setError(causa instanceof Error ? causa.message : 'No se pudo cargar el panel.');
          setEstado(ultimaInstantanea ? 'listo' : 'error');
        }
      } finally {
        setActualizando(false);
      }
    },
    [router],
  );

  // Carga inicial.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- la carga inicial actualiza el estado al resolverse
    void cargar(false);
  }, [cargar]);

  // Al volver a la app, se actualiza en segundo plano.
  useEffect(() => {
    const suscripcion = AppState.addEventListener('change', (nuevoEstado) => {
      if (nuevoEstado === 'active') void cargar(false);
    });

    return () => suscripcion.remove();
  }, [cargar]);

  // Sin conexión, se reintenta cada tanto hasta recuperarla.
  useEffect(() => {
    if (!sinConexion) return;

    const intervalo = setInterval(() => void cargar(false), REINTENTO_SIN_CONEXION_MS);

    return () => clearInterval(intervalo);
  }, [sinConexion, cargar]);

  const actualizar = useCallback(() => cargar(true), [cargar]);

  // Carga el último panel al montar el hook.
  useEffect(() => {
    void leerOffline<Instantanea>('panel_latest').then((guardada) => {
      if (guardada) {
        ultimaInstantanea = {
          ...guardada,
          actualizado: new Date(guardada.actualizado),
        };
        setInstantanea(ultimaInstantanea);
        setEstado('listo');
      }
    });
  }, []);

  // Android/iOS informan cambios reales de red mediante NetInfo.
  useEffect(() => {
    const suscripcion = NetInfo.addEventListener((estado) => {
      const conectada = estado.isConnected === true && estado.isInternetReachable !== false;

      if (conectada) {
        void sincronizarCola();
        void cargar(false);
      } else {
        setSinConexion(true);
      }
    });

    return () => suscripcion();
  }, [cargar]);

  return {
    me: instantanea?.me ?? null,
    panel: instantanea?.panel ?? null,
    actualizado: instantanea?.actualizado ?? null,
    estado,
    sinConexion,
    actualizando,
    error,
    actualizar,
  };
}
