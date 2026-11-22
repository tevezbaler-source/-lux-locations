import { colors } from '@lux/shared';
import { Image } from 'expo-image';
import { Stack, useLocalSearchParams } from 'expo-router';
import { Linking, ScrollView, StyleSheet, useWindowDimensions, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Button, Eyebrow, Screen, ui } from '@/components/ui';
import { MaxContentWidth, Spacing } from '@/constants/theme';
import { useApp, waLink } from '@/lib/app-state';
import { absolute, sprintf, stars } from '@/lib/site';

export default function HotelScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { content, img, whatsapp, app } = useApp();
  const width = Math.min(useWindowDimensions().width - Spacing.four * 2, MaxContentWidth - Spacing.four * 2);
  const h = content?.hotels.find((x) => x.slug === slug);

  if (!content || !h) {
    return (
      <Screen tab={false} top={false}>
        <ThemedText style={ui.text}>{content ? content.ui.empty_h : app.loading}</ThemedText>
      </Screen>
    );
  }

  const u = content.ui;
  const photos = [
    ...(h.cover ? [{ src: h.cover, caption: sprintf(u.cover_alt, h.name) }] : []),
    ...h.galleries.flatMap((g) => g.photos.map((p) => ({ src: p.src, caption: p.caption || g.title }))),
  ].filter((p) => img(p.src));

  return (
    <Screen tab={false} top={false}>
      <Stack.Screen options={{ title: h.name }} />
      {photos.length > 0 ? (
        <View>
          <ScrollView horizontal pagingEnabled showsHorizontalScrollIndicator={false}>
            {photos.map((p, i) => (
              <View key={`${p.src}-${i}`} style={{ width }}>
                <Image source={img(p.src)} style={styles.photo} contentFit="cover" transition={200} />
                <ThemedText style={styles.caption} numberOfLines={1}>{`${i + 1} / ${photos.length} · ${p.caption}`}</ThemedText>
              </View>
            ))}
          </ScrollView>
          {h.coverAi ? <ThemedText style={styles.note}>{u.ai_note}</ThemedText> : null}
        </View>
      ) : null}

      {h.stars > 0 ? <ThemedText style={ui.kicker}>{`${stars(h.stars)}  ${sprintf(u.stars_hotel, h.stars)}`}</ThemedText> : null}
      <ThemedText style={ui.title}>{h.name}</ThemedText>
      {h.city ? <ThemedText style={ui.text}>{h.city}</ThemedText> : null}
      <Button label={u.reserve} onPress={() => Linking.openURL(waLink(whatsapp, sprintf(u.wa_msg, h.name)))} />

      <Eyebrow style={styles.gapTop}>{u.nav_about.toLocaleUpperCase(content.lang)}</Eyebrow>
      <ThemedText style={styles.body}>{h.description}</ThemedText>

      {h.rooms.length > 0 ? (
        <>
          <Eyebrow style={styles.gapTop}>{u.rooms_eyebrow}</Eyebrow>
          <ThemedText style={ui.h2}>{u.rooms_h2}</ThemedText>
          {h.rooms.map((r) => (
            <View key={r.name} style={[ui.card, ui.cardBody]}>
              <ThemedText style={ui.cardTitle}>{r.name}</ThemedText>
              {r.size ? <ThemedText style={ui.kicker}>{r.size}</ThemedText> : null}
              {r.desc ? <ThemedText style={ui.text}>{r.desc}</ThemedText> : null}
              <ThemedText
                style={styles.link}
                onPress={() => Linking.openURL(waLink(whatsapp, sprintf(u.wa_room, h.name, r.name)))}>
                {u.room_quote}
              </ThemedText>
            </View>
          ))}
        </>
      ) : null}

      {h.amenities.length > 0 ? (
        <>
          <Eyebrow style={styles.gapTop}>{u.amen_eyebrow}</Eyebrow>
          <View style={styles.tags}>
            {h.amenities.map((a) => (
              <ThemedText key={a} style={styles.tag}>
                {a}
              </ThemedText>
            ))}
          </View>
        </>
      ) : null}

      {h.address || h.map ? (
        <>
          <Eyebrow style={styles.gapTop}>{u.loc_h2.toLocaleUpperCase(content.lang)}</Eyebrow>
          {h.address ? <ThemedText style={styles.body}>{h.address}</ThemedText> : null}
          {h.map ? <Button light label={u.map} onPress={() => Linking.openURL(h.map)} /> : null}
        </>
      ) : null}

      <View style={[styles.cta, styles.gapTop]}>
        <ThemedText style={[ui.h2, styles.white]}>{u.res_h2.replace(/<br\s*\/?>/g, ' ')}</ThemedText>
        <ThemedText style={[ui.text, styles.light]}>{u.res_p}</ThemedText>
        <Button light label={u.res_wa} onPress={() => Linking.openURL(waLink(whatsapp, sprintf(u.wa_msg, h.name)))} />
        {h.booking ? <Button label={u.res_booking} onPress={() => Linking.openURL(absolute(h.booking))} /> : null}
        {h.website ? <Button label={u.website} onPress={() => Linking.openURL(absolute(h.website))} /> : null}
        <ThemedText style={[styles.note, styles.light]}>{u.res_small}</ThemedText>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  gapTop: { marginTop: Spacing.three },
  photo: { width: '100%', aspectRatio: 3 / 2, backgroundColor: colors.line },
  caption: { fontSize: 12, color: colors.muted, marginTop: Spacing.one },
  note: { fontSize: 11, lineHeight: 16, color: colors.muted, marginTop: Spacing.one },
  body: { fontSize: 15, lineHeight: 23, color: colors.ink },
  link: { fontSize: 13, color: colors.gold, marginTop: Spacing.two },
  tags: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two },
  tag: { fontSize: 13, color: colors.forest, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.line, paddingHorizontal: Spacing.two, paddingVertical: Spacing.one },
  cta: { backgroundColor: colors.forest, padding: Spacing.three, gap: Spacing.two },
  white: { color: colors.white },
  light: { color: colors.line },
});
