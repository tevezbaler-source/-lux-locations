// Site, uygulama ve veritabanı tohumu bu listeyi paylaşır.
// Veritabanı kurulunca içerik `services` ve `destinations` tablolarından okunur;
// bu dosya o zamana kadar varsayılan içeriktir.

export type FieldType = 'text' | 'number' | 'date';

export interface ServiceField {
  key: string;
  label: string;
  type: FieldType;
  placeholder?: string;
  optional?: boolean;
}

export interface Service {
  id: 'tur' | 'transfer' | 'konaklama' | 'organizasyon' | 'kiralama' | 'rehberlik';
  title: string;
  kicker: string;
  text: string;
  tags: string[];
  image: string;
  fields: ServiceField[];
}

export const SERVICES: Service[] = [
  {
    id: 'tur',
    title: 'Özel turlar',
    kicker: 'Keşfetmenin size özel hali',
    text: 'Şehrin simgelerinden daha sakin keşiflere, ilgi alanlarınıza ve temponuza uygun bir rota oluşturalım.',
    tags: ['Şehir keşifleri', 'Kişisel rota', 'Kültür & deneyim'],
    image: 'tours.jpg',
    fields: [
      { key: 'route', label: 'Şehir / rota', type: 'text', placeholder: 'İstanbul, Kapadokya…' },
      { key: 'guests', label: 'Kişi sayısı', type: 'number', placeholder: '2' },
      { key: 'language', label: 'Rehberlik dili', type: 'text', placeholder: 'Türkçe, İngilizce…' },
    ],
  },
  {
    id: 'transfer',
    title: 'Transfer',
    kicker: 'Rahat bir başlangıç',
    text: 'Havalimanı, otel veya şehir içi ulaşımınızı seyahat planınızla birlikte koordine edelim.',
    tags: ['Havalimanı', 'Şehir içi', 'Özel ulaşım'],
    image: 'transfer.jpg',
    fields: [
      { key: 'pickup', label: 'Alış noktası', type: 'text', placeholder: 'Havalimanı / adres' },
      { key: 'dropoff', label: 'Varış noktası', type: 'text', placeholder: 'Otel / adres' },
      { key: 'guests', label: 'Yolcu sayısı', type: 'number', placeholder: '2' },
      { key: 'flight', label: 'Uçuş numarası (varsa)', type: 'text', placeholder: 'TK…', optional: true },
    ],
  },
  {
    id: 'konaklama',
    title: 'Oteller',
    kicker: 'Kendinize ayırdığınız alan',
    text: 'Bir şehir kaçamağı ya da uzun bir tatil. Beklentilerinize ve seyahat grubunuza uygun otel için talebinizi paylaşın.',
    tags: ['Otel seçenekleri', 'Kahvaltı & oda', 'Kişisel tercihler'],
    image: 'villa.jpg',
    fields: [
      { key: 'region', label: 'Bölge', type: 'text', placeholder: 'Bodrum, İstanbul…' },
      { key: 'checkout', label: 'Çıkış tarihi', type: 'date' },
      { key: 'adults', label: 'Yetişkin sayısı', type: 'number', placeholder: '2' },
      { key: 'children', label: 'Çocuk sayısı', type: 'number', placeholder: '0', optional: true },
    ],
  },
  {
    id: 'organizasyon',
    title: 'Organizasyon',
    kicker: 'Hatırlanmaya değer anlar',
    text: 'Özel bir kutlama veya kurumsal bir buluşma. Mekân, ulaşım ve program ihtiyaçlarınızı birlikte planlayalım.',
    tags: ['Özel davetler', 'Kurumsal buluşmalar', 'Etkinlik planlama'],
    image: 'events.jpg',
    fields: [
      { key: 'eventType', label: 'Organizasyon türü', type: 'text', placeholder: 'Kutlama, toplantı…' },
      { key: 'location', label: 'Lokasyon', type: 'text', placeholder: 'Şehir / mekân' },
      { key: 'guests', label: 'Katılımcı sayısı', type: 'number', placeholder: '20' },
      { key: 'budget', label: 'Bütçe aralığı (isteğe bağlı)', type: 'text', placeholder: 'Tutar ve para birimi', optional: true },
    ],
  },
  {
    id: 'kiralama',
    title: 'Kiralama',
    kicker: 'Planınıza uygun seçenekler',
    text: 'Kiralama ihtiyacınızı, tarihlerinizi ve beklentilerinizi anlatın; uygun seçenekler için talebinizi değerlendirelim.',
    tags: ['Kişisel ihtiyaçlar', 'Tarih aralığı', 'Teslim planlama'],
    image: 'rental.jpg',
    fields: [
      { key: 'rentalType', label: 'Kiralama türü', type: 'text', placeholder: 'İhtiyacınızı belirtin' },
      { key: 'endDate', label: 'Bitiş tarihi', type: 'date' },
      { key: 'delivery', label: 'Teslim noktası', type: 'text', placeholder: 'Şehir / adres' },
    ],
  },
  {
    id: 'rehberlik',
    title: 'Rehberlik',
    kicker: 'Her şehrin bir hikâyesi var',
    text: 'Gördüğünüz yerlerin hikâyesini keşfedin. Şehir, dil ve süre tercihlerinize göre rehberlik talebinizi oluşturalım.',
    tags: ['Kültür & tarih', 'Dil tercihi', 'Esnek süre'],
    image: 'guide.jpg',
    fields: [
      { key: 'city', label: 'Şehir', type: 'text', placeholder: 'İstanbul…' },
      { key: 'language', label: 'Dil tercihi', type: 'text', placeholder: 'Türkçe, İngilizce…' },
      { key: 'duration', label: 'Süre', type: 'text', placeholder: 'Yarım gün, tam gün…' },
      { key: 'guests', label: 'Kişi sayısı', type: 'number', placeholder: '2' },
    ],
  },
];

