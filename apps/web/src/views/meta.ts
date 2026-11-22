import type { Metadata } from "next";
import { LOCALES, languageAlternates, localePath, type Locale } from "@/i18n";
import { DEFAULT_SHARE_IMAGE, OG_LOCALE, SITE_NAME, SITE_URL } from "./seo";

// Her sayfa için başlık, açıklama, diğer dillerdeki karşılıkları (hreflang)
// ve paylaşım önizlemesi (Open Graph / X kartı).
export function pageMetadata(
  lang: Locale,
  path: string,
  title: string,
  description: string,
  image: string = DEFAULT_SHARE_IMAGE,
): Metadata {
  const url = localePath(lang, path);
  return {
    metadataBase: new URL(SITE_URL),
    title,
    description,
    alternates: { canonical: url, languages: languageAlternates(path) },
    openGraph: {
      type: "website",
      siteName: SITE_NAME,
      url,
      title,
      description,
      locale: OG_LOCALE[lang],
      alternateLocale: LOCALES.filter((l) => l.code !== lang).map((l) => OG_LOCALE[l.code]),
      images: [{ url: image, alt: title }],
    },
    twitter: { card: "summary_large_image", title, description, images: [image] },
    robots: { index: true, follow: true, "max-image-preview": "large" },
  };
}
