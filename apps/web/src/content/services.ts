import type { FieldType } from "@lux/shared";
import type { Locale } from "@/i18n";
import { SERVICE_TEXT_A } from "./services-text-a";
import { SERVICE_TEXT_B } from "./services-text-b";

// Hizmet sayfaları: /hizmetler/<slug>/ (11 dilde). Google'da "İstanbul transfer", "private tour Istanbul"
// gibi aramalarda çıkabilmek için her hizmetin kendi sayfası, açıklaması ve sık sorulan soruları var.
export type ServiceSlug = "ozel-tur" | "transfer" | "kiralama" | "rehberlik";

export interface ServicePage {
  slug: ServiceSlug;
  // Talep kaydındaki kategori (admin/db.php CATEGORIES)
  category: "tur" | "transfer" | "kiralama" | "rehberlik";
  image: string;
  fields: { key: string; type: FieldType; optional?: boolean }[];
}

export const SERVICE_PAGES: ServicePage[] = [
  {
    slug: "ozel-tur",
    category: "tur",
    image: "tours.jpg",
    fields: [
      { key: "date", type: "date" },
      { key: "route", type: "text" },
      { key: "guests", type: "number" },
      { key: "language", type: "text", optional: true },
    ],
  },
  {
    slug: "transfer",
    category: "transfer",
    image: "transfer.jpg",
    fields: [
      { key: "date", type: "date" },
      { key: "pickup", type: "text" },
      { key: "dropoff", type: "text" },
      { key: "guests", type: "number" },
      { key: "flight", type: "text", optional: true },
    ],
  },
  {
    slug: "kiralama",
    category: "kiralama",
    image: "rental.jpg",
    fields: [
      { key: "rentalType", type: "text" },
      { key: "startDate", type: "date" },
      { key: "endDate", type: "date" },
      { key: "location", type: "text" },
    ],
  },
  {
    slug: "rehberlik",
    category: "rehberlik",
    image: "guide.jpg",
    fields: [
      { key: "date", type: "date" },
      { key: "city", type: "text" },
      { key: "language", type: "text" },
      { key: "duration", type: "text" },
      { key: "guests", type: "number" },
    ],
  },
];

export interface ServiceText {
  // Sayfa başlığı (h1) ve menüdeki ad
  title: string;
  // Google'da görünen başlık (~60 karakter) ve açıklama (~155 karakter)
  seoTitle: string;
  description: string;
  kicker: string;
  intro: string;
  points: [string, string, string, string];
  faq: [string, string][];
  // Form alanları: [etiket, örnek]
  fields: Record<string, [string, string?]>;
}

export interface ServiceUi {
  eyebrow: string;
  included: string;
  faq: string;
  formTitle: string;
  formText: string;
  other: string;
  whatsapp: string;
}

export type ServiceLang = { ui: ServiceUi } & Record<ServiceSlug, ServiceText>;

export const SERVICE_TEXT: Record<Locale, ServiceLang> = { ...SERVICE_TEXT_A, ...SERVICE_TEXT_B };
