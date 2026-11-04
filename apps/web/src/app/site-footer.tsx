import { CONTACT } from "@lux/shared";

export function SiteFooter() {
  return (
    <footer id="iletisim" className="site-footer">
      <div className="foot-grid">
        <div>
          <strong>Lux Locations Tours</strong>
          <p>{CONTACT.address}</p>
        </div>
        <div>
          <strong>İletişim</strong>
          <p>
            <a href={`tel:${CONTACT.phone}`}>+90 212 523 23 10</a>
            <br />
            <a href={`tel:${CONTACT.phone2}`}>+90 212 523 23 11</a>
            <br />
            <a href={`mailto:${CONTACT.infoEmail}`}>{CONTACT.infoEmail}</a>
            <br />
            <a href={`mailto:${CONTACT.email}`}>{CONTACT.email}</a>
          </p>
        </div>
        <div>
          <strong>Bizi takip edin</strong>
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
      <div className="foot-copy">© 2022–2026 Lux Locations Tours · Tüm hakları saklıdır · TÜRSAB üyesi</div>
    </footer>
  );
}