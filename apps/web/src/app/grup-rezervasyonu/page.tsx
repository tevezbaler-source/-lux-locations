import { ReservationPage, reservationMetadata } from "../reservation-page";

export const metadata = reservationMetadata("grup");

export default function Page() {
  return <ReservationPage id="grup" />;
}
