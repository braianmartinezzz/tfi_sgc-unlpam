import * as SecureStore from 'expo-secure-store';

export type ThemePreference = 'light' | 'dark';

const THEME_KEY = 'sgc_theme';

function getWebStorage(): Storage | null {
  try {
    if (typeof window === 'undefined') return null;
    window.localStorage.setItem('__theme_probe__', '1');
    window.localStorage.removeItem('__theme_probe__');
    return window.localStorage;
  } catch {
    return null;
  }
}

export async function getThemePreference(): Promise<ThemePreference | null> {
  try {
    if (await SecureStore.isAvailableAsync()) {
      const value = await SecureStore.getItemAsync(THEME_KEY);
      if (value === 'light' || value === 'dark') return value;
    }
  } catch {
    // Se usa el respaldo web si SecureStore no está disponible.
  }

  const value = getWebStorage()?.getItem(THEME_KEY);
  return value === 'light' || value === 'dark' ? value : null;
}

export async function setThemePreference(preference: ThemePreference): Promise<void> {
  try {
    if (await SecureStore.isAvailableAsync()) {
      await SecureStore.setItemAsync(THEME_KEY, preference);
      return;
    }
  } catch {
    // Se usa el respaldo web si SecureStore no está disponible.
  }

  getWebStorage()?.setItem(THEME_KEY, preference);
}
