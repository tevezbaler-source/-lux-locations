import { SiteFooter } from "../site-footer";
import { CONTACT, whatsappLink } from "@lux/shared";
import { SiteHeader } from "../site-header";
import { HOTELS } from "./hotels";

export const metadata = { title: "Oteller | Lux Locations Tours" };

export default function Oteller() {
  return (
    <>
      <SiteHeader ctaHref={whatsappLink("Merhaba, otel konaklaması için bilgi almak istiyorum.")} ctaLabel="Talep oluştur ↗" />

      <section className="section">
        <span className="eyebrow">Konaklama</span>
        <h2>Oteller</h2>
        {HOTELS.length === 0 ? (
          <p>Otel listemiz çok yakında burada olacak. Şimdiden talebinizi iletebilirsiniz.</p>
        ) : (
          <div className="services">
            {HOTELS.map((hotel) => (
              <a className="service" key={hotel.slug} href={`/oteller/${hotel.slug}`}>
                <img src={hotel.cover} alt={hotel.name} />
                <div>
                  <small>{hotel.city}</small>
                  <h3>{hotel.name}</h3>
                  <p>{hotel.summary}</p>
                </div>
              </a>
            ))}
          </div>
        )}
      </section>

      <SiteFooter />
    </>
  );
}
