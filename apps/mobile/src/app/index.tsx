import { BOSPHORUS_DINNER, colors, SERVICES, whatsappLink, type Service } from '@lux/shared';
import { Image } from 'expo-image';
import { Linking, Pressable, ScrollView, StyleSheet, useWindowDimensions, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { BottomTabInset, MaxContentWidth, Spacing } from '@/constants/theme';

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
  require('@/assets/images/bogaz/bogaz-07.jpg'),
  require('@/assets/images/bogaz/bogaz-08.jpg'),
  require('@/assets/images/bogaz/bogaz-09.jpg'),
  require('@/assets/images/bogaz/bogaz-10.jpg'),
  require('@/assets/images/bogaz/bogaz-11.jpg'),
  require('@/assets/images/bogaz/bogaz-12.jpg'),
];

export default function DiscoverScreen() {
  const insets = useSafeAreaInsets();
  // Kaydırmalı galeride her fotoğraf kartın tam genişliğini kaplar.
  const photoWidth = Math.min(useWindowDimensions().width - Spacing.four * 2, MaxContentWidth - Spacing.four * 2);

  return (
    <ScrollView
      style={styles.scroll}
      contentContainerStyle={[
        styles.content,
        { paddingTop: insets.top + Spacing.four, paddingBottom: insets.bottom + BottomTabInset + Spacing.four },
      ]}>
      <ThemedText style={styles.brand}>LUX LOCATIONS</ThemedText>
      <ThemedText style={styles.eyebrow}>SİZE ÖZEL SEYAHAT</ThemedText>
      <ThemedText style={styles.title}>Yolculuğunuz, size özel.</ThemedText>

      <View style={styles.featured}>
        <ScrollView horizontal pagingEnabled showsHorizontalScrollIndicator={false}>
          {bosphorusPhotos.map((photo, i) => (
            <Image key={i} source={photo} style={[styles.featuredPhoto, { width: photoWidth }]} contentFit="cover" transition={200} />
          ))}
        </ScrollView>
        <View style={styles.cardBody}>
          <ThemedText style={styles.featuredKicker}>{`ÖNE ÇIKAN · ${BOSPHORUS_DINNER.kicker}`}</ThemedText>
          <ThemedText style={[styles.cardTitle, styles.featuredText]}>{BOSPHORUS_DINNER.title}</ThemedText>
          <ThemedText style={[styles.cardText, styles.featuredMuted]}>{BOSPHORUS_DINNER.intro}</ThemedText>
          <Pressable
            onPress={() => Linking.openURL(whatsappLink(BOSPHORUS_DINNER.whatsappMessage))}
            style={({ pressed }) => [styles.featuredButton, pressed && { opacity: 0.8 }]}>
            <ThemedText style={styles.featuredButtonText}>WhatsApp ile bilgi alın</ThemedText>
          </Pressable>
        </View>
      </View>

      {SERVICES.map((service, i) => (
        <View key={service.id} style={styles.card}>
          <Image source={images[service.id]} style={styles.photo} contentFit="cover" transition={200} />
          <View style={styles.cardBody}>
            <ThemedText style={styles.kicker}>{`0${i + 1} · ${service.kicker}`}</ThemedText>
            <ThemedText style={styles.cardTitle}>{service.title}</ThemedText>
            <ThemedText themeColor="textSecondary" style={styles.cardText}>
              {service.text}
            </ThemedText>
          </View>
        </View>
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { flex: 1, backgroundColor: colors.paper },
  content: {
    paddingHorizontal: Spacing.four,
    gap: Spacing.three,
    width: '100%',
    maxWidth: MaxContentWidth,
    alignSelf: 'center',
  },
  brand: { fontFamily: 'serif', fontSize: 20, letterSpacing: 3, color: colors.ink },
  eyebrow: { marginTop: Spacing.four, fontSize: 11, letterSpacing: 2, color: colors.gold },
  title: { fontFamily: 'serif', fontSize: 34, lineHeight: 42, color: colors.ink, marginBottom: Spacing.three },
  card: { backgroundColor: colors.white, borderWidth: 1, borderColor: colors.line },
  photo: { width: '100%', aspectRatio: 16 / 10 },
  cardBody: { padding: Spacing.three, gap: Spacing.one },
  kicker: { fontSize: 11, letterSpacing: 1, color: colors.gold },
  cardTitle: { fontFamily: 'serif', fontSize: 22, lineHeight: 30, color: colors.ink },
  cardText: { fontSize: 14, lineHeight: 21 },
  featured: { backgroundColor: colors.forest, marginBottom: Spacing.three },
  featuredPhoto: { aspectRatio: 16 / 10 },
  featuredKicker: { fontSize: 11, letterSpacing: 1, color: colors.copperLight },
  featuredText: { color: colors.white },
  featuredMuted: { color: colors.line },
  featuredButton: { marginTop: Spacing.two, backgroundColor: colors.white, paddingVertical: Spacing.three, alignItems: 'center' },
  featuredButtonText: { color: colors.ink, fontSize: 14, letterSpacing: 1 },
});
