import { whatsappLink } from "@lux/shared";
import { SiteFooter } from "@/components/site-footer";
import { SiteHeader } from "@/components/site-header";
import { getDict, localePath, type Locale } from "@/i18n";
import { HOTELS } from "./hotels";
import { pageMetadata } from "./meta";

export function hotelsMetadata(lang: Locale) {
  const t = getDict(lang);
  return pageMetadata(lang, "/oteller", `${t.hotels.title} | Lux Locations Tours`, t.hotels.empty);
}

export function HotelsView({ lang }: { lang: Locale }) {
  const t = getDict(lang);

  return (
    <>
      <SiteHeader lang={lang} path="/oteller" ctaHref={whatsappLink(t.hotels.whatsappMessage)} ctaLabel={t.cta.request} />

      <section className="section">
        <span className="eyebrow">{t.hotels.eyebrow}</span>
        <h2>{t.hotels.title}</h2>
        {HOTELS.length === 0 ? (
          <p>{t.hotels.empty}</p>
        ) : (
          <div className="services">
            {HOTELS.map((hotel) => (
              <a className="service" key={hotel.slug} href={localePath(lang, `/oteller/${hotel.slug}`)}>
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

      <SiteFooter lang={lang} />
    </>
  );
}
