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
    title: 'Otel & villa',
    kicker: 'Kendinize ayırdığınız alan',
    text: 'Bir şehir kaçamağı ya da uzun bir tatil. Beklentilerinize ve seyahat grubunuza uygun konaklama için talebinizi paylaşın.',
    tags: ['Otel seçenekleri', 'Villa konaklaması', 'Kişisel tercihler'],
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

export interface Destination {
  slug: string;
  name: string;
  country: string;
}

// Mevcut sitede 13 şehir anlatılıyor; geri kalanı içerik aktarımında eklenecek.
export const DESTINATIONS: Destination[] = [
  { slug: 'istanbul', name: 'İstanbul', country: 'Türkiye' },
  { slug: 'kapadokya', name: 'Kapadokya', country: 'Türkiye' },
  { slug: 'bodrum', name: 'Bodrum', country: 'Türkiye' },
  { slug: 'baku', name: 'Bakü', country: 'Azerbaycan' },
];

export const CONTACT = {
  whatsapp: '+905319000340',
  phone: '+902125232310',
  email: 'reservation@luxlocationstours.com',
  website: 'https://www.luxlocationstours.com',
} as const;
