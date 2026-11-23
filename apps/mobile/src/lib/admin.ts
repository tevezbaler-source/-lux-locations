import AsyncStorage from '@react-native-async-storage/async-storage';
import { useSyncExternalStore } from 'react';

// Ekip (admin) modu: panel şifresiyle giriş yapan telefonda alt menüde "Panel" sekmesi görünür.
// Müşteriler bu sekmeyi hiç görmez. Bayrak telefonda saklanır, panelden "Çıkış" yapılınca silinir.
const KEY = 'lx:admin';
let admin = false;
const listeners = new Set<() => void>();

AsyncStorage.getItem(KEY)
  .then((v) => setAdmin(v === '1', false))
  .catch(() => {});

export function setAdmin(on: boolean, persist = true) {
  if (persist) (on ? AsyncStorage.setItem(KEY, '1') : AsyncStorage.removeItem(KEY)).catch(() => {});
  if (admin === on) return;
  admin = on;
  listeners.forEach((l) => l());
}

function subscribe(l: () => void) {
  listeners.add(l);
  return () => listeners.delete(l);
}

export function useAdmin() {
  return useSyncExternalStore(subscribe, () => admin, () => false);
}
