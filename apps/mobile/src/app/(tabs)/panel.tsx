import { colors } from '@lux/shared';
import { View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { PanelView } from '@/components/panel-view';

// Yalnızca ekip modundaki telefonlarda görünen sekme.
export default function PanelTab() {
  const insets = useSafeAreaInsets();
  return (
    <View style={{ flex: 1, paddingTop: insets.top, backgroundColor: colors.paper }}>
      <PanelView />
    </View>
  );
}
