import { colors } from '@lux/shared';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Linking, StyleSheet, View } from 'react-native';
import { WebView, type WebViewNavigation } from 'react-native-webview';

import { SITE } from '@/lib/site';

// Sitedeki bir sayfa (ör. Boğaz turu) uygulamanın içinde açılır: panelde değişen metin ve fotoğraflar aynen görünür.
// Sitenin üst menüsü ve alt bilgisi gizlenir; gezinme uygulamanın kendi sekmelerinden yapılır.
const HIDE_CHROME = `(function(){var s=document.createElement('style');s.textContent='.site-head,footer,.site-foot,.hotel-breadcrumb{display:none!important}body{padding-top:0!important}';document.documentElement.appendChild(s);})();true;`;

export default function SitePageScreen() {
  const { path, title } = useLocalSearchParams<{ path: string; title?: string }>();
  const [loading, setLoading] = useState(true);
  const safePath = typeof path === 'string' && path.startsWith('/') ? path : '/';

  // Yalnızca site sayfaları içeride açılır; WhatsApp, telefon, e-posta ve diğer siteler telefonun kendi uygulamasında.
  function onRequest(req: WebViewNavigation) {
    const url = req.url;
    if (url.startsWith(SITE) || url.startsWith('about:')) return true;
    Linking.openURL(url).catch(() => {});
    return false;
  }

  return (
    <View style={styles.flex}>
      <Stack.Screen options={{ title: title ?? '' }} />
      <WebView
        source={{ uri: SITE + safePath }}
        style={styles.flex}
        injectedJavaScriptBeforeContentLoaded={HIDE_CHROME}
        injectedJavaScript={HIDE_CHROME}
        onShouldStartLoadWithRequest={onRequest}
        onLoadEnd={() => setLoading(false)}
        allowsInlineMediaPlayback
        mediaPlaybackRequiresUserAction
        setSupportMultipleWindows={false}
        applicationNameForUserAgent="LuxLocationsApp"
      />
      {loading ? <ActivityIndicator style={StyleSheet.absoluteFill} color={colors.gold} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({ flex: { flex: 1, backgroundColor: colors.paper } });
