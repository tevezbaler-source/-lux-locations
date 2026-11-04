"use client";

import { whatsappLink, type ReservationContact, type ServiceField } from "@lux/shared";
import { useState } from "react";
import { fill, type FormText } from "@/i18n/types";

const emptyContact: ReservationContact = { full_name: "", phone: "", email: "", notes: "" };

// Alanların etiketleri seçili dilde gelir; talep, aynı dilde hazırlanmış bir WhatsApp mesajı olarak gönderilir.
export function ReservationForm({ title, fields, t }: { title: string; fields: ServiceField[]; t: FormText }) {
  const [details, setDetails] = useState<Record<string, string>>({});
  const [contact, setContact] = useState(emptyContact);
  const [errors, setErrors] = useState<string[]>([]);

  function validate(): string[] {
    const found: string[] = [];
    if (!contact.full_name.trim()) found.push(t.errName);
    if (!contact.phone.trim() && !contact.email.trim()) found.push(t.errContact);
    if (contact.email.trim() && !/^\S+@\S+\.\S+$/.test(contact.email.trim())) found.push(t.errEmail);
    for (const field of fields) {
      if (!field.optional && !details[field.key]?.trim()) found.push(fill(t.errRequired, { field: field.label }));
    }
    return found;
  }

  function message(): string {
    const lines = [fill(t.messageIntro, { title }), ""];
    for (const field of fields) {
      const value = details[field.key]?.trim();
      if (value) lines.push(`${field.label}: ${value}`);
    }
    lines.push("", `${t.fullName}: ${contact.full_name.trim()}`);
    if (contact.phone.trim()) lines.push(`${t.phone}: ${contact.phone.trim()}`);
    if (contact.email.trim()) lines.push(`${t.email}: ${contact.email.trim()}`);
    if (contact.notes.trim()) lines.push(`${t.noteLabel}: ${contact.notes.trim()}`);
    return lines.join("\n");
  }

  function submit(e: React.FormEvent) {
    e.preventDefault();
    const found = validate();
    setErrors(found);
    if (found.length === 0) window.open(whatsappLink(message()), "_blank");
  }

  const contactField = (key: keyof ReservationContact, label: string, type = "text") => (
    <label>
      {label}
      <input type={type} value={contact[key]} onChange={(e) => setContact((c) => ({ ...c, [key]: e.target.value }))} />
    </label>
  );

  return (
    <form className="form" onSubmit={submit} noValidate>
      {fields.map((field) => (
        <label key={field.key}>
          {field.optional ? `${field.label} (${t.optional})` : field.label}
          <input
            type={field.type}
            min={field.type === "number" ? 0 : undefined}
            placeholder={field.placeholder}
            value={details[field.key] ?? ""}
            onChange={(e) => setDetails((d) => ({ ...d, [field.key]: e.target.value }))}
          />
        </label>
      ))}
      <span className="eyebrow wide">{t.contactEyebrow}</span>
      {contactField("full_name", t.fullName)}
      {contactField("phone", t.phone, "tel")}
      {contactField("email", t.email, "email")}
      <label className="wide">
        {t.notes}
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
        {t.submit}
      </button>
    </form>
  );
}
