import { HotelsView, hotelsMetadata } from "@/views/hotels-page";

export const metadata = hotelsMetadata("tr");

export default function Page() {
  return <HotelsView lang="tr" />;
}
