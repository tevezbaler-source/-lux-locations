import { RESERVATIONS, whatsappLink, type Reservation } from "@lux/shared";
import { ReservationForm } from "@/components/reservation-form";
import { SiteFooter } from "@/components/site-footer";
import { SiteHeader } from "@/components/site-header";
import { getDict, localePath, type Locale } from "@/i18n";
import { pageMetadata } from "./meta";

const find = (id: Reservation["id"]) => RESERVATIONS.find((r) => r.id === id)!;

export function reservationMetadata(lang: Locale, id: Reservation["id"]) {
  const t = getDict(lang).reservations[id];
  return pageMetadata(lang, `/${find(id).slug}`, `${t.title} | Lux Locations`, t.text);
}

export function ReservationView({ lang, id }: { lang: Locale; id: Reservation["id"] }) {
  const t = getDict(lang);
  const reservation = find(id);
  const text = t.reservations[id];
  const fields = reservation.fields.map((f) => ({ ...f, ...text.fields[f.key] }));

  return (
    <>
      <SiteHeader lang={lang} path={`/${reservation.slug}`} ctaHref={whatsappLink()} ctaLabel={t.cta.whatsapp} />

      <nav className="tabs">
        {RESERVATIONS.map((r) => (
          <a key={r.id} href={localePath(lang, `/${r.slug}`)} aria-current={r.id === id ? "page" : undefined}>
            {t.reservations[r.id].title}
          </a>
        ))}
      </nav>

      <section className="section narrow">
        <span className="eyebrow">{text.kicker}</span>
        <h2>{text.title}</h2>
        <p>{text.text}</p>
        <ReservationForm
          title={lang === "tr" ? text.title.toLocaleLowerCase("tr") : text.title}
          fields={fields}
          t={t.form}
        />
      </section>

      <SiteFooter lang={lang} />
    </>
  );
}
