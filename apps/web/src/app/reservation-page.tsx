import { SiteFooter } from "./site-footer";
import { SiteHeader } from "./site-header";
import { CONTACT, RESERVATIONS, whatsappLink, type Reservation } from "@lux/shared";
import type { Metadata } from "next";

import { ReservationForm } from "./reservation-form";

export function reservationMetadata(id: Reservation["id"]): Metadata {
  const r = RESERVATIONS.find((x) => x.id === id)!;
  return { title: `${r.title} | Lux Locations`, description: r.text };
}

export function ReservationPage({ id }: { id: Reservation["id"] }) {
  const reservation = RESERVATIONS.find((r) => r.id === id)!;

  return (
    <>
      <SiteHeader ctaHref={whatsappLink()} ctaLabel="WhatsApp ↗" />

      <nav className="tabs">
        {RESERVATIONS.map((r) => (
          <a key={r.id} href={`/${r.slug}`} aria-current={r.id === id ? "page" : undefined}>
            {r.title}
          </a>
        ))}
      </nav>

      <section className="section narrow">
        <span className="eyebrow">{reservation.kicker}</span>
        <h2>{reservation.title}</h2>
        <p>{reservation.text}</p>
        <ReservationForm id={id} />
      </section>

      <SiteFooter />
    </>
  );
}
