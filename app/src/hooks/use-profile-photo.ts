import { useEffect, useState } from 'react';
import { getProfilePhotoUri } from '@/lib/profile-photo-storage';

const listeners = new Set<(uri: string | null) => void>();
let sharedUri: string | null = null;
let initialized = false;

export function notifyProfilePhoto(uri: string | null) {
  sharedUri = uri;
  listeners.forEach((listener) => listener(uri));
}

export function useProfilePhoto(): string | null {
  const [uri, setUri] = useState<string | null>(sharedUri);

  useEffect(() => {
    listeners.add(setUri);
    if (!initialized) {
      initialized = true;
      void getProfilePhotoUri().then((saved) => notifyProfilePhoto(saved));
    }
    return () => {
      listeners.delete(setUri);
    };
  }, []);

  return uri;
}
