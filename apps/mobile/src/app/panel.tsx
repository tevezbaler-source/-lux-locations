import { Stack } from 'expo-router';

import { PanelView } from '@/components/panel-view';

// İletişim sayfasındaki küçük "Ekip girişi" bağlantısından açılır.
export default function PanelLoginScreen() {
  return (
    <>
      <Stack.Screen options={{ title: 'Panel' }} />
      <PanelView />
    </>
  );
}
