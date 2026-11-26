import { CONTACT, whatsappLink } from "@lux/shared";
import { ReservationForm } from "@/components/reservation-form";
import { SiteFooter } from "@/components/site-footer";
import { SiteHeader } from "@/components/site-header";
import { getDict, localePath, type Locale } from "@/i18n";
import { SERVICE_PAGES, SERVICE_TEXT, type ServiceSlug } from "@/content/services";
import { pageMetadata } from "./meta";
import { absUrl, JsonLd, SITE_URL } from "./seo";

// Hizmet sayfaları: /hizmetler/<slug>/ (Türkçe kökte, diğer diller /en/hizmetler/… ).
export const SERVICE_SLUGS = SERVICE_PAGES.map((s) => s.slug);
export const servicePath = (slug: ServiceSlug) => `/hizmetler/${slug}`;
export const isServiceSlug = (s: string): s is ServiceSlug => (SERVICE_SLUGS as string[]).includes(s);

const find = (slug: ServiceSlug) => SERVICE_PAGES.find((s) => s.slug === slug)!;

export function serviceMetadata(lang: Locale, slug: ServiceSlug) {
  const t = SERVICE_TEXT[lang][slug];
  return pageMetadata(lang, servicePath(slug), t.seoTitle, t.description, `/assets/${find(slug).image}`);
}

export function ServiceView({ lang, slug }: { lang: Locale; slug: ServiceSlug }) {
  const d = getDict(lang);
  const all = SERVICE_TEXT[lang];
  const t = all[slug];
  const page = find(slug);
  const url = absUrl(localePath(lang, `${servicePath(slug)}/`));
  const fields = page.fields.map((f) => ({ ...f, label: t.fields[f.key]?.[0] ?? f.key, placeholder: t.fields[f.key]?.[1] }));
  const wa = whatsappLink(`${t.title}: `);

  return (
    <>
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@graph": [
            {
              "@type": "Service",
              "@id": `${url}#service`,
              name: t.title,
              description: t.description,
              url,
              image: absUrl(`/assets/${page.image}`),
              serviceType: t.title,
              areaServed: { "@type": "Country", name: "Türkiye" },
              provider: { "@id": `${SITE_URL}/#organization` },
              availableChannel: { "@type": "ServiceChannel", servicePhone: CONTACT.phone, serviceUrl: url },
            },
            {
              "@type": "FAQPage",
              "@id": `${url}#faq`,
              mainEntity: t.faq.map(([q, a]) => ({ "@type": "Question", name: q, acceptedAnswer: { "@type": "Answer", text: a } })),
            },
            {
              "@type": "BreadcrumbList",
              itemListElement: [
                { "@type": "ListItem", position: 1, name: "Lux Locations Tours", item: absUrl(localePath(lang, "/")) },
                { "@type": "ListItem", position: 2, name: t.title, item: url },
              ],
            },
          ],
        }}
      />
      <SiteHeader lang={lang} path={servicePath(slug)} ctaHref={wa} ctaLabel={all.ui.whatsapp} />

      <section
        className="hero"
        style={{ backgroundImage: `linear-gradient(90deg, rgba(11,29,37,.75), rgba(11,29,37,.15)), url('/assets/${page.image}')` }}
      >
        <span className="eyebrow">{all.ui.eyebrow}</span>
        <h1>{t.title}</h1>
        <p>{t.kicker}</p>
      </section>

      <section className="section narrow">
        <p className="lead">{t.intro}</p>
        <span className="eyebrow">{all.ui.included}</span>
        <ul className="points">
          {t.points.map((p) => (
            <li key={p}>{p}</li>
          ))}
        </ul>
      </section>

      <section className="section narrow" id="talep">
        <span className="eyebrow">{all.ui.formTitle}</span>
        <h2>{t.title}</h2>
        <p>{all.ui.formText}</p>
        <ReservationForm category={page.category} title={t.title} fields={fields} t={d.form} />
      </section>

      <section className="section narrow">
        <span className="eyebrow">{all.ui.faq}</span>
        <div className="faq">
          {t.faq.map(([q, a]) => (
            <details key={q}>
              <summary>{q}</summary>
              <p>{a}</p>
            </details>
          ))}
        </div>
      </section>

      <section className="section">
        <span className="eyebrow">{all.ui.other}</span>
        <nav className="tabs plain">
          {SERVICE_PAGES.map((s) => (
            <a key={s.slug} href={localePath(lang, `${servicePath(s.slug)}/`)} aria-current={s.slug === slug ? "page" : undefined}>
              {all[s.slug].title}
            </a>
          ))}
        </nav>
      </section>

      <SiteFooter lang={lang} />
    </>
  );
}
