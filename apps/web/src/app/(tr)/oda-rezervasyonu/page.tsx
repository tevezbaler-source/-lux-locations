import { ReservationView, reservationMetadata } from "@/views/reservation";

export const metadata = reservationMetadata("tr", "oda");

export default function Page() {
  return <ReservationView lang="tr" id="oda" />;
}
