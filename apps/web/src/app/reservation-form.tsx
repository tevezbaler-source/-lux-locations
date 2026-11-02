"use client";

import {
  reservationMessage,
  RESERVATIONS,
  validateReservation,
  whatsappLink,
  type Reservation,
  type ReservationContact,
} from "@lux/shared";
import { useState } from "react";

const emptyContact: ReservationContact = { full_name: "", phone: "", email: "", notes: "" };

export function ReservationForm({ id }: { id: Reservation["id"] }) {
  const reservation = RESERVATIONS.find((r) => r.id === id)!;
  const [details, setDetails] = useState<Record<string, string>>({});
  const [contact, setContact] = useState(emptyContact);
  const [errors, setErrors] = useState<string[]>([]);

  function submit(e: React.FormEvent) {
    e.preventDefault();
    const found = validateReservation(reservation, contact, details);
    setErrors(found);
    if (found.length === 0) window.open(whatsappLink(reservationMessage(reservation, contact, details)), "_blank");
  }

  const contactField = (key: keyof ReservationContact, label: string, type = "text") => (
    <label>
      {label}
      <input type={type} value={contact[key]} onChange={(e) => setContact((c) => ({ ...c, [key]: e.target.value }))} />
    </label>
  );

  return (
    <form className="form" onSubmit={submit} noValidate>
      {reservation.fields.map((field) => (
        <label key={field.key}>
          {field.optional ? `${field.label} (isteğe bağlı)` : field.label}
          <input
            type={field.type}
            min={field.type === "number" ? 0 : undefined}
            placeholder={field.placeholder}
            value={details[field.key] ?? ""}
            onChange={(e) => setDetails((d) => ({ ...d, [field.key]: e.target.value }))}
          />
        </label>
      ))}
      <span className="eyebrow wide">İletişim bilgileriniz</span>
      {contactField("full_name", "Ad soyad")}
      {contactField("phone", "Telefon", "tel")}
      {contactField("email", "E-posta", "email")}
      <label className="wide">
        Notunuz (isteğe bağlı)
        <textarea rows={4} value={contact.notes} onChange={(e) => setContact((c) => ({ ...c, notes: e.target.value }))} />
      </label>
      {errors.length > 0 && (
        <ul className="errors wide">
          {errors.map((e) => (
            <li key={e}>{e}</li>
          ))}
        </ul>
      )}
      <button className="btn wide" type="submit">
        WhatsApp ile gönder ↗
      </button>
    </form>
  );
}
