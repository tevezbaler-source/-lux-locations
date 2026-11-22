import { BOSPHORUS_DINNER, DESTINATIONS, RESERVATIONS, SERVICES, whatsappLink } from "@lux/shared";
import { preload } from "react-dom";
import { HeroSlider } from "@/components/hero-slider";
import { SiteFooter } from "@/components/site-footer";
import { SiteHeader } from "@/components/site-header";
import { getDict, localePath, type Locale } from "@/i18n";
import { pageMetadata } from "./meta";
import { HOME_SEO } from "./seo";

export function homeMetadata(lang: Locale) {
  return pageMetadata(lang, "/", HOME_SEO[lang].title, HOME_SEO[lang].description);
}

export function HomeView({ lang }: { lang: Locale }) {
  const t = getDict(lang);
  const to = (p: string) => localePath(lang, p);
  // İlk slayt sayfanın en büyük görseli; tarayıcı CSS'i beklemeden indirmeye başlasın.
  preload("/assets/slider/1.jpg", { as: "image", fetchPriority: "high" });

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
          <img src="/assets/bogaz/bogaz-01.jpg" alt={t.bosphorus.title} loading="lazy" />
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
                <img src={`/assets/${service.image}`} alt={s.title} loading="lazy" />
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
