import { colors } from '@lux/shared';
import { LOCALES } from '@lux/shared/i18n';
import { Stack, useRouter } from 'expo-router';
import { Pressable, ScrollView, StyleSheet } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useApp } from '@/lib/app-state';
import { FLAGS } from '@/lib/app-text';

export default function LanguageScreen() {
  const router = useRouter();
  const { lang, setLang, app } = useApp();
  return (
    <ScrollView style={styles.scroll} contentContainerStyle={styles.content}>
      <Stack.Screen options={{ title: app.language }} />
      {LOCALES.map((l) => (
        <Pressable
          key={l.code}
          onPress={() => {
            setLang(l.code);
            router.back();
          }}
          style={({ pressed }) => [styles.row, l.code === lang && styles.on, pressed && styles.pressed]}>
          <ThemedText style={styles.flag}>{FLAGS[l.code]}</ThemedText>
          <ThemedText style={[styles.name, l.code === lang && styles.nameOn]}>{l.name}</ThemedText>
          {l.code === lang ? <ThemedText style={styles.nameOn}>✓</ThemedText> : null}
        </Pressable>
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { flex: 1, backgroundColor: colors.paper },
  content: { padding: Spacing.three, gap: Spacing.two },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three, padding: Spacing.three, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.line },
  on: { backgroundColor: colors.ink, borderColor: colors.ink },
  pressed: { opacity: 0.8 },
  flag: { fontSize: 22, lineHeight: 28 },
  name: { flex: 1, fontSize: 16, color: colors.ink },
  nameOn: { flex: 1, fontSize: 16, color: colors.white },
});
