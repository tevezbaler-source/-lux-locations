import { ReservationPage, reservationMetadata } from "../reservation-page";

export const metadata = reservationMetadata("kongre");

export default function Page() {
  return <ReservationPage id="kongre" />;
}
