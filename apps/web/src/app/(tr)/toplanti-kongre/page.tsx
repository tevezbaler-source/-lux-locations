import { ReservationView, reservationMetadata } from "@/views/reservation";

export const metadata = reservationMetadata("tr", "kongre");

export default function Page() {
  return <ReservationView lang="tr" id="kongre" />;
}
