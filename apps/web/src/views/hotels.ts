// Otel listesi. Fotoğraflar geldikçe buraya eklenecek; her otelin kahvaltı, oda vb. galerisi olacak.
export interface Hotel {
  slug: string;
  name: string;
  city: string;
  summary: string;
  cover: string;
  // Örn: { "Kahvaltı": ["/assets/oteller/x/kahvalti-01.jpg"], "Odalar": [...] }
  gallery: Record<string, string[]>;
}

export const HOTELS: Hotel[] = [];
