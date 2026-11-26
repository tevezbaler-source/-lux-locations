import { DestinationsView, destinationsMetadata } from "@/views/destination";

export const metadata = destinationsMetadata("tr");

export default function Page() {
  return <DestinationsView lang="tr" />;
}
