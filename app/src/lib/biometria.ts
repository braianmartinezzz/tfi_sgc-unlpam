import * as LocalAuthentication from 'expo-local-authentication';

import type { CampoBiometrico } from '@/types/identidad';

const CONFIG: Record<
  CampoBiometrico,
  { tipo: LocalAuthentication.AuthenticationType; nombre: string; mensaje: string }
> = {
  rostro: {
    tipo: LocalAuthentication.AuthenticationType.FACIAL_RECOGNITION,
    nombre: 'el reconocimiento facial',
    mensaje: 'Mirá la cámara para verificar tu rostro',
  },
  huella: {
    tipo: LocalAuthentication.AuthenticationType.FINGERPRINT,
    nombre: 'la huella digital',
    mensaje: 'Apoyá tu dedo en el sensor para verificar tu huella',
  },
};

/**
 * Pide al sistema operativo que verifique el rostro o la huella del usuario.
 * Lanza un Error con un mensaje listo para mostrar si no se puede o falla.
 *
 * Limitación de la plataforma: la app no puede elegir ni leer qué biometría se usó;
 * si el dispositivo tiene ambas, el sistema puede aceptar cualquiera de las dos.
 * Por eso solo se comprueba que el dispositivo admita ese tipo y que haya uno cargado.
 */
export async function verificarBiometria(campo: CampoBiometrico): Promise<void> {
  const { tipo, nombre, mensaje } = CONFIG[campo];

  const [hardware, inscripta, tipos] = await Promise.all([
    LocalAuthentication.hasHardwareAsync(),
    LocalAuthentication.isEnrolledAsync(),
    LocalAuthentication.supportedAuthenticationTypesAsync(),
  ]);

  if (!hardware || !tipos.includes(tipo)) {
    throw new Error(`Este dispositivo no admite ${nombre}.`);
  }
  if (!inscripta) {
    throw new Error(`Configurá ${nombre} en los ajustes del dispositivo y volvé a intentar.`);
  }

  const resultado = await LocalAuthentication.authenticateAsync({
    promptMessage: mensaje,
    cancelLabel: 'Cancelar',
    // Sin PIN/patrón: el código del dispositivo no demuestra rostro ni huella.
    disableDeviceFallback: true,
  });

  if (!resultado.success) {
    throw new Error('La verificación fue cancelada o no coincidió.');
  }
}
