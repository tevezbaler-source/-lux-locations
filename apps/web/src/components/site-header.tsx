import { RESERVATIONS } from "@lux/shared";
import { getDict, LOCALES, localePath, type Locale } from "@/i18n";

// Kaydırırken ekranla birlikte gelen (sticky) üst şerit + altındaki gezinme satırı.
// `path` dil seçicinin aynı sayfanın diğer dildeki karşılığına gitmesi için kullanılır.
export function SiteHeader({ lang, path, ctaHref, ctaLabel }: { lang: Locale; path: string; ctaHref: string; ctaLabel: string }) {
  const t = getDict(lang);
  const to = (p: string) => localePath(lang, p);
  const nav = [
    { href: "/oteller", label: t.nav.hotels },
    { href: "/#organizasyon", label: t.nav.organizations },
    { href: "/bogaz-tekne-turu", label: t.nav.bosphorus },
    { href: "/#tur", label: t.nav.tours },
    { href: "/#transfer", label: t.nav.transfer },
    { href: "/#kiralama", label: t.nav.rental },
    { href: "/#rehberlik", label: t.nav.guide },
    { href: "/#hakkimizda", label: t.nav.about },
    { href: "/#iletisim", label: t.nav.contact },
  ];
  const current = LOCALES.find((l) => l.code === lang)!;

  return (
    <header className="site-head">
      <div className="site-bar">
        <details className="lang">
          <summary aria-label={t.languageLabel}>
            <img className="flag" src={`/assets/flags/${current.code}.svg`} alt="" width={20} height={15} /> {current.code.toUpperCase()}
          </summary>
          <ul>
            {LOCALES.map((l) => (
              <li key={l.code}>
                <a href={localePath(l.code, path)} hrefLang={l.code} lang={l.code} aria-current={l.code === lang ? "true" : undefined}>
                  <img className="flag" src={`/assets/flags/${l.code}.svg`} alt="" width={20} height={15} loading="lazy" />
                  {l.name}
                </a>
              </li>
            ))}
          </ul>
        </details>
        <a className="brand" href={to("/")} dir="ltr">
          <img src="/assets/logo-mark.png" alt="" width={56} height={56} />
          <span>
            LUX
            <small>LOCATIONS TOURS</small>
          </span>
        </a>
        <a className="btn" href={ctaHref}>
          {ctaLabel}
        </a>
      </div>
      <nav className="site-nav" aria-label={t.nav.aria}>
        {nav.map((item) => (
          <a key={item.href} href={to(item.href)}>
            {item.label}
          </a>
        ))}
      </nav>
      <div className="site-sub">
        {RESERVATIONS.map((r) => (
          <a key={r.id} href={to(`/${r.slug}`)}>
            {t.reservations[r.id].title}
          </a>
        ))}
      </div>
    </header>
  );
}
