import { colors } from '@lux/shared';
import { Link } from 'expo-router';
import type { ReactNode } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { MaxContentWidth, Spacing } from '@/constants/theme';
import { useApp } from '@/lib/app-state';
import { FLAGS } from '@/lib/app-text';

// Sekme ekranlarının ortak kaydırma alanı: aşağı çekince içerik siteden yenilenir.
export function Screen({ children, tab = true, top = true }: { children: ReactNode; tab?: boolean; top?: boolean }) {
  const insets = useSafeAreaInsets();
  const { rtl, refresh, status } = useApp();
  return (
    <View style={styles.scroll}>
      <ScrollView
        style={styles.scroll}
        keyboardShouldPersistTaps="handled"
        refreshControl={<RefreshControl refreshing={false} onRefresh={refresh} tintColor={colors.gold} />}
        contentContainerStyle={[
          styles.content,
          rtl && styles.rtl,
          {
            paddingTop: (top ? insets.top : 0) + Spacing.four,
            // Sekmeli ekranlarda alt boşluğu alt menü kendisi bırakır.
            paddingBottom: (tab ? 0 : insets.bottom) + Spacing.four,
          },
        ]}>
        {status === 'offline' || status === 'error' ? <StatusBanner /> : null}
        {children}
      </ScrollView>
      {/* Kaydırılan içerik saat ve pil yazılarının altından geçmesin: üstte sabit zemin şeridi */}
      {top ? <View pointerEvents="none" style={[styles.statusStrip, { height: insets.top }]} /> : null}
    </View>
  );
}

function StatusBanner() {
  const { app, refresh } = useApp();
  return (
    <Pressable onPress={refresh} style={styles.banner}>
      <ThemedText style={styles.bannerText}>{`${app.offline} ${app.retry} ↻`}</ThemedText>
    </Pressable>
  );
}

export function LangButton() {
  const { lang } = useApp();
  return (
    <Link href="/dil" asChild>
      <Pressable style={({ pressed }) => [styles.lang, pressed && styles.pressed]} hitSlop={8}>
        <ThemedText style={styles.langText}>{`${FLAGS[lang]} ${lang.toUpperCase()}`}</ThemedText>
      </Pressable>
    </Link>
  );
}

export function Eyebrow({ children, style }: { children: ReactNode; style?: StyleProp<ViewStyle> }) {
  return (
    <View style={style}>
      <ThemedText style={styles.eyebrow}>{children}</ThemedText>
    </View>
  );
}

export function Button({ label, onPress, light }: { label: string; onPress: () => void; light?: boolean }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.button, light && styles.buttonLight, pressed && styles.pressed]}>
      <ThemedText style={[styles.buttonText, light && styles.buttonTextLight]}>{label}</ThemedText>
    </Pressable>
  );
}

export const ui = StyleSheet.create({
  brand: { fontFamily: 'serif', fontSize: 20, letterSpacing: 3, color: colors.ink },
  title: { fontFamily: 'serif', fontSize: 32, lineHeight: 40, color: colors.ink },
  h2: { fontFamily: 'serif', fontSize: 24, lineHeight: 32, color: colors.ink },
  cardTitle: { fontFamily: 'serif', fontSize: 20, lineHeight: 28, color: colors.ink },
  text: { fontSize: 14, lineHeight: 21, color: colors.muted },
  kicker: { fontSize: 11, letterSpacing: 1, color: colors.gold },
  card: { backgroundColor: colors.white, borderWidth: 1, borderColor: colors.line },
  cardBody: { padding: Spacing.three, gap: Spacing.one },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
});

const styles = StyleSheet.create({
  scroll: { flex: 1, backgroundColor: colors.paper },
  statusStrip: { position: 'absolute', top: 0, left: 0, right: 0, backgroundColor: colors.paper },
  content: { paddingHorizontal: Spacing.four, gap: Spacing.three, width: '100%', maxWidth: MaxContentWidth, alignSelf: 'center' },
  rtl: { direction: 'rtl' },
  banner: { backgroundColor: colors.forest, padding: Spacing.two },
  bannerText: { color: colors.white, fontSize: 12, lineHeight: 18 },
  lang: { borderWidth: 1, borderColor: colors.line, paddingHorizontal: Spacing.two, paddingVertical: Spacing.one, backgroundColor: colors.white },
  langText: { fontSize: 13, color: colors.ink },
  eyebrow: { fontSize: 11, letterSpacing: 2, color: colors.gold },
  button: { backgroundColor: colors.ink, paddingVertical: Spacing.three, paddingHorizontal: Spacing.three, alignItems: 'center' },
  buttonLight: { backgroundColor: colors.white },
  buttonText: { color: colors.white, fontSize: 14, letterSpacing: 1, textAlign: 'center' },
  buttonTextLight: { color: colors.ink },
  pressed: { opacity: 0.8 },
});
