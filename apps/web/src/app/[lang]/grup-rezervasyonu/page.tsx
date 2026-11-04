import { ReservationView, reservationMetadata } from "@/views/reservation";
import { toLocale } from "../params";

export async function generateMetadata({ params }: PageProps<"/[lang]/grup-rezervasyonu">) {
  return reservationMetadata(toLocale((await params).lang), "grup");
}

export default async function Page({ params }: PageProps<"/[lang]/grup-rezervasyonu">) {
  return <ReservationView lang={toLocale((await params).lang)} id="grup" />;
}
