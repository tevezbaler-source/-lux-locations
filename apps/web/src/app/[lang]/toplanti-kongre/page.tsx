import { ReservationView, reservationMetadata } from "@/views/reservation";
import { toLocale } from "../params";

export async function generateMetadata({ params }: PageProps<"/[lang]/toplanti-kongre">) {
  return reservationMetadata(toLocale((await params).lang), "kongre");
}

export default async function Page({ params }: PageProps<"/[lang]/toplanti-kongre">) {
  return <ReservationView lang={toLocale((await params).lang)} id="kongre" />;
}
