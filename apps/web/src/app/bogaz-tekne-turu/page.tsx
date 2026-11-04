import { SiteFooter } from "../site-footer";
import { SiteHeader } from "../site-header";
import type { Metadata } from "next";
import { BOSPHORUS_DINNER as tour, CONTACT, whatsappLink } from "@lux/shared";

export const metadata: Metadata = {
  title: `${tour.title} | Lux Locations`,
  description: tour.intro,
};

const photos = Array.from({ length: tour.photoCount }, (_, i) => `/assets/bogaz/bogaz-${String(i + 1).padStart(2, "0")}.jpg`);
const boat = tour.boat;
const boatPhotos = boat
  ? Array.from({ length: boat.photoCount }, (_, i) => `/assets/${boat.slug}/${boat.slug}-${String(i + 1).padStart(2, "0")}.jpg`)
  : [];

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

      {boat && (
        <section className="section">
          <span className="eyebrow">Teknemiz</span>
          <h2>{boat.name}</h2>
          <p className="lead">{boat.text}</p>
          <div className="boat-media">
            <video
              className="tall"
              src={`/assets/${boat.slug}/${boat.video}`}
              poster={`/assets/${boat.slug}/${boat.poster}`}
              autoPlay
              muted
              loop
              playsInline
              preload="metadata"
              aria-label={`${boat.name} — Boğaz’da gece, drone çekimi`}
            />
            {boatPhotos.slice(0, -1).map((src, i) => (
              <img key={src} src={src} alt={`${boat.name} teknesi — fotoğraf ${i + 1}`} loading="lazy" />
            ))}
            <img className="tall" src={boatPhotos.at(-1)} alt={`${boat.name} teknesi gece`} loading="lazy" />
          </div>
        </section>
      )}

      <section className="section">
        <span className="eyebrow">Tanıtım filmi</span>
        <h2>Lux Locations ile Boğaz’da bir akşam</h2>
        <video
          className="film"
          src="/assets/video/lux-tanitim.mp4"
          poster="/assets/video/lux-tanitim.jpg"
          controls
          playsInline
          preload="none"
        />
      </section>

      <section className="section">
        <span className="eyebrow">Fotoğraflar</span>
        <h2>Salondan kareler</h2>
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
