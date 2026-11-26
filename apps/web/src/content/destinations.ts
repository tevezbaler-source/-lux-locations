import type { Locale } from "@/i18n";
import { DEST_TEXT_A } from "./destinations-text-a";
import { DEST_TEXT_B } from "./destinations-text-b";
import { DEST_TEXT_C } from "./destinations-text-c";

// Destinasyon sayfaları: /destinasyonlar/ ve /destinasyonlar/<slug>/ (11 dilde).
// Şehir adları sözlükten (t.destinations) gelir; burada her şehir için kısa tanıtım ve görülecek yerler var.
export type DestinationSlug =
  | "istanbul" | "bodrum" | "kapadokya" | "antalya" | "izmir" | "bursa" | "trabzon" | "sapanca"
  | "marmaris" | "kusadasi" | "fethiye" | "didim" | "cesme" | "erzurum" | "baku";

export interface DestinationUi {
  eyebrow: string;
  // Liste sayfası
  indexTitle: string;
  indexSeoTitle: string;
  indexIntro: string;
  // Şehir sayfası; {city} yerine şehir adı gelir
  seoTitle: string;
  kicker: string;
  arrange: string;
  arrangeText: string;
  highlights: string;
  hotels: string;
  ctaTitle: string;
  ctaText: string;
  ctaButton: string;
  others: string;
}

// [tanıtım, görülecek yerler (";" ile ayrılmış)]
export type DestinationText = [string, string];

export type DestinationLang = { ui: DestinationUi } & Record<DestinationSlug, DestinationText>;

export const DEST_TEXT: Record<Locale, DestinationLang> = { ...DEST_TEXT_A, ...DEST_TEXT_B, ...DEST_TEXT_C };
