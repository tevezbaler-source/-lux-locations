import { NativeTabs } from 'expo-router/native-tabs';
import { useColorScheme } from 'react-native';

import { Colors } from '@/constants/theme';
import { useApp } from '@/lib/app-state';

// Simgeler iOS'ta SF Symbols, Android'de Material simgelerinden gelir.
export default function AppTabs() {
  const scheme = useColorScheme();
  const colors = Colors[scheme === 'dark' ? 'dark' : 'light'];
  const { t, app } = useApp();

  return (
    <NativeTabs
      backgroundColor={colors.background}
      indicatorColor={colors.backgroundElement}
      labelStyle={{ selected: { color: colors.text } }}>
      <NativeTabs.Trigger name="index">
        <NativeTabs.Trigger.Label>{app.discover}</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf="safari" md="explore" />
      </NativeTabs.Trigger>
      <NativeTabs.Trigger name="oteller">
        <NativeTabs.Trigger.Label>{t.nav.hotels}</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf="bed.double" md="hotel" />
      </NativeTabs.Trigger>
      <NativeTabs.Trigger name="rezervasyon">
        <NativeTabs.Trigger.Label>{app.reserve}</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf="calendar" md="event" />
      </NativeTabs.Trigger>
      <NativeTabs.Trigger name="iletisim">
        <NativeTabs.Trigger.Label>{t.nav.contact}</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf="phone" md="call" />
      </NativeTabs.Trigger>
    </NativeTabs>
  );
}
