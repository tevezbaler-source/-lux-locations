import { colors } from '@lux/shared';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Button, Eyebrow, LangButton, Screen, ui } from '@/components/ui';
import { Spacing } from '@/constants/theme';
import { useApp } from '@/lib/app-state';
import { sprintf, stars } from '@/lib/site';

// Oteller paneldeki listeden canlı gelir (yıldız sayısına göre, panel sırasıyla).
export default function HotelsScreen() {
  const router = useRouter();
  const { content, status, app, img, refresh } = useApp();
  const [filter, setFilter] = useState(0);

  if (!content) {
    return (
      <Screen>
        {status === 'loading' ? (
          <ActivityIndicator color={colors.gold} style={styles.loading} />
        ) : (
          <>
            <ThemedText style={ui.text}>{app.noHotels}</ThemedText>
            <Button label={app.retry} onPress={refresh} />
          </>
        )}
      </Screen>
    );
  }

  const u = content.ui;
  const all = content.hotels;
  const starOptions = [...new Set(all.map((h) => h.stars).filter((n) => n > 0))].sort((a, b) => b - a);
  const list = filter ? all.filter((h) => h.stars === filter) : all;

  return (
    <Screen>
      <View style={ui.row}>
        <Eyebrow>{u.list_eyebrow}</Eyebrow>
        <LangButton />
      </View>
      <ThemedText style={ui.title}>{`${u.list_h1a} ${u.list_h1b}`}</ThemedText>
      <ThemedText style={ui.text}>{u.list_lead}</ThemedText>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
        {[0, ...starOptions].map((n) => (
          <Pressable key={n} onPress={() => setFilter(n)} style={[styles.chip, filter === n && styles.chipOn]}>
            <ThemedText style={[styles.chipText, filter === n && styles.chipTextOn]}>
              {n ? sprintf(u.n_star_filter, n) : u.all_hotels}
            </ThemedText>
          </Pressable>
        ))}
      </ScrollView>
      <ThemedText style={ui.text}>{sprintf(u.count, list.length)}</ThemedText>

      {list.length === 0 ? <ThemedText style={ui.text}>{u.empty_h}</ThemedText> : null}
      {list.map((h) => (
        <Pressable
          key={h.slug}
          onPress={() => router.push({ pathname: '/otel/[slug]', params: { slug: h.slug } })}
          style={({ pressed }) => [ui.card, pressed && styles.pressed]}>
          {img(h.cover) ? <Image source={img(h.cover)} style={styles.photo} contentFit="cover" transition={200} /> : null}
          <View style={ui.cardBody}>
            <ThemedText style={ui.kicker}>{`${stars(h.stars)}  ${h.city}`}</ThemedText>
            <ThemedText style={ui.cardTitle}>{h.name}</ThemedText>
            <ThemedText style={ui.text} numberOfLines={3}>
              {h.summary}
            </ThemedText>
            {h.features.length > 0 ? (
              <View style={styles.tags}>
                {h.features.slice(0, 3).map((f) => (
                  <ThemedText key={f} style={styles.tag}>
                    {f}
                  </ThemedText>
                ))}
              </View>
            ) : null}
            <ThemedText style={styles.link}>{`${u.view} ↗`}</ThemedText>
          </View>
        </Pressable>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create({
  loading: { marginTop: Spacing.six },
  chips: { gap: Spacing.two },
  chip: { borderWidth: 1, borderColor: colors.line, paddingHorizontal: Spacing.three, paddingVertical: Spacing.two, backgroundColor: colors.white },
  chipOn: { backgroundColor: colors.ink, borderColor: colors.ink },
  chipText: { fontSize: 13, color: colors.ink },
  chipTextOn: { color: colors.white },
  photo: { width: '100%', aspectRatio: 3 / 2 },
  tags: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.one, marginTop: Spacing.one },
  tag: { fontSize: 12, color: colors.forest, backgroundColor: colors.paper, paddingHorizontal: Spacing.two, paddingVertical: 2 },
  link: { fontSize: 13, color: colors.gold, marginTop: Spacing.two },
  pressed: { opacity: 0.85 },
});
