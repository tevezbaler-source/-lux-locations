import { CONTACT } from "@lux/shared";
import { getDict, type Locale } from "@/i18n";

export function SiteFooter({ lang }: { lang: Locale }) {
  const t = getDict(lang).footer;
  return (
    <footer id="iletisim" className="site-footer">
      <div className="foot-grid">
        <div>
          <strong>Lux Locations Tours</strong>
          <p>{CONTACT.address}</p>
        </div>
        <div>
          <strong>{t.contact}</strong>
          <p dir="ltr">
            <a href={`tel:${CONTACT.phone}`}>{CONTACT.phoneDisplay}</a>
            <br />
            <a href={`mailto:${CONTACT.infoEmail}`}>{CONTACT.infoEmail}</a>
            <br />
            <a href={`mailto:${CONTACT.email}`}>{CONTACT.email}</a>
          </p>
        </div>
        <div>
          <strong>{t.follow}</strong>
          <p>
            <a href={CONTACT.social.instagram} target="_blank" rel="noreferrer">Instagram</a>
            {" · "}
            <a href={CONTACT.social.facebook} target="_blank" rel="noreferrer">Facebook</a>
            <br />
            <a href={CONTACT.social.twitter} target="_blank" rel="noreferrer">Twitter</a>
            {" · "}
            <a href={CONTACT.social.linkedin} target="_blank" rel="noreferrer">LinkedIn</a>
          </p>
        </div>
      </div>
      <div className="foot-copy">
        <span dir="ltr">© 2022–2026 Lux Locations Tours</span> · {t.rights}
      </div>
    </footer>
  );
}
