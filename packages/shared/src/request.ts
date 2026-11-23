import { SERVICES, type Service } from './catalog';

// Site ve uygulama aynı `requests` tablosuna bu biçimde yazar.

export type RequestStatus = 'received' | 'quoted' | 'confirmed' | 'cancelled';

export const STATUS_LABELS: Record<RequestStatus, string> = {
  received: 'Alındı',
  quoted: 'Teklif gönderildi',
  confirmed: 'Onaylandı',
  cancelled: 'İptal edildi',
};

export type RequestSource = 'web' | 'mobile';

export interface RequestInput {
  service_id: Service['id'];
  full_name: string;
  email?: string;
  phone?: string;
  start_date?: string;
  details: Record<string, string>;
  notes?: string;
  source: RequestSource;
}

export function validateRequest(input: RequestInput): string[] {
  const errors: string[] = [];
  const service = SERVICES.find((s) => s.id === input.service_id);
  if (!service) errors.push('Hizmet seçin.');
  if (!input.full_name.trim()) errors.push('Ad soyad gerekli.');
  if (!input.email?.trim() && !input.phone?.trim()) errors.push('E-posta veya telefon gerekli.');
  if (input.email && !/^\S+@\S+\.\S+$/.test(input.email)) errors.push('E-posta adresi geçersiz.');
  for (const field of service?.fields ?? []) {
    if (!field.optional && !input.details[field.key]?.trim()) errors.push(`${field.label} gerekli.`);
  }
  return errors;
}

// Formdan giden talebin bir kopyası sunucuya kaydedilir (lx-content/talep.php) ve sitedeki panelin
// "Talepler" bölümünde listelenir. Asıl gönderim yine WhatsApp'tan yapılır; kayıt başarısız olsa da form çalışır.
export interface RequestCopy {
  source: 'web' | 'app';
  lang: string;
  title: string;
  full_name: string;
  phone?: string;
  email?: string;
  message: string;
}

export function saveRequestCopy(site: string, copy: RequestCopy): void {
  try {
    fetch(`${site}/lx-content/talep.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(copy),
      keepalive: true,
    }).catch(() => {});
  } catch {
    // ağ yoksa sessizce geç
  }
}
