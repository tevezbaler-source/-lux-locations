import { colors, CONTACT } from '@lux/shared';
import { Linking, StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Button, Eyebrow, LangButton, Screen, ui } from '@/components/ui';
import { Spacing } from '@/constants/theme';
import { useApp, waLink } from '@/lib/app-state';
import { SITE } from '@/lib/site';

export default function ContactScreen() {
  const { t, app, lang, whatsapp } = useApp();
  const social = [
    { label: 'Instagram', url: CONTACT.social.instagram },
    { label: 'Facebook', url: CONTACT.social.facebook },
    { label: 'LinkedIn', url: CONTACT.social.linkedin },
  ];

  return (
    <Screen>
      <View style={ui.row}>
        <ThemedText style={ui.brand}>LUX LOCATIONS</ThemedText>
        <LangButton />
      </View>
      <ThemedText style={[ui.title, styles.gapTop]}>{t.nav.contact}</ThemedText>
      <ThemedText style={ui.text}>{app.contactLead}</ThemedText>
      <Button label={t.cta.whatsapp} onPress={() => Linking.openURL(waLink(whatsapp))} />
      <Button light label={`${app.call} · ${CONTACT.phoneDisplay}`} onPress={() => Linking.openURL(`tel:${CONTACT.phone}`)} />
      <Button light label={`${app.mail} · ${CONTACT.email}`} onPress={() => Linking.openURL(`mailto:${CONTACT.email}`)} />

      <Eyebrow style={styles.gapTop}>{app.address.toLocaleUpperCase(lang)}</Eyebrow>
      <ThemedText style={styles.body}>{CONTACT.address}</ThemedText>

      <Eyebrow style={styles.gapTop}>{app.follow.toLocaleUpperCase(lang)}</Eyebrow>
      <View style={styles.social}>
        {social.map((s) => (
          <ThemedText key={s.label} style={styles.link} onPress={() => Linking.openURL(s.url)}>
            {`${s.label} ↗`}
          </ThemedText>
        ))}
      </View>
      <ThemedText style={[styles.link, styles.gapTop]} onPress={() => Linking.openURL(`${SITE}/gizlilik/`)}>
        {app.privacy}
      </ThemedText>
      <ThemedText style={styles.small}>{`© Lux Locations · ${t.footer.rights}`}</ThemedText>
    </Screen>
  );
}

const styles = StyleSheet.create({
  gapTop: { marginTop: Spacing.three },
  body: { fontSize: 15, lineHeight: 23, color: colors.ink },
  social: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.four },
  link: { fontSize: 15, color: colors.gold },
  small: { fontSize: 11, color: colors.muted, marginTop: Spacing.four },
});
