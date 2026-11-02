import { RESERVATIONS } from '@lux/shared';
import { NativeTabs } from 'expo-router/native-tabs';
import { useColorScheme } from 'react-native';

import { Colors } from '@/constants/theme';

// Simgeler iOS'ta SF Symbols, Android'de Material simgelerinden gelir.
const icons = {
  oda: { sf: 'bed.double', md: 'hotel' },
  grup: { sf: 'person.3', md: 'groups' },
  kongre: { sf: 'person.wave.2', md: 'co_present' },
} as const;

export default function AppTabs() {
  const scheme = useColorScheme();
  const colors = Colors[scheme === 'dark' ? 'dark' : 'light'];

  return (
    <NativeTabs
      backgroundColor={colors.background}
      indicatorColor={colors.backgroundElement}
      labelStyle={{ selected: { color: colors.text } }}>
      <NativeTabs.Trigger name="index">
        <NativeTabs.Trigger.Label>Keşfet</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf="safari" md="explore" />
      </NativeTabs.Trigger>

      {RESERVATIONS.map((r) => (
        <NativeTabs.Trigger key={r.id} name={r.id}>
          <NativeTabs.Trigger.Label>{r.tab}</NativeTabs.Trigger.Label>
          <NativeTabs.Trigger.Icon sf={icons[r.id].sf} md={icons[r.id].md} />
        </NativeTabs.Trigger>
      ))}

      <NativeTabs.Trigger name="iletisim">
        <NativeTabs.Trigger.Label>İletişim</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf="phone" md="call" />
      </NativeTabs.Trigger>
    </NativeTabs>
  );
}
