import { ReservationView, reservationMetadata } from "@/views/reservation";

export const metadata = reservationMetadata("tr", "grup");

export default function Page() {
  return <ReservationView lang="tr" id="grup" />;
}
