import AsyncStorage from '@react-native-async-storage/async-storage';
import { getDict, isLocale, localeDir, type Dict, type Locale } from '@lux/shared/i18n';
import { createContext, use, useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { AppState } from 'react-native';

import { APP_TEXT, type AppText } from './app-text';
import { absolute, apiUrl, isSiteContent, type SiteContent } from './site';

type Status = 'loading' | 'ready' | 'offline' | 'error';

interface AppContextValue {
  lang: Locale;
  setLang: (lang: Locale) => void;
  rtl: boolean;
  t: Dict;
  app: AppText;
  content: SiteContent | null;
  status: Status;
  refresh: () => Promise<void>;
  /** Site görselinin tam adresi; panelde değiştirilen görsel yenisiyle gelir, gizlenen görsel boş döner. */
  img: (src: string) => string;
  whatsapp: string;
}

const AppContext = createContext<AppContextValue | null>(null);

const LANG_KEY = 'lux:lang';
const cacheKey = (lang: Locale) => `lux:content:${lang}`;

function deviceLocale(): Locale {
  try {
    const code = Intl.DateTimeFormat().resolvedOptions().locale.slice(0, 2).toLowerCase();
    return isLocale(code) ? code : 'en';
  } catch {
    return 'en';
  }
}

// Panelde değiştirilen metinler (eski metin => yeni metin) uygulamanın metinlerine de uygulanır.
function applyTexts<T>(value: T, texts: Record<string, string>): T {
  if (typeof value === 'string') return (texts[value] ?? value) as T;
  if (Array.isArray(value)) return value.map((v) => applyTexts(v, texts)) as T;
  if (value && typeof value === 'object') {
    const out: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(value)) out[k] = applyTexts(v, texts);
    return out as T;
  }
  return value;
}

interface Cached {
  etag: string | null;
  data: SiteContent;
}

export function AppProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<Locale | null>(null);
  const [content, setContent] = useState<SiteContent | null>(null);
  const [status, setStatus] = useState<Status>('loading');
  const etagRef = useRef<string | null>(null);
  const langRef = useRef<Locale | null>(null);

  // İlk açılış: kayıtlı dil, yoksa telefonun dili
  useEffect(() => {
    AsyncStorage.getItem(LANG_KEY)
      .then((saved) => setLangState(saved && isLocale(saved) ? saved : deviceLocale()))
      .catch(() => setLangState(deviceLocale()));
  }, []);

  const load = useCallback(async (l: Locale) => {
    try {
      const headers: Record<string, string> = { Accept: 'application/json' };
      if (etagRef.current) headers['If-None-Match'] = etagRef.current;
      const res = await fetch(apiUrl(l), { headers });
      if (langRef.current !== l) return;
      if (res.status === 304) {
        setStatus('ready');
        return;
      }
      if (!res.ok) throw new Error(String(res.status));
      const data: unknown = await res.json();
      if (!isSiteContent(data)) throw new Error('bad data');
      if (langRef.current !== l) return;
      etagRef.current = res.headers.get('etag');
      setContent(data);
      setStatus('ready');
      AsyncStorage.setItem(cacheKey(l), JSON.stringify({ etag: etagRef.current, data } satisfies Cached)).catch(() => {});
    } catch {
      if (langRef.current === l) setStatus((s) => (s === 'loading' ? 'error' : 'offline'));
    }
  }, []);

  // Dil değişince: önce telefondaki son içerik, sonra siteden güncel olanı
  useEffect(() => {
    if (!lang) return;
    langRef.current = lang;
    etagRef.current = null;
    let cancelled = false;
    (async () => {
      setStatus('loading');
      try {
        const raw = await AsyncStorage.getItem(cacheKey(lang));
        const cached = raw ? (JSON.parse(raw) as Cached) : null;
        if (!cancelled && cached && isSiteContent(cached.data)) {
          etagRef.current = cached.etag;
          setContent(cached.data);
          setStatus('offline'); // siteden yanıt gelene kadar
        } else if (!cancelled) {
          setContent(null);
        }
      } catch {}
      if (!cancelled) await load(lang);
    })();
    return () => {
      cancelled = true;
    };
  }, [lang, load]);

  // Uygulamaya her dönüşte içerik yenilenir
  useEffect(() => {
    const sub = AppState.addEventListener('change', (s) => {
      if (s === 'active' && langRef.current) load(langRef.current);
    });
    return () => sub.remove();
  }, [load]);

  const setLang = useCallback((l: Locale) => {
    setLangState(l);
    AsyncStorage.setItem(LANG_KEY, l).catch(() => {});
  }, []);

  const value = useMemo<AppContextValue | null>(() => {
    if (!lang) return null;
    const live = content && content.lang === lang ? content : null;
    const texts = live?.texts ?? {};
    const images = live?.images ?? {};
    const hidden = new Set(live?.hidden ?? []);
    return {
      lang,
      setLang,
      rtl: localeDir(lang) === 'rtl',
      t: applyTexts(getDict(lang), texts),
      app: APP_TEXT[lang],
      content: live,
      status,
      refresh: () => load(lang),
      img: (src: string) => {
        const s = images[src] ?? src;
        return hidden.has(src) || hidden.has(s) ? '' : absolute(s);
      },
      whatsapp: live?.whatsapp ?? '905396114190',
    };
  }, [lang, content, status, setLang, load]);

  if (!value) return null;
  return <AppContext value={value}>{children}</AppContext>;
}

export function useApp() {
  const ctx = use(AppContext);
  if (!ctx) throw new Error('AppProvider eksik');
  return ctx;
}

export function waLink(number: string, text?: string) {
  const base = `https://wa.me/${number.replace(/\D/g, '')}`;
  return text ? `${base}?text=${encodeURIComponent(text)}` : base;
}
