import { colors, RESERVATIONS, SERVICES, type Service } from '@lux/shared';
import { localePath } from '@lux/shared/i18n';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Linking, Pressable, ScrollView, StyleSheet, useWindowDimensions, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Button, Eyebrow, LangButton, Screen, ui } from '@/components/ui';
import { MaxContentWidth, Spacing } from '@/constants/theme';
import { useApp, waLink } from '@/lib/app-state';
import { stars } from '@/lib/site';

const images: Record<Service['id'], number> = {
  tur: require('@/assets/images/services/tours.jpg'),
  transfer: require('@/assets/images/services/transfer.jpg'),
  konaklama: require('@/assets/images/services/villa.jpg'),
  organizasyon: require('@/assets/images/services/events.jpg'),
  kiralama: require('@/assets/images/services/rental.jpg'),
  rehberlik: require('@/assets/images/services/guide.jpg'),
};

const bosphorusPhotos: number[] = [
  require('@/assets/images/bogaz/bogaz-01.jpg'),
  require('@/assets/images/bogaz/bogaz-02.jpg'),
  require('@/assets/images/bogaz/bogaz-03.jpg'),
  require('@/assets/images/bogaz/bogaz-04.jpg'),
  require('@/assets/images/bogaz/bogaz-05.jpg'),
  require('@/assets/images/bogaz/bogaz-06.jpg'),
];

export default function DiscoverScreen() {
  const router = useRouter();
  const { t, app, lang, content, img, whatsapp } = useApp();
  const photoWidth = Math.min(useWindowDimensions().width - Spacing.four * 2, MaxContentWidth - Spacing.four * 2);
  const hotels = content?.hotels ?? [];
  const bosphorusPath = content?.pages.find((p) => p.id === 'bogaz-tekne-turu')?.path ?? localePath(lang, '/bogaz-tekne-turu/');
  const openPage = (path: string, title: string) => router.push({ pathname: '/sayfa', params: { path, title } });

  return (
    <Screen>
      <View style={ui.row}>
        <ThemedText style={ui.brand}>LUX LOCATIONS</ThemedText>
        <LangButton />
      </View>
      <Eyebrow style={styles.gapTop}>{t.home.eyebrow.toLocaleUpperCase(lang)}</Eyebrow>
      <ThemedText style={ui.title}>{`${t.home.titleStart} ${t.home.titleEm}`}</ThemedText>
      <ThemedText style={ui.text}>{t.home.text}</ThemedText>

      <View style={styles.featured}>
        <ScrollView horizontal pagingEnabled showsHorizontalScrollIndicator={false}>
          {bosphorusPhotos.map((photo, i) => (
            <Image key={i} source={photo} style={[styles.featuredPhoto, { width: photoWidth }]} contentFit="cover" transition={200} />
          ))}
        </ScrollView>
        <View style={ui.cardBody}>
          <ThemedText style={styles.featuredKicker}>{`${t.home.featured.toLocaleUpperCase(lang)} · ${t.bosphorus.kicker}`}</ThemedText>
          <ThemedText style={[ui.cardTitle, styles.white]}>{t.bosphorus.title}</ThemedText>
          <ThemedText style={[ui.text, styles.light]}>{t.bosphorus.intro}</ThemedText>
          <View style={styles.buttons}>
            <Button light label={`${app.openPage} ↗`} onPress={() => openPage(bosphorusPath, t.bosphorus.title)} />
            <Button label={t.cta.whatsapp} onPress={() => Linking.openURL(waLink(whatsapp, t.bosphorus.whatsappMessage))} />
          </View>
        </View>
      </View>

      {hotels.length > 0 && content ? (
        <>
          <View style={[ui.row, styles.gapTop]}>
            <View>
              <Eyebrow>{content.ui.rail_kicker}</Eyebrow>
              <ThemedText style={ui.h2}>{content.ui.rail_h2}</ThemedText>
            </View>
            <Pressable onPress={() => router.navigate('/oteller')} hitSlop={8}>
              <ThemedText style={styles.link}>{content.ui.rail_all}</ThemedText>
            </Pressable>
          </View>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rail}>
            {hotels.map((h) => (
              <Pressable
                key={h.slug}
                onPress={() => router.push({ pathname: '/otel/[slug]', params: { slug: h.slug } })}
                style={({ pressed }) => [ui.card, styles.railCard, pressed && styles.pressed]}>
                {img(h.cover) ? <Image source={img(h.cover)} style={styles.railPhoto} contentFit="cover" transition={200} /> : null}
                <View style={ui.cardBody}>
                  <ThemedText style={ui.kicker}>{stars(h.stars)}</ThemedText>
                  <ThemedText style={styles.railTitle} numberOfLines={2}>
                    {h.name}
                  </ThemedText>
                  <ThemedText style={ui.text} numberOfLines={1}>
                    {h.city}
                  </ThemedText>
                </View>
              </Pressable>
            ))}
          </ScrollView>
        </>
      ) : null}

      <Eyebrow style={styles.gapTop}>{t.home.reservationsEyebrow.toLocaleUpperCase(lang)}</Eyebrow>
      <ThemedText style={ui.h2}>{t.home.reservationsTitle}</ThemedText>
      {RESERVATIONS.map((r) => (
        <Pressable
          key={r.id}
          onPress={() => router.navigate({ pathname: '/rezervasyon', params: { tip: r.id } })}
          style={({ pressed }) => [ui.card, ui.cardBody, pressed && styles.pressed]}>
          <ThemedText style={ui.kicker}>{t.reservations[r.id].kicker.toLocaleUpperCase(lang)}</ThemedText>
          <ThemedText style={ui.cardTitle}>{t.reservations[r.id].title}</ThemedText>
          <ThemedText style={ui.text}>{t.reservations[r.id].text}</ThemedText>
        </Pressable>
      ))}

      <Eyebrow style={styles.gapTop}>{t.home.servicesEyebrow.toLocaleUpperCase(lang)}</Eyebrow>
      <ThemedText style={ui.h2}>{t.home.servicesTitle}</ThemedText>
      {SERVICES.map((service, i) => (
        <View key={service.id} style={ui.card}>
          <Image source={images[service.id]} style={styles.photo} contentFit="cover" transition={200} />
          <View style={ui.cardBody}>
            <ThemedText style={ui.kicker}>{`0${i + 1} · ${t.services[service.id].kicker}`}</ThemedText>
            <ThemedText style={ui.cardTitle}>{t.services[service.id].title}</ThemedText>
            <ThemedText style={ui.text}>{t.services[service.id].text}</ThemedText>
          </View>
        </View>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create({
  gapTop: { marginTop: Spacing.three },
  featured: { backgroundColor: colors.forest, marginTop: Spacing.two },
  featuredPhoto: { aspectRatio: 16 / 10 },
  featuredKicker: { fontSize: 11, letterSpacing: 1, color: colors.copperLight },
  white: { color: colors.white },
  light: { color: colors.line },
  buttons: { gap: Spacing.two, marginTop: Spacing.two },
  link: { fontSize: 13, color: colors.gold },
  rail: { gap: Spacing.three },
  railCard: { width: 220 },
  railPhoto: { width: '100%', aspectRatio: 4 / 3 },
  railTitle: { fontFamily: 'serif', fontSize: 17, lineHeight: 23, color: colors.ink },
  photo: { width: '100%', aspectRatio: 16 / 10 },
  pressed: { opacity: 0.85 },
});
