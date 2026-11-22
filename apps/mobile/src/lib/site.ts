import type { Locale } from '@lux/shared/i18n';

// Uygulamanın içeriği bu siteden canlı gelir (lx-content/app-api.php). Panelde yapılan her değişiklik
// uygulama bir sonraki açıldığında görünür; mağaza güncellemesi gerekmez.
export const SITE = process.env.EXPO_PUBLIC_SITE_URL ?? 'https://luxlocationstours.com';

export interface HotelRoom {
  name: string;
  size: string;
  desc: string;
}

export interface Hotel {
  slug: string;
  name: string;
  stars: number;
  city: string;
  summary: string;
  description: string;
  features: string[];
  amenities: string[];
  rooms: HotelRoom[];
  address: string;
  map: string;
  website: string;
  booking: string;
  cover: string;
  coverAi: boolean;
  galleries: { title: string; photos: { src: string; caption: string }[] }[];
  path: string;
}

export interface SitePage {
  id: string;
  path: string;
  title: string;
  description: string;
  image: string;
}

export interface SiteContent {
  ok: true;
  version: number;
  lang: Locale;
  updated: number;
  whatsapp: string;
  texts: Record<string, string>;
  images: Record<string, string>;
  hidden: string[];
  ui: Record<string, string>;
  pages: SitePage[];
  hotels: Hotel[];
}

export function apiUrl(lang: Locale) {
  return `${SITE}/lx-content/app-api.php?lang=${lang}`;
}

// Site yollarını (/assets/..., /lx-content/uploads/...) tam adrese çevirir.
export function absolute(src: string) {
  if (!src) return '';
  if (/^https?:\/\//.test(src)) return src;
  return SITE + (src.startsWith('/') ? src : `/${src}`);
}

export function isSiteContent(v: unknown): v is SiteContent {
  const d = v as SiteContent | null;
  return !!d && d.ok === true && Array.isArray(d.hotels) && Array.isArray(d.pages);
}

// "%s" / "%d" yer tutucularını sırayla doldurur (sitenin PHP metinleriyle aynı biçim).
export function sprintf(template: string, ...values: (string | number)[]) {
  let i = 0;
  return template.replace(/%(\d+\$)?[sd]/g, (m, pos: string | undefined) => {
    const idx = pos ? parseInt(pos, 10) - 1 : i++;
    return String(values[idx] ?? '');
  });
}

export function stars(n: number) {
  return n > 0 ? '★'.repeat(Math.min(7, n)) : '';
}
