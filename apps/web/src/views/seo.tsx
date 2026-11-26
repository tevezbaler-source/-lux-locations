import { CONTACT, DESTINATIONS } from "@lux/shared";
import { localePath, type Locale } from "@/i18n";

// Sitenin asıl adresi. www, .htaccess'te çıplak alan adına 301 ile yönlenir;
// arama motorlarına yönlenen değil, son adres verilmeli.
export const SITE_URL = "https://luxlocationstours.com";
export const SITE_NAME = "Lux Locations Tours";

// Paylaşım (WhatsApp, Facebook, X …) önizlemesinde görünen varsayılan görsel.
export const DEFAULT_SHARE_IMAGE = "/assets/slider/1.jpg";

export const OG_LOCALE: Record<Locale, string> = {
  tr: "tr_TR",
  en: "en_US",
  ar: "ar_AR",
  ru: "ru_RU",
  de: "de_DE",
  fr: "fr_FR",
  es: "es_ES",
  it: "it_IT",
  zh: "zh_CN",
  fa: "fa_IR",
  ro: "ro_RO",
};

// Ana sayfanın Google'da görünen başlığı ve açıklaması: insanların aradığı kelimelerle
// (özel tur, transfer, otel, Boğaz turu). Başlık ~60, açıklama ~160 karakteri geçmesin.
export const HOME_SEO: Record<Locale, { title: string; description: string }> = {
  tr: {
    title: "Lux Locations Tours | İstanbul Özel Tur, Transfer ve Otel",
    description:
      "İstanbul ve Türkiye'de özel turlar, VIP havalimanı transferi, otel rezervasyonu, Boğaz yemekli tekne turu ve rehberlik. Size özel teklif için WhatsApp'tan yazın.",
  },
  en: {
    title: "Lux Locations Tours | Istanbul Private Tours, Transfers & Hotels",
    description:
      "Private tours in Istanbul and across Türkiye, VIP airport transfers, hotel bookings, Bosphorus dinner cruises and guides. Get a tailored quote on WhatsApp.",
  },
  ar: {
    title: "Lux Locations Tours | جولات خاصة في إسطنبول، نقل وفنادق",
    description:
      "جولات خاصة في إسطنبول وتركيا، نقل VIP من المطار، حجز فنادق، عشاء على متن قارب في البوسفور ومرشدون سياحيون. اطلب عرضاً خاصاً بك عبر واتساب.",
  },
  ru: {
    title: "Lux Locations Tours | Индивидуальные туры по Стамбулу, трансфер, отели",
    description:
      "Индивидуальные туры по Стамбулу и Турции, VIP-трансфер из аэропорта, бронирование отелей, ужин-круиз по Босфору и гиды. Персональное предложение в WhatsApp.",
  },
  de: {
    title: "Lux Locations Tours | Private Touren in Istanbul, Transfers & Hotels",
    description:
      "Private Touren in Istanbul und der Türkei, VIP-Flughafentransfer, Hotelbuchung, Bosporus-Dinner-Kreuzfahrt und Reiseführer. Ihr individuelles Angebot per WhatsApp.",
  },
  fr: {
    title: "Lux Locations Tours | Visites privées à Istanbul, transferts, hôtels",
    description:
      "Visites privées à Istanbul et en Turquie, transferts VIP depuis l'aéroport, réservation d'hôtels, dîner-croisière sur le Bosphore et guides. Devis sur mesure par WhatsApp.",
  },
  es: {
    title: "Lux Locations Tours | Tours privados en Estambul, traslados, hoteles",
    description:
      "Tours privados en Estambul y Turquía, traslados VIP desde el aeropuerto, reservas de hotel, cena en crucero por el Bósforo y guías. Presupuesto a medida por WhatsApp.",
  },
  it: {
    title: "Lux Locations Tours | Tour privati a Istanbul, transfer e hotel",
    description:
      "Tour privati a Istanbul e in Turchia, transfer VIP dall'aeroporto, prenotazione hotel, crociera con cena sul Bosforo e guide. Preventivo su misura via WhatsApp.",
  },
  zh: {
    title: "Lux Locations Tours | 伊斯坦布尔私人定制游、接送与酒店",
    description:
      "伊斯坦布尔及土耳其私人定制游、VIP机场接送、酒店预订、博斯普鲁斯海峡晚餐游船及专业导游。通过WhatsApp获取专属报价。",
  },
  fa: {
    title: "Lux Locations Tours | تور خصوصی استانبول، ترانسفر و هتل",
    description:
      "تورهای خصوصی در استانبول و ترکیه، ترانسفر VIP فرودگاه، رزرو هتل، شام روی کشتی در بسفر و راهنمای تور. پیشنهاد اختصاصی خود را از واتس‌اپ بگیرید.",
  },
  ro: {
    title: "Lux Locations Tours | Tururi private în Istanbul, transferuri, hoteluri",
    description:
      "Tururi private în Istanbul și Turcia, transfer VIP de la aeroport, rezervări la hotel, croazieră cu cină pe Bosfor și ghizi. Ofertă personalizată pe WhatsApp.",
  },
};