export interface Experience {
  slug: string;
  title: string;
  kicker: string;
  intro: string;
  highlights: { title: string; text: string }[];
  // Fotoğraflar her iki uygulamada `bogaz-01.jpg` … biçiminde; ilki kapak görseli.
  photoCount: number;
  whatsappMessage: string;
}

// Tarih, saat, menü ve fiyat ekipten gelince eklenecek; o zamana kadar teklifle bildirilir.
export const BOSPHORUS_DINNER: Experience = {
  slug: 'bogaz-tekne-turu',
  title: 'Boğaz’da yemekli tekne turu',
  kicker: 'İstanbul · Akşam',
  intro:
    'Panoramik camların ardında Boğaz’ın ışıkları, size ayrılmış bir masa ve özenle hazırlanmış bir akşam yemeği. Tarih, kişi sayısı ve beklentilerinizi paylaşın; size özel teklifle dönelim.',
  highlights: [
    { title: 'Boğaz manzarası', text: 'Geniş camlı salonlardan iki yakayı ve köprüleri izleyerek bir akşam.' },
    { title: 'Akşam yemeği', text: 'Hazırlanmış masalarda servis edilen yemekle keyifli bir program.' },
    { title: 'Özel günler', text: 'Doğum günü, yıl dönümü ya da kurumsal grup için masa düzeni ve süsleme talebi.' },
    { title: 'Transfer', text: 'İsterseniz otelinizden alış ve dönüşü de aynı plana ekleyelim.' },
  ],
  photoCount: 12,
  whatsappMessage: 'Merhaba, Boğaz’da yemekli tekne turu için bilgi almak istiyorum.',
};

export function whatsappLink(message?: string): string {
  const base = `https://wa.me/${CONTACT.whatsapp.replace('+', '')}`;
  return message ? `${base}?text=${encodeURIComponent(message)}` : base;
}

export interface Destination {
  slug: string;
  name: string;
  country: string;
}

// Eski sitedeki (luxlocationstours.com) şehir listesi.
export const DESTINATIONS: Destination[] = [
  { slug: 'istanbul', name: 'İstanbul', country: 'Türkiye' },
  { slug: 'bodrum', name: 'Bodrum', country: 'Türkiye' },
  { slug: 'kapadokya', name: 'Kapadokya', country: 'Türkiye' },
  { slug: 'antalya', name: 'Antalya', country: 'Türkiye' },
  { slug: 'izmir', name: 'İzmir', country: 'Türkiye' },
  { slug: 'bursa', name: 'Bursa', country: 'Türkiye' },
  { slug: 'trabzon', name: 'Trabzon', country: 'Türkiye' },
  { slug: 'sapanca', name: 'Sapanca', country: 'Türkiye' },
  { slug: 'marmaris', name: 'Marmaris', country: 'Türkiye' },
  { slug: 'kusadasi', name: 'Kuşadası', country: 'Türkiye' },
  { slug: 'fethiye', name: 'Fethiye', country: 'Türkiye' },
  { slug: 'didim', name: 'Didim', country: 'Türkiye' },
  { slug: 'cesme', name: 'Çeşme', country: 'Türkiye' },
  { slug: 'erzurum', name: 'Erzurum', country: 'Türkiye' },
  { slug: 'baku', name: 'Bakü', country: 'Azerbaycan' },
];

export const CONTACT = {
  whatsapp: '+905319000340',
  phone: '+902125232310',
  phone2: '+902125232311',
  email: 'reservation@luxlocationstours.com',
  infoEmail: 'info@luxlocationstours.com',
  address: 'Evliya Çelebi Mahallesi Meşrutiyet Caddesi No:92 Kat:2 D:5 34430 Beyoğlu / İstanbul / TÜRKİYE',
  website: 'https://www.luxlocationstours.com',
  social: {
    instagram: 'https://www.instagram.com/luxlocationstours/',
    facebook: 'https://www.facebook.com/people/Lux-Locations-Tours/100082737445569/',
    twitter: 'https://twitter.com/luxlocationn',
    linkedin: 'https://www.linkedin.com/in/lux-locations-tours-06b780255/',
  },
} as const;

export const ABOUT =
  'Acentamız 2022 yılında kaliteyi uluslararası standartlarda, uzun vadeli ticari ilişkilere dayanan hizmet anlayışıyla kurulmuştur. TÜRSAB (Türkiye Seyahat Acentaları Birliği) üyesi olan acentamız; çeşitli bölgelerde koordineli ve sorunsuz ilerleyen transfer hizmetleri, geniş yelpazeli gezi programları, çok sayıda otel, mükemmel bir fiyat ve servis oranı ile profesyonel hizmet anlayışı sunarak tüm tur operatörlerine beklenen kesinlik, güvenilirlik ve esneklik sağlamaktadır.';

export const PARTNERS = [
  { name: 'Cistour', logo: 'cistour.png' },
  { name: 'Camelia', logo: 'camellia.png' },
  { name: 'Europa', logo: 'europa.png' },
  { name: 'Panda', logo: 'panda.png' },
  { name: 'Taki', logo: 'taki.png' },
  { name: 'Nevokorp', logo: 'nevokorp.png' },
] as const;