import type { Locale } from '@lux/shared/i18n';

// Yalnızca uygulamada geçen kısa yazılar. Sitenin metinleri @lux/shared/i18n'den gelir.
export interface AppText {
  discover: string;
  reserve: string;
  language: string;
  offline: string;
  retry: string;
  loading: string;
  call: string;
  mail: string;
  contactLead: string;
  address: string;
  follow: string;
  openPage: string;
  noHotels: string;
  privacy: string;
}

export const APP_TEXT: Record<Locale, AppText> = {
  tr: {
    discover: 'Keşfet', reserve: 'Rezervasyon', language: 'Dil', offline: 'Bağlantı yok, son indirilen içerik gösteriliyor.',
    retry: 'Yeniden dene', loading: 'Yükleniyor…', call: 'Arayın', mail: 'E-posta gönderin',
    contactLead: 'Talebinizi ekibimize iletin, size özel teklifle dönelim.', address: 'Adres', follow: 'Bizi takip edin',
    openPage: 'İncele', noHotels: 'Oteller yüklenemedi.', privacy: 'Gizlilik politikası',
  },
  en: {
    discover: 'Discover', reserve: 'Booking', language: 'Language', offline: 'No connection, showing the last downloaded content.',
    retry: 'Try again', loading: 'Loading…', call: 'Call us', mail: 'Send an email',
    contactLead: 'Send your request to our team and we will come back with a tailored offer.', address: 'Address', follow: 'Follow us',
    openPage: 'Discover', noHotels: 'Hotels could not be loaded.', privacy: 'Privacy policy',
  },
  ar: {
    discover: 'اكتشف', reserve: 'الحجز', language: 'اللغة', offline: 'لا يوجد اتصال، يُعرض آخر محتوى تم تنزيله.',
    retry: 'أعد المحاولة', loading: 'جارٍ التحميل…', call: 'اتصل بنا', mail: 'أرسل بريداً إلكترونياً',
    contactLead: 'أرسل طلبك إلى فريقنا وسنعود إليك بعرض مخصّص.', address: 'العنوان', follow: 'تابعنا',
    openPage: 'اكتشف', noHotels: 'تعذّر تحميل الفنادق.', privacy: 'سياسة الخصوصية',
  },
  ru: {
    discover: 'Обзор', reserve: 'Бронирование', language: 'Язык', offline: 'Нет соединения, показан последний загруженный контент.',
    retry: 'Повторить', loading: 'Загрузка…', call: 'Позвонить', mail: 'Написать письмо',
    contactLead: 'Отправьте запрос нашей команде, и мы вернёмся с индивидуальным предложением.', address: 'Адрес', follow: 'Мы в соцсетях',
    openPage: 'Подробнее', noHotels: 'Не удалось загрузить отели.', privacy: 'Политика конфиденциальности',
  },
  de: {
    discover: 'Entdecken', reserve: 'Buchung', language: 'Sprache', offline: 'Keine Verbindung, zuletzt geladene Inhalte werden angezeigt.',
    retry: 'Erneut versuchen', loading: 'Wird geladen…', call: 'Anrufen', mail: 'E-Mail senden',
    contactLead: 'Senden Sie Ihre Anfrage an unser Team, wir melden uns mit einem persönlichen Angebot.', address: 'Adresse', follow: 'Folgen Sie uns',
    openPage: 'Entdecken', noHotels: 'Hotels konnten nicht geladen werden.', privacy: 'Datenschutzerklärung',
  },
  fr: {
    discover: 'Découvrir', reserve: 'Réservation', language: 'Langue', offline: 'Pas de connexion, affichage du dernier contenu téléchargé.',
    retry: 'Réessayer', loading: 'Chargement…', call: 'Appelez-nous', mail: 'Envoyer un e-mail',
    contactLead: 'Envoyez votre demande à notre équipe, nous revenons vers vous avec une offre sur mesure.', address: 'Adresse', follow: 'Suivez-nous',
    openPage: 'Découvrir', noHotels: 'Impossible de charger les hôtels.', privacy: 'Politique de confidentialité',
  },
  es: {
    discover: 'Descubrir', reserve: 'Reservas', language: 'Idioma', offline: 'Sin conexión, se muestra el último contenido descargado.',
    retry: 'Reintentar', loading: 'Cargando…', call: 'Llámenos', mail: 'Enviar un correo',
    contactLead: 'Envíe su solicitud a nuestro equipo y le responderemos con una oferta a medida.', address: 'Dirección', follow: 'Síganos',
    openPage: 'Descubrir', noHotels: 'No se pudieron cargar los hoteles.', privacy: 'Política de privacidad',
  },
  it: {
    discover: 'Scopri', reserve: 'Prenotazioni', language: 'Lingua', offline: 'Nessuna connessione, viene mostrato l’ultimo contenuto scaricato.',
    retry: 'Riprova', loading: 'Caricamento…', call: 'Chiamaci', mail: 'Invia un’e-mail',
    contactLead: 'Invia la tua richiesta al nostro team, ti risponderemo con un’offerta su misura.', address: 'Indirizzo', follow: 'Seguici',
    openPage: 'Scopri', noHotels: 'Impossibile caricare gli hotel.', privacy: 'Informativa sulla privacy',
  },
  zh: {
    discover: '探索', reserve: '预订', language: '语言', offline: '无网络连接，显示最近下载的内容。',
    retry: '重试', loading: '加载中…', call: '致电我们', mail: '发送邮件',
    contactLead: '将您的需求发送给我们的团队，我们将为您提供专属报价。', address: '地址', follow: '关注我们',
    openPage: '查看', noHotels: '无法加载酒店。', privacy: '隐私政策',
  },
  fa: {
    discover: 'کاوش', reserve: 'رزرو', language: 'زبان', offline: 'اتصال برقرار نیست، آخرین محتوای دریافت‌شده نمایش داده می‌شود.',
    retry: 'تلاش دوباره', loading: 'در حال بارگذاری…', call: 'تماس بگیرید', mail: 'ارسال ایمیل',
    contactLead: 'درخواست خود را برای تیم ما بفرستید تا با پیشنهادی ویژه پاسخ دهیم.', address: 'نشانی', follow: 'ما را دنبال کنید',
    openPage: 'مشاهده', noHotels: 'هتل‌ها بارگذاری نشدند.', privacy: 'سیاست حریم خصوصی',
  },
  ro: {
    discover: 'Descoperă', reserve: 'Rezervări', language: 'Limbă', offline: 'Fără conexiune, se afișează ultimul conținut descărcat.',
    retry: 'Reîncearcă', loading: 'Se încarcă…', call: 'Sunați-ne', mail: 'Trimiteți un e-mail',
    contactLead: 'Trimiteți cererea echipei noastre și revenim cu o ofertă personalizată.', address: 'Adresă', follow: 'Urmăriți-ne',
    openPage: 'Descoperă', noHotels: 'Hotelurile nu au putut fi încărcate.', privacy: 'Politica de confidențialitate',
  },
};

export const FLAGS: Record<Locale, string> = {
  tr: '🇹🇷', en: '🇬🇧', ar: '🇸🇦', ru: '🇷🇺', de: '🇩🇪', fr: '🇫🇷', es: '🇪🇸', it: '🇮🇹', zh: '🇨🇳', fa: '🇮🇷', ro: '🇷🇴',
};
