import { Appearance } from 'react-native';
import { useSyncExternalStore } from 'react';

/**
 * To support static rendering, this value needs to be re-calculated on the client side for web
 */

function subscribe(callback: () => void) {
  const subscription = Appearance.addChangeListener(callback);
  return () => subscription.remove();
}

function getSnapshot() {
  return Appearance.getColorScheme() ?? 'light';
}

function getServerSnapshot() {
  return 'light';
}

export function useColorScheme() {
  return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
