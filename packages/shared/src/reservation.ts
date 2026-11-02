import type { ServiceField } from './catalog';

// Uygulamadaki üç rezervasyon sekmesi ve sitedeki karşılık sayfaları bu listeyi paylaşır.
// Supabase bağlanana kadar talep, doldurulmuş bir WhatsApp mesajı olarak gönderilir.

export interface Reservation {
  id: 'oda' | 'grup' | 'kongre';
  slug: string;
  tab: string;
  title: string;
  kicker: string;
  text: string;
  fields: ServiceField[];
}

export const RESERVATIONS: Reservation[] = [
  {
    id: 'oda',
    slug: 'oda-rezervasyonu',
    tab: 'Oda',
    title: 'Oda rezervasyonu',
    kicker: 'Otel & konaklama',
    text: 'Şehir, tarih ve oda tercihlerinizi paylaşın; size uygun otel seçenekleriyle dönelim.',
    fields: [
      { key: 'city', label: 'Şehir / otel tercihi', type: 'text', placeholder: 'İstanbul, Bodrum…' },
      { key: 'checkin', label: 'Giriş tarihi', type: 'date' },
      { key: 'checkout', label: 'Çıkış tarihi', type: 'date' },
      { key: 'rooms', label: 'Oda sayısı', type: 'number', placeholder: '1' },
      { key: 'adults', label: 'Yetişkin sayısı', type: 'number', placeholder: '2' },
      { key: 'children', label: 'Çocuk sayısı', type: 'number', placeholder: '0', optional: true },
      { key: 'roomType', label: 'Oda tipi', type: 'text', placeholder: 'Standart, deniz manzaralı, suit…', optional: true },
    ],
  },
  {
    id: 'grup',
    slug: 'grup-rezervasyonu',
    tab: 'Grup',
    title: 'Grup rezervasyonu',
    kicker: 'Aileler, kulüpler, şirket grupları',
    text: 'Kalabalık gruplar için konaklama, transfer ve programı tek elden planlayalım.',
    fields: [
      { key: 'groupName', label: 'Grup / kurum adı', type: 'text', placeholder: 'Kurum ya da grup adı', optional: true },
      { key: 'city', label: 'Şehir', type: 'text', placeholder: 'İstanbul, Kapadokya…' },
      { key: 'checkin', label: 'Giriş tarihi', type: 'date' },
      { key: 'checkout', label: 'Çıkış tarihi', type: 'date' },
      { key: 'guests', label: 'Kişi sayısı', type: 'number', placeholder: '20' },
      { key: 'rooms', label: 'Oda sayısı', type: 'number', placeholder: '10' },
      { key: 'extras', label: 'Ek hizmetler', type: 'text', placeholder: 'Transfer, tur, rehber…', optional: true },
    ],
  },
  {
    id: 'kongre',
    slug: 'toplanti-kongre',
    tab: 'Kongre',
    title: 'Toplantı & kongre rezervasyonu',
    kicker: 'Toplantı, kongre, seminer',
    text: 'Salon, konaklama, teknik altyapı ve ulaşımı etkinliğinize göre planlayalım.',
    fields: [
      { key: 'eventType', label: 'Etkinlik türü', type: 'text', placeholder: 'Toplantı, kongre, seminer…' },
      { key: 'city', label: 'Şehir', type: 'text', placeholder: 'İstanbul, Antalya…' },
      { key: 'startDate', label: 'Başlangıç tarihi', type: 'date' },
      { key: 'endDate', label: 'Bitiş tarihi', type: 'date' },
      { key: 'attendees', label: 'Katılımcı sayısı', type: 'number', placeholder: '100' },
      { key: 'layout', label: 'Salon düzeni', type: 'text', placeholder: 'Tiyatro, sınıf, U düzeni…', optional: true },
      { key: 'rooms', label: 'Konaklama (oda sayısı)', type: 'number', placeholder: '0', optional: true },
      { key: 'technical', label: 'Teknik ihtiyaçlar', type: 'text', placeholder: 'Projeksiyon, ses, simultane çeviri…', optional: true },
    ],
  },
];

export interface ReservationContact {
  full_name: string;
  phone: string;
  email: string;
  notes: string;
}

export function validateReservation(r: Reservation, contact: ReservationContact, details: Record<string, string>): string[] {
  const errors: string[] = [];
  if (!contact.full_name.trim()) errors.push('Ad soyad gerekli.');
  if (!contact.phone.trim() && !contact.email.trim()) errors.push('Telefon veya e-posta gerekli.');
  if (contact.email.trim() && !/^\S+@\S+\.\S+$/.test(contact.email.trim())) errors.push('E-posta adresi geçersiz.');
  for (const field of r.fields) {
    if (!field.optional && !details[field.key]?.trim()) errors.push(`${field.label} gerekli.`);
  }
  return errors;
}

export function reservationMessage(r: Reservation, contact: ReservationContact, details: Record<string, string>): string {
  const lines = [`Merhaba, ${r.title.toLocaleLowerCase('tr')} talebim:`, ''];
  for (const field of r.fields) {
    const value = details[field.key]?.trim();
    if (value) lines.push(`${field.label}: ${value}`);
  }
  lines.push('', `Ad soyad: ${contact.full_name.trim()}`);
  if (contact.phone.trim()) lines.push(`Telefon: ${contact.phone.trim()}`);
  if (contact.email.trim()) lines.push(`E-posta: ${contact.email.trim()}`);
  if (contact.notes.trim()) lines.push(`Not: ${contact.notes.trim()}`);
  return lines.join('\n');
}
