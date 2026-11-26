import { DESTINATIONS, whatsappLink } from "@lux/shared";
import { SiteFooter } from "@/components/site-footer";
import { SiteHeader } from "@/components/site-header";
import { DEST_TEXT, type DestinationSlug } from "@/content/destinations";
import { SERVICE_PAGES, SERVICE_TEXT } from "@/content/services";
import { fill, getDict, localePath, type Locale } from "@/i18n";
import { pageMetadata } from "./meta";
import { absUrl, JsonLd, shortText, SITE_URL } from "./seo";

// Destinasyon sayfaları: /destinasyonlar/ (liste) ve /destinasyonlar/<slug>/
export const DEST_PATH = "/destinasyonlar";
export const DEST_SLUGS = DESTINATIONS.map((d) => d.slug as DestinationSlug);
export const destPath = (slug: DestinationSlug) => `${DEST_PATH}/${slug}`;
export const isDestSlug = (s: string): s is DestinationSlug => (DEST_SLUGS as string[]).includes(s);

const cityName = (lang: Locale, slug: DestinationSlug) =>
  getDict(lang).destinations[slug] ?? DESTINATIONS.find((d) => d.slug === slug)!.name;
const places = (list: string) => list.split(/\s*[;；؛]\s*/).filter(Boolean);
// İstanbul sayfasında Boğaz fotoğrafı, diğerlerinde genel tur görseli
const imageOf = (slug: DestinationSlug) => (slug === "istanbul" ? "/assets/bosphorus-palace.jpg" : "/assets/tours.jpg");

export function destinationsMetadata(lang: Locale) {
  const ui = DEST_TEXT[lang].ui;
  return pageMetadata(lang, DEST_PATH, ui.indexSeoTitle, shortText(ui.indexIntro));
}

export function destinationMetadata(lang: Locale, slug: DestinationSlug) {
  const all = DEST_TEXT[lang];
  const city = cityName(lang, slug);
  return pageMetadata(lang, destPath(slug), fill(all.ui.seoTitle, { city }), shortText(all[slug][0]), imageOf(slug));
}

function OtherCities({ lang, current }: { lang: Locale; current?: DestinationSlug }) {
  return (
    <ul className="cities linked">
      {DEST_SLUGS.filter((s) => s !== current).map((s) => (
        <li key={s}>
          <a href={localePath(lang, `${destPath(s)}/`)}>{cityName(lang, s)}</a>
        </li>
      ))}
    </ul>
  );
}

export function DestinationsView({ lang }: { lang: Locale }) {
  const d = getDict(lang);
  const all = DEST_TEXT[lang];
  const url = absUrl(localePath(lang, `${DEST_PATH}/`));

  return (
    <>
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "ItemList",
          name: all.ui.indexTitle,
          url,
          itemListElement: DEST_SLUGS.map((s, i) => ({
            "@type": "ListItem",
            position: i + 1,
            name: cityName(lang, s),
            url: absUrl(localePath(lang, `${destPath(s)}/`)),
          })),
        }}
      />
      <SiteHeader lang={lang} path={DEST_PATH} ctaHref={whatsappLink()} ctaLabel={d.cta.whatsapp} />

      <section className="section narrow page-title">
        <span className="eyebrow">{all.ui.eyebrow}</span>
        <h1>{all.ui.indexTitle}</h1>
        <p className="lead">{all.ui.indexIntro}</p>
      </section>

      <section className="section">
        <div className="dest-grid">
          {DEST_SLUGS.map((s) => (
            <a key={s} className="dest-card" href={localePath(lang, `${destPath(s)}/`)}>
              <h2>{cityName(lang, s)}</h2>
              <p>{places(all[s][1]).slice(0, 3).join(" · ")}</p>
            </a>
          ))}
        </div>
      </section>

      <SiteFooter lang={lang} />
    </>
  );
}

export function DestinationView({ lang, slug }: { lang: Locale; slug: DestinationSlug }) {
  const d = getDict(lang);
  const all = DEST_TEXT[lang];
  const ui = all.ui;
  const [intro, highlights] = all[slug];
  const city = cityName(lang, slug);
  const url = absUrl(localePath(lang, `${destPath(slug)}/`));
  const services = SERVICE_TEXT[lang];
  const wa = whatsappLink(`${city}: `);

  return (
    <>
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@graph": [
            {
              "@type": "TouristDestination",
              "@id": `${url}#destination`,
              name: city,
              description: intro,
              url,
              includesAttraction: places(highlights).map((name) => ({ "@type": "TouristAttraction", name })),
              provider: { "@id": `${SITE_URL}/#organization` },
            },
            {
              "@type": "BreadcrumbList",
              itemListElement: [
                { "@type": "ListItem", position: 1, name: "Lux Locations Tours", item: absUrl(localePath(lang, "/")) },
                { "@type": "ListItem", position: 2, name: ui.eyebrow, item: absUrl(localePath(lang, `${DEST_PATH}/`)) },
                { "@type": "ListItem", position: 3, name: city, item: url },
              ],
            },
          ],
        }}
      />
      <SiteHeader lang={lang} path={destPath(slug)} ctaHref={wa} ctaLabel={d.cta.whatsapp} />

      <section className="section narrow page-title">
        <a className="eyebrow" href={localePath(lang, `${DEST_PATH}/`)}>
          {ui.eyebrow}
        </a>
        <h1>{city}</h1>
        <p className="kicker">{fill(ui.kicker, { city })}</p>
        <p className="lead">{intro}</p>
        <span className="eyebrow">{ui.highlights}</span>
        <ul className="points">
          {places(highlights).map((p) => (
            <li key={p}>{p}</li>
          ))}
        </ul>
      </section>

      <section className="section narrow">
        <span className="eyebrow">{fill(ui.arrange, { city })}</span>
        <p className="lead">{fill(ui.arrangeText, { city })}</p>
        <nav className="tabs plain">
          <a href={localePath(lang, "/oteller/")}>{ui.hotels}</a>
          {slug === "istanbul" && <a href={localePath(lang, "/bogaz-tekne-turu/")}>{d.nav.bosphorus}</a>}
          {SERVICE_PAGES.map((s) => (
            <a key={s.slug} href={localePath(lang, `/hizmetler/${s.slug}/`)}>
              {services[s.slug].title}
            </a>
          ))}
        </nav>
      </section>

      <section className="section cta">
        <h2>{fill(ui.ctaTitle, { city })}</h2>
        <p>{ui.ctaText}</p>
        <a className="btn" href={wa}>
          {ui.ctaButton}
        </a>
      </section>

      <section className="section">
        <span className="eyebrow">{ui.others}</span>
        <OtherCities lang={lang} current={slug} />
      </section>

      <SiteFooter lang={lang} />
    </>
  );
}
