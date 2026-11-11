import type { Reservation, Service } from "@lux/shared";

interface Text {
  title: string;
  kicker: string;
  text: string;
}

interface Field {
  label: string;
  placeholder?: string;
}

// Her dil dosyası bu yapıyı birebir doldurur; eksik metin tip kontrolünde yakalanır.
export interface Dict {
  meta: { title: string; description: string };
  languageLabel: string;
  nav: {
    aria: string;
    hotels: string;
    organizations: string;
    bosphorus: string;
    tours: string;
    transfer: string;
    rental: string;
    guide: string;
    about: string;
    contact: string;
  };
  cta: { request: string; whatsapp: string; view: string };
  slider: {
    label: string;
    bestPrice: string;
    book: string;
    prev: string;
    next: string;
    // Sırasıyla İstanbul, Bodrum, Kapadokya, Bakü
    slides: [Slide, Slide, Slide, Slide];
  };
  home: {
    eyebrow: string;
    titleStart: string;
    titleEm: string;
    text: string;
    featured: string;
    reservationsEyebrow: string;
    reservationsTitle: string;
    servicesEyebrow: string;
    servicesTitle: string;
    aboutEyebrow: string;
    destinationsEyebrow: string;
    destinationsTitle: string;
  };
  about: string;
  services: Record<Service["id"], Text>;
  reservations: Record<Reservation["id"], Text & { fields: Record<string, Field> }>;
  destinations: Record<string, string>;
  form: FormText;
  bosphorus: {
    title: string;
    kicker: string;
    intro: string;
    highlights: [Highlight, Highlight, Highlight, Highlight];
    whatsappMessage: string;
    highlightsEyebrow: string;
    highlightsTitle: string;
    boatEyebrow: string;
    boatText: string;
    // {name} tekne adıyla, {n} sıra numarasıyla değişir
    boatVideo: string;
    boatPhoto: string;
    boatNight: string;
    filmEyebrow: string;
    filmTitle: string;
    photosEyebrow: string;
    photosTitle: string;
    photo: string;
    ctaTitle: string;
    ctaText: string;
    ctaButton: string;
  };
  hotels: { title: string; eyebrow: string; empty: string; whatsappMessage: string };
  footer: { contact: string; follow: string; rights: string };
}

interface Slide {
  kicker: string;
  title: string;
}

interface Highlight {
  title: string;
  text: string;
}

// İstemci bileşenine (rezervasyon formu) giden metinler; {field}/{title} yer tutucudur.
export interface FormText {
  optional: string;
  contactEyebrow: string;
  fullName: string;
  phone: string;
  email: string;
  notes: string;
  noteLabel: string;
  submit: string;
  errName: string;
  errContact: string;
  errEmail: string;
  errRequired: string;
  messageIntro: string;
}

export function fill(template: string, values: Record<string, string | number>): string {
  return template.replace(/\{(\w+)\}/g, (_, key: string) => String(values[key] ?? ""));
}
