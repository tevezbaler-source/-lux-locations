import { HotelsView, hotelsMetadata } from "@/views/hotels-page";
import { toLocale } from "../params";

export async function generateMetadata({ params }: PageProps<"/[lang]/oteller">) {
  return hotelsMetadata(toLocale((await params).lang));
}

export default async function Page({ params }: PageProps<"/[lang]/oteller">) {
  return <HotelsView lang={toLocale((await params).lang)} />;
}
