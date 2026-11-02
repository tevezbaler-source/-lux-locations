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
      <header>
        <a className="brand" href="/">
          LUX LOCATIONS<small>TOURS &amp; TRAVEL</small>
        </a>
        <a className="btn" href={whatsappLink()}>
          WhatsApp ↗
        </a>
      </header>

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

      <footer>
        <span>© 2026 Lux Locations</span>
        <span>
          <a href={`tel:${CONTACT.phone}`}>{CONTACT.phone}</a> · <a href={`mailto:${CONTACT.email}`}>{CONTACT.email}</a>
        </span>
      </footer>
    </>
  );
}