// Google açıklamayı ~160 karakterde keser; uzun metni kelime sınırında kısaltır.
export function shortText(text: string, max = 158) {
  if (text.length <= max) return text;
  const cut = text.slice(0, max);
  return cut.slice(0, Math.max(cut.lastIndexOf(" "), max - 20)).replace(/[\s,.;:–—-]+$/, "") + "…";
}

export const absUrl = (path: string) => (path.startsWith("http") ? path : SITE_URL + path);

// Google için firma bilgisi (yapılandırılmış veri). Her sayfada bir kez bulunur;
// arama sonuçlarındaki firma kartı ve harita bilgisiyle eşleşmesine yardım eder.
export function organizationJsonLd(lang: Locale, description: string) {
  return {
    "@context": "https://schema.org",
    "@type": "TravelAgency",
    "@id": `${SITE_URL}/#organization`,
    name: SITE_NAME,
    alternateName: "Lux Locations",
    url: absUrl(localePath(lang, "/")),
    logo: absUrl("/assets/logo-mark.png"),
    image: absUrl(DEFAULT_SHARE_IMAGE),
    description,
    telephone: CONTACT.phone,
    email: CONTACT.infoEmail,
    address: {
      "@type": "PostalAddress",
      streetAddress: "Evliya Çelebi Mahallesi, Meşrutiyet Caddesi No:92 Kat:2 D:5",
      addressLocality: "Beyoğlu",
      addressRegion: "İstanbul",
      postalCode: "34430",
      addressCountry: "TR",
    },
    areaServed: DESTINATIONS.map((d) => ({ "@type": "City", name: d.name })),
    availableLanguage: ["tr", "en", "ar", "ru", "de", "fr", "es", "it", "zh", "fa", "ro"],
    sameAs: Object.values(CONTACT.social),
    contactPoint: {
      "@type": "ContactPoint",
      telephone: CONTACT.phone,
      contactType: "reservations",
      availableLanguage: ["Turkish", "English", "Arabic", "Russian"],
    },
  };
}

// JSON-LD'yi <script> olarak basar; "<" kaçırılır ki metin içinden etiket kapanmasın.
export function JsonLd({ data }: { data: object }) {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data).replace(/</g, "\\u003c") }}
    />
  );
}

// Çerezsiz ziyaret sayacı (lx-content/hit.php): sayfa açılışı ve WhatsApp/telefon/e-posta tıklamaları.
// Aynı betik otel sayfalarında PHP'den basılır (lx-content/hotels.php, lx_hit_script).
export const HIT_SCRIPT = String.raw`(function(){var u="/lx-content/hit.php";function s(o){try{var d=JSON.stringify(o);if(navigator.sendBeacon)navigator.sendBeacon(u,d);else fetch(u,{method:"POST",body:d,keepalive:true})}catch(e){}}s({p:location.pathname,r:document.referrer,w:innerWidth});document.addEventListener("click",function(v){var a=v.target&&v.target.closest&&v.target.closest("a[href]");if(!a)return;var h=a.getAttribute("href")||"",e=/^https:\/\/(wa\.me|api\.whatsapp\.com)\//.test(h)?"wa":h.indexOf("tel:")===0?"tel":h.indexOf("mailto:")===0?"mail":"";if(e)s({p:location.pathname,e:e})},true)})()`;

export function HitCounter() {
  return <script dangerouslySetInnerHTML={{ __html: HIT_SCRIPT }} />;
}
