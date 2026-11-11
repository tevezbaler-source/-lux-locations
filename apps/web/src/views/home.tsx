import { BOSPHORUS_DINNER, DESTINATIONS, RESERVATIONS, SERVICES, whatsappLink } from "@lux/shared";
import { HeroSlider } from "@/components/hero-slider";
import { SiteFooter } from "@/components/site-footer";
import { SiteHeader } from "@/components/site-header";
import { getDict, localePath, type Locale } from "@/i18n";
import { pageMetadata } from "./meta";

export function homeMetadata(lang: Locale) {
  const t = getDict(lang);
  return pageMetadata(lang, "/", t.meta.title, t.meta.description);
}

export function HomeView({ lang }: { lang: Locale }) {
  const t = getDict(lang);
  const to = (p: string) => localePath(lang, p);

  return (
    <>
      <SiteHeader lang={lang} path="/" ctaHref={whatsappLink()} ctaLabel={t.cta.request} />

      <HeroSlider ctaHref={whatsappLink()} t={t.slider} />

      <section className="intro">
        <span className="eyebrow">{t.home.eyebrow}</span>
        <h1>
          {t.home.titleStart} <em>{t.home.titleEm}</em>
        </h1>
        <p>{t.home.text}</p>
      </section>

      <section className="section">
        <a className="featured" href={to(`/${BOSPHORUS_DINNER.slug}`)}>
          <img src="/assets/bogaz/bogaz-01.jpg" alt={t.bosphorus.title} />
          <div>
            <small>
              {t.home.featured} · {t.bosphorus.kicker}
            </small>
            <h3>{t.bosphorus.title}</h3>
            <p>{t.bosphorus.intro}</p>
            <span className="btn">{t.cta.view}</span>
          </div>
        </a>
      </section>

      <section className="section" id="rezervasyon">
        <span className="eyebrow">{t.home.reservationsEyebrow}</span>
        <h2>{t.home.reservationsTitle}</h2>
        <div className="reservations">
          {RESERVATIONS.map((r) => (
            <a key={r.id} href={to(`/${r.slug}`)}>
              <small>{t.reservations[r.id].kicker}</small>
              <h3>{t.reservations[r.id].title}</h3>
              <p>{t.reservations[r.id].text}</p>
            </a>
          ))}
        </div>
      </section>

      <section className="section" id="hizmetler">
        <span className="eyebrow">{t.home.servicesEyebrow}</span>
        <h2>{t.home.servicesTitle}</h2>
        <div className="services">
          {SERVICES.map((service, i) => {
            const s = t.services[service.id];
            return (
              <article className="service" key={service.id} id={service.id}>
                <img src={`/assets/${service.image}`} alt={s.title} loading={i > 2 ? "lazy" : undefined} />
                <div>
                  <small>{s.kicker}</small>
                  <h3>{s.title}</h3>
                  <p>{s.text}</p>
                </div>
              </article>
            );
          })}
        </div>
      </section>

      <section className="section" id="hakkimizda">
        <span className="eyebrow">{t.home.aboutEyebrow}</span>
        <h2>Lux Locations Tours</h2>
        <p className="about">{t.about}</p>
      </section>

      <section className="section" id="destinasyonlar">
        <span className="eyebrow">{t.home.destinationsEyebrow}</span>
        <h2>{t.home.destinationsTitle}</h2>
        <ul className="cities">
          {DESTINATIONS.map((d) => (
            <li key={d.slug}>{t.destinations[d.slug] ?? d.name}</li>
          ))}
        </ul>
      </section>

      <SiteFooter lang={lang} />
    </>
  );
}
