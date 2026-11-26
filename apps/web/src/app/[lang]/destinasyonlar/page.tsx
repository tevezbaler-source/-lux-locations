import { DestinationsView, destinationsMetadata } from "@/views/destination";
import { toLocale } from "../params";

export async function generateMetadata({ params }: PageProps<"/[lang]/destinasyonlar">) {
  return destinationsMetadata(toLocale((await params).lang));
}

export default async function Page({ params }: PageProps<"/[lang]/destinasyonlar">) {
  return <DestinationsView lang={toLocale((await params).lang)} />;
}
