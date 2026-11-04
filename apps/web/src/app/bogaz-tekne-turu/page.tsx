import { SiteFooter } from "../site-footer";
import { SiteHeader } from "../site-header";
import type { Metadata } from "next";
import { BOSPHORUS_DINNER as tour, CONTACT, whatsappLink } from "@lux/shared";

export const metadata: Metadata = {
  title: `${tour.title} | Lux Locations`,
  description: tour.intro,
};

const photos = Array.from({ length: tour.photoCount }, (_, i) => `/assets/bogaz/bogaz-${String(i + 1).padStart(2, "0")}.jpg`);

export default function BosphorusDinnerPage() {
  return (
    <>
      <SiteHeader ctaHref={whatsappLink(tour.whatsappMessage)} ctaLabel="Talep oluştur ↗" />

      <section className="hero" style={{ backgroundImage: `linear-gradient(90deg, rgba(11,29,37,.7), rgba(11,29,37,.1)), url('${photos[0]}')` }}>
        <span className="eyebrow">{tour.kicker}</span>
        <h1>{tour.title}</h1>
        <p>{tour.intro}</p>
      </section>

      <section className="section">
        <span className="eyebrow">Neler var</span>
        <h2>Boğaz’da size ayrılmış bir akşam</h2>
        <div className="highlights">
          {tour.highlights.map((h) => (
            <div key={h.title}>
              <h3>{h.title}</h3>
              <p>{h.text}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="section">
        <span className="eyebrow">Fotoğraflar</span>
        <h2>Teknemizden kareler</h2>
        <div className="gallery">
          {photos.slice(1).map((src, i) => (
            <img key={src} src={src} alt={`${tour.title} — fotoğraf ${i + 2}`} loading="lazy" />
          ))}
        </div>
      </section>

      <section className="section cta">
        <h2>Tarihinizi ayıralım</h2>
        <p>Tarih, kişi sayısı ve özel isteklerinizi yazın; menü ve fiyatla birlikte size dönelim.</p>
        <a className="btn" href={whatsappLink(tour.whatsappMessage)}>
          WhatsApp ile yazın ↗
        </a>
      </section>

      <SiteFooter />
    </>
  );
}
