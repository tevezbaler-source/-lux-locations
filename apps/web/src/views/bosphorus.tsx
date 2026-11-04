import { BOSPHORUS_DINNER as tour, whatsappLink } from "@lux/shared";
import { SiteFooter } from "@/components/site-footer";
import { SiteHeader } from "@/components/site-header";
import { fill, getDict, type Locale } from "@/i18n";
import { pageMetadata } from "./meta";

const PATH = `/${tour.slug}`;
const photos = Array.from({ length: tour.photoCount }, (_, i) => `/assets/bogaz/bogaz-${String(i + 1).padStart(2, "0")}.jpg`);
const boat = tour.boat;
const boatPhotos = boat
  ? Array.from({ length: boat.photoCount }, (_, i) => `/assets/${boat.slug}/${boat.slug}-${String(i + 1).padStart(2, "0")}.jpg`)
  : [];

export function bosphorusMetadata(lang: Locale) {
  const t = getDict(lang).bosphorus;
  return pageMetadata(lang, PATH, `${t.title} | Lux Locations`, t.intro);
}

export function BosphorusView({ lang }: { lang: Locale }) {
  const d = getDict(lang);
  const t = d.bosphorus;
  const wa = whatsappLink(t.whatsappMessage);

  return (
    <>
      <SiteHeader lang={lang} path={PATH} ctaHref={wa} ctaLabel={d.cta.request} />

      <section className="hero" style={{ backgroundImage: `linear-gradient(90deg, rgba(11,29,37,.7), rgba(11,29,37,.1)), url('${photos[0]}')` }}>
        <span className="eyebrow">{t.kicker}</span>
        <h1>{t.title}</h1>
        <p>{t.intro}</p>
      </section>

      <section className="section">
        <span className="eyebrow">{t.highlightsEyebrow}</span>
        <h2>{t.highlightsTitle}</h2>
        <div className="highlights">
          {t.highlights.map((h) => (
            <div key={h.title}>
              <h3>{h.title}</h3>
              <p>{h.text}</p>
            </div>
          ))}
        </div>
      </section>

      {boat && (
        <section className="section">
          <span className="eyebrow">{t.boatEyebrow}</span>
          <h2>{boat.name}</h2>
          <p className="lead">{t.boatText}</p>
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
              aria-label={fill(t.boatVideo, { name: boat.name })}
            />
            {boatPhotos.slice(0, -1).map((src, i) => (
              <img key={src} src={src} alt={fill(t.boatPhoto, { name: boat.name, n: i + 1 })} loading="lazy" />
            ))}
            <img className="tall" src={boatPhotos.at(-1)} alt={fill(t.boatNight, { name: boat.name })} loading="lazy" />
          </div>
        </section>
      )}

      <section className="section">
        <span className="eyebrow">{t.filmEyebrow}</span>
        <h2>{t.filmTitle}</h2>
        <video className="film" src="/assets/video/lux-tanitim.mp4" poster="/assets/video/lux-tanitim.jpg" controls playsInline preload="none" />
      </section>

      <section className="section">
        <span className="eyebrow">{t.photosEyebrow}</span>
        <h2>{t.photosTitle}</h2>
        <div className="gallery">
          {photos.slice(1).map((src, i) => (
            <img key={src} src={src} alt={fill(t.photo, { title: t.title, n: i + 2 })} loading="lazy" />
          ))}
        </div>
      </section>

      <section className="section cta">
        <h2>{t.ctaTitle}</h2>
        <p>{t.ctaText}</p>
        <a className="btn" href={wa}>
          {t.ctaButton}
        </a>
      </section>

      <SiteFooter lang={lang} />
    </>
  );
}
