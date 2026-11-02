import { BOSPHORUS_DINNER, CONTACT, SERVICES } from "@lux/shared";

export default function Home() {
  return (
    <>
      <header>
        <a className="brand" href="/">
          LUX LOCATIONS<small>TOURS &amp; TRAVEL</small>
        </a>
        <a className="btn" href={`https://wa.me/${CONTACT.whatsapp.replace("+", "")}`}>
          Talep oluştur ↗
        </a>
      </header>

      <section className="hero">
        <span className="eyebrow">İstanbul merkezli · Size özel seyahat</span>
        <h1>
          Yolculuğunuz, <em>size özel.</em>
        </h1>
        <p>Özel turlardan transfere, villa konaklamasından organizasyona kadar her ayrıntıyı sizin için planlıyoruz.</p>
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

      <footer>
        <span>© 2026 Lux Locations</span>
        <span>
          <a href={`tel:${CONTACT.phone}`}>{CONTACT.phone}</a> · <a href={`mailto:${CONTACT.email}`}>{CONTACT.email}</a>
        </span>
      </footer>
    </>
  );
}
