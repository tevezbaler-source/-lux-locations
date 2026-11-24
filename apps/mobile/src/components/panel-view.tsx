import { colors } from '@lux/shared';
import { useState } from 'react';
import { ActivityIndicator, Linking, StyleSheet, View } from 'react-native';
import { WebView, type WebViewMessageEvent, type WebViewNavigation } from 'react-native-webview';

import { setAdmin } from '@/lib/admin';
import { SITE } from '@/lib/site';

// Sitedeki tek yönetim paneli (/admin/site/) uygulamanın içinde açılır: Talepler bölümüyle başlar, soldan site düzenlemeye geçilir.
// Giriş başarılıysa (sayfada "Çıkış" düğmesi varsa) telefon ekip moduna geçer; "Çıkış"a basılınca çıkar.
const WATCH = `(function(){
  var post=function(m){window.ReactNativeWebView&&window.ReactNativeWebView.postMessage(m)};
  var out=document.querySelector('input[name=action][value=logout]');
  if(out){post('in');out.form.addEventListener('submit',function(){post('out')});}
})();true;`;

export function PanelView({ path = '/admin/site/?page=talepler' }: { path?: string }) {
  const [loading, setLoading] = useState(true);

  function onMessage(e: WebViewMessageEvent) {
    if (e.nativeEvent.data === 'in') setAdmin(true);
    if (e.nativeEvent.data === 'out') setAdmin(false);
  }

  // Panel ve site sayfaları içeride; dış bağlantılar telefonun kendi uygulamasında açılır.
  function onRequest(req: WebViewNavigation) {
    if (req.url.startsWith(SITE) || req.url.startsWith('about:')) return true;
    Linking.openURL(req.url).catch(() => {});
    return false;
  }

  return (
    <View style={styles.flex}>
      <WebView
        source={{ uri: SITE + path }}
        style={styles.flex}
        injectedJavaScript={WATCH}
        onMessage={onMessage}
        onShouldStartLoadWithRequest={onRequest}
        onLoadEnd={() => setLoading(false)}
        sharedCookiesEnabled
        thirdPartyCookiesEnabled
        setSupportMultipleWindows={false}
        applicationNameForUserAgent="LuxLocationsApp"
      />
      {loading ? <ActivityIndicator style={StyleSheet.absoluteFill} color={colors.gold} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({ flex: { flex: 1, backgroundColor: colors.paper } });
