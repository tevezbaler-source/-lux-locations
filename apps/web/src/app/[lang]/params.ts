import { notFound } from "next/navigation";
import { isLocale, PREFIXED_LOCALES, type Locale } from "@/i18n";

// /en, /ar … sayfaları derleme sırasında üretilir; listede olmayan önek 404 verir.
export const generateLangParams = () => PREFIXED_LOCALES.map((lang) => ({ lang }));

export function toLocale(lang: string): Locale {
  if (!isLocale(lang) || lang === "tr") notFound();
  return lang;
}
