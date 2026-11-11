// Sitedeki 11 dil. Türkçe kökte (/), diğerleri kendi önekiyle (/en/, /ar/ …) yayınlanır.
export const LOCALES = [
  { code: "tr", name: "Türkçe", dir: "ltr" },
  { code: "en", name: "English", dir: "ltr" },
  { code: "ar", name: "العربية", dir: "rtl" },
  { code: "ru", name: "Русский", dir: "ltr" },
  { code: "de", name: "Deutsch", dir: "ltr" },
  { code: "fr", name: "Français", dir: "ltr" },
  { code: "es", name: "Español", dir: "ltr" },
  { code: "it", name: "Italiano", dir: "ltr" },
  { code: "zh", name: "中文", dir: "ltr" },
  { code: "fa", name: "فارسی", dir: "rtl" },
  { code: "ro", name: "Română", dir: "ltr" },
] as const;

export type Locale = (typeof LOCALES)[number]["code"];
export const DEFAULT_LOCALE: Locale = "tr";

// Önekli diller (Türkçe hariç); `[lang]` rotalarının statik parametreleri.
export const PREFIXED_LOCALES = LOCALES.filter((l) => l.code !== DEFAULT_LOCALE).map((l) => l.code);

export function isLocale(value: string): value is Locale {
  return LOCALES.some((l) => l.code === value);
}

export function localeDir(lang: Locale) {
  return LOCALES.find((l) => l.code === lang)!.dir;
}

// "/oteller" → "/en/oteller", "/#tur" → "/en/#tur", "/" → "/en/"
export function localePath(lang: Locale, path: string): string {
  return lang === DEFAULT_LOCALE ? path : `/${lang}${path}`;
}

// Arama motorları için her sayfanın diğer dillerdeki karşılıkları (hreflang).
export function languageAlternates(path: string): Record<string, string> {
  const map: Record<string, string> = { "x-default": path };
  for (const l of LOCALES) map[l.code] = localePath(l.code, path);
  return map;
}
