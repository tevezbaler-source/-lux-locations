import { colors } from '@lux/shared';
import { Tabs } from 'expo-router';
import type { BottomTabBarProps } from 'expo-router/build/layouts/Tabs';
import { SymbolView, type AndroidSymbol, type SFSymbol } from 'expo-symbols';
import { Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useApp } from '@/lib/app-state';

// Logonun zemin rengi: seçili sekme bu renkle, üstünde ince bakır çizgi.
const ACTIVE = '#3a5f78';

type Icon = { sf: SFSymbol; sfActive: SFSymbol; md: AndroidSymbol };

const ICONS: Record<string, Icon> = {
  index: { sf: 'safari', sfActive: 'safari.fill', md: 'explore' },
  oteller: { sf: 'building.2', sfActive: 'building.2.fill', md: 'apartment' },
  rezervasyon: { sf: 'calendar', sfActive: 'calendar', md: 'calendar_month' },
  iletisim: { sf: 'bubble.left.and.bubble.right', sfActive: 'bubble.left.and.bubble.right.fill', md: 'forum' },
};

export default function AppTabs() {
  const { t, app } = useApp();
  return (
    <Tabs
      tabBar={(props) => <LuxTabBar {...props} />}
      screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: colors.paper } }}>
      <Tabs.Screen name="index" options={{ title: app.discover }} />
      <Tabs.Screen name="oteller" options={{ title: t.nav.hotels }} />
      <Tabs.Screen name="rezervasyon" options={{ title: app.reserve }} />
      <Tabs.Screen name="iletisim" options={{ title: t.nav.contact }} />
    </Tabs>
  );
}

function LuxTabBar({ state, descriptors, emitter, navigateToTab }: BottomTabBarProps) {
  const insets = useSafeAreaInsets();
  return (
    <View style={[styles.bar, { paddingBottom: Math.max(insets.bottom, 8) }]}>
      {state.routes.map((route, index) => {
        const focused = state.index === index;
        const icon = ICONS[route.name] ?? ICONS.index;
        const label = descriptors[route.key].options.title ?? route.name;
        const tint = focused ? ACTIVE : colors.muted;
        const onPress = () => {
          const event = emitter.emit({ type: 'tabPress', target: route.key, canPreventDefault: true });
          if (!focused && !event.defaultPrevented) navigateToTab(route.key);
        };
        return (
          <Pressable
            key={route.key}
            onPress={onPress}
            accessibilityRole="tab"
            accessibilityState={{ selected: focused }}
            accessibilityLabel={label}
            style={({ pressed }) => [styles.item, pressed && styles.pressed]}>
            <View style={[styles.indicator, focused && styles.indicatorOn]} />
            <SymbolView
              name={{ ios: focused ? icon.sfActive : icon.sf, android: icon.md, web: icon.md }}
              size={24}
              tintColor={tint}
              weight={Platform.OS === 'ios' ? (focused ? 'semibold' : 'regular') : undefined}
              fallback={<View style={styles.iconBox} />}
            />
            <Text numberOfLines={1} style={[styles.label, { color: tint }, focused && styles.labelOn]}>
              {label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    backgroundColor: '#ffffff',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.line,
    paddingHorizontal: 6,
  },
  item: {
    flex: 1,
    alignItems: 'center',
    paddingTop: 8,
    paddingBottom: 4,
    gap: 3,
  },
  pressed: { opacity: 0.6 },
  indicator: {
    position: 'absolute',
    top: 0,
    width: 28,
    height: 3,
    borderBottomLeftRadius: 3,
    borderBottomRightRadius: 3,
    backgroundColor: 'transparent',
  },
  indicatorOn: { backgroundColor: colors.copper },
  iconBox: { width: 24, height: 24 },
  label: { fontSize: 11.5, fontWeight: '500', letterSpacing: 0.2 },
  labelOn: { fontWeight: '700' },
});
