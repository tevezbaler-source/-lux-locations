import { RESERVATIONS, type Reservation } from '@lux/shared';
import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';

import { ReservationScreen } from '@/components/reservation-form';

export default function Screen() {
  const { tip } = useLocalSearchParams<{ tip?: string }>();
  const fromLink = RESERVATIONS.find((r) => r.id === tip)?.id;
  const [id, setId] = useState<Reservation['id']>(fromLink ?? 'oda');
  useEffect(() => {
    if (fromLink) setId(fromLink);
  }, [fromLink]);
  return <ReservationScreen id={id} onChange={setId} />;
}
