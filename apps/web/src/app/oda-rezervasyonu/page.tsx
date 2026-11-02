import { ReservationPage, reservationMetadata } from "../reservation-page";

export const metadata = reservationMetadata("oda");

export default function Page() {
  return <ReservationPage id="oda" />;
}
