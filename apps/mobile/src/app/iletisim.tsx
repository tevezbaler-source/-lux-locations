import { colors, CONTACT } from '@lux/shared';
import { Linking, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { MaxContentWidth, Spacing } from '@/constants/theme';

const actions = [
  { label: 'WhatsApp ile yazın', url: `https://wa.me/${CONTACT.whatsapp.replace('+', '')}` },
  { label: 'Arayın', url: `tel:${CONTACT.phone}` },
  { label: 'E-posta gönderin', url: `mailto:${CONTACT.email}` },
];

export default function ContactScreen() {
  const insets = useSafeAreaInsets();

  return (
    <View style={[styles.container, { paddingTop: insets.top + Spacing.four }]}>
      <ThemedText style={styles.title}>İletişim</ThemedText>
      <ThemedText themeColor="textSecondary" style={styles.text}>
        Talebinizi ekibimize iletin, size özel teklifle dönelim.
      </ThemedText>
      {actions.map((a) => (
        <Pressable
          key={a.url}
          onPress={() => Linking.openURL(a.url)}
          style={({ pressed }) => [styles.button, pressed && styles.pressed]}>
          <ThemedText style={styles.buttonText}>{a.label}</ThemedText>
        </Pressable>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.paper,
    paddingHorizontal: Spacing.four,
    gap: Spacing.three,
    width: '100%',
    maxWidth: MaxContentWidth,
    alignSelf: 'center',
  },
  title: { fontFamily: 'serif', fontSize: 34, lineHeight: 42, color: colors.ink },
  text: { fontSize: 14, lineHeight: 21, marginBottom: Spacing.three },
  button: { backgroundColor: colors.ink, paddingVertical: Spacing.three, alignItems: 'center' },
  pressed: { opacity: 0.8 },
  buttonText: { color: colors.white, fontSize: 14, letterSpacing: 1 },
});
