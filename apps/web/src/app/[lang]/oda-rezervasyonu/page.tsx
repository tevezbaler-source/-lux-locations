import { ReservationView, reservationMetadata } from "@/views/reservation";
import { toLocale } from "../params";

export async function generateMetadata({ params }: PageProps<"/[lang]/oda-rezervasyonu">) {
  return reservationMetadata(toLocale((await params).lang), "oda");
}

export default async function Page({ params }: PageProps<"/[lang]/oda-rezervasyonu">) {
  return <ReservationView lang={toLocale((await params).lang)} id="oda" />;
}
