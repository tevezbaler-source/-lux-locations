import type { Metadata } from "next";
import { CONTACT } from "@lux/shared";
import { languageAlternates, localePath, type Locale } from "@/i18n";

// Her sayfa için başlık, açıklama ve diğer dillerdeki karşılıkları (hreflang).
export function pageMetadata(lang: Locale, path: string, title: string, description: string): Metadata {
  return {
    metadataBase: new URL(CONTACT.website),
    title,
    description,
    alternates: { canonical: localePath(lang, path), languages: languageAlternates(path) },
  };
}
