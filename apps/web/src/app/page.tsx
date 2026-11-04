import { SiteFooter } from "./site-footer";
import { HeroSlider } from "./hero-slider";
import { SiteHeader } from "./site-header";
import { ABOUT, BOSPHORUS_DINNER, CONTACT, DESTINATIONS, RESERVATIONS, SERVICES } from "@lux/shared";

export default function Home() {
  return (
    <>
      <SiteHeader ctaHref={`https://wa.me/${CONTACT.whatsapp.replace("+", "")}`} ctaLabel="Talep oluştur ↗" />

      <HeroSlider ctaHref={`https://wa.me/${CONTACT.whatsapp.replace("+", "")}`} />

      <section className="intro">
        <span className="eyebrow">İstanbul merkezli · Size özel seyahat</span>
        <h1>
          Yolculuğunuz, <em>size özel.</em>
        </h1>
        <p>Özel turlardan transfere, otel konaklamasından organizasyona kadar her ayrıntıyı sizin için planlıyoruz.</p>
      </section>

      <section className="section">
        <a className="featured" href={`/${BOSPHORUS_DINNER.slug}`}>
          <img src="/assets/bogaz/bogaz-01.jpg" alt={BOSPHORUS_DINNER.title} />
          <div>
            <small>Öne çıkan deneyim · {BOSPHORUS_DINNER.kicker}</small>
            <h3>{BOSPHORUS_DINNER.title}</h3>
            <p>{BOSPHORUS_DINNER.intro}</p>
            <span className="btn">İncele ↗</span>
          </div>
        </a>
      </section>

      <section className="section" id="rezervasyon">
        <span className="eyebrow">Rezervasyon</span>
        <h2>Konaklama, grup ve toplantılar</h2>
        <div className="reservations">
          {RESERVATIONS.map((r) => (
            <a key={r.id} href={`/${r.slug}`}>
              <small>{r.kicker}</small>
              <h3>{r.title}</h3>
              <p>{r.text}</p>
            </a>
          ))}
        </div>
      </section>

      <section className="section" id="hizmetler">
        <span className="eyebrow">Hizmetlerimiz</span>
        <h2>Her yolculuk için doğru dokunuş</h2>
        <div className="services">
          {SERVICES.map((service, i) => (
            <article className="service" key={service.id} id={service.id}>
              <img src={`/assets/${service.image}`} alt={service.title} loading={i > 2 ? "lazy" : undefined} />
              <div>
                <small>{service.kicker}</small>
                <h3>{service.title}</h3>
                <p>{service.text}</p>
              </div>
            </article>
          ))}
        </div>
      </section>

      <section className="section" id="hakkimizda">
        <span className="eyebrow">Hakkımızda</span>
        <h2>Lux Locations Tours</h2>
        <p className="about">{ABOUT}</p>
      </section>

      <section className="section" id="destinasyonlar">
        <span className="eyebrow">Destinasyonlar</span>
        <h2>Gidebileceğiniz şehirler</h2>
        <ul className="cities">
          {DESTINATIONS.map((d) => (
            <li key={d.slug}>{d.name}</li>
          ))}
        </ul>
      </section>      <SiteFooter />
    </>
  );
}
