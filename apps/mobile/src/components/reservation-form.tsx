import {
  colors,
  reservationMessage,
  RESERVATIONS,
  validateReservation,
  whatsappLink,
  type Reservation,
  type ReservationContact,
} from '@lux/shared';
import { useState } from 'react';
import { KeyboardAvoidingView, Linking, Platform, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { BottomTabInset, MaxContentWidth, Spacing } from '@/constants/theme';

const emptyContact: ReservationContact = { full_name: '', phone: '', email: '', notes: '' };

export function ReservationScreen({ id }: { id: Reservation['id'] }) {
  const reservation = RESERVATIONS.find((r) => r.id === id)!;
  const insets = useSafeAreaInsets();
  const [details, setDetails] = useState<Record<string, string>>({});
  const [contact, setContact] = useState(emptyContact);
  const [errors, setErrors] = useState<string[]>([]);

  function submit() {
    const found = validateReservation(reservation, contact, details);
    setErrors(found);
    if (found.length === 0) Linking.openURL(whatsappLink(reservationMessage(reservation, contact, details)));
  }

  return (
    <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        style={styles.scroll}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={[
          styles.content,
          { paddingTop: insets.top + Spacing.four, paddingBottom: insets.bottom + BottomTabInset + Spacing.four },
        ]}>
        <ThemedText style={styles.eyebrow}>{reservation.kicker.toLocaleUpperCase('tr')}</ThemedText>
        <ThemedText style={styles.title}>{reservation.title}</ThemedText>
        <ThemedText themeColor="textSecondary" style={styles.text}>
          {reservation.text}
        </ThemedText>

        {reservation.fields.map((field) => (
          <Field
            key={field.key}
            label={field.optional ? `${field.label} (isteğe bağlı)` : field.label}
            value={details[field.key] ?? ''}
            placeholder={field.type === 'date' ? 'GG.AA.YYYY' : field.placeholder}
            keyboardType={field.type === 'text' ? 'default' : 'number-pad'}
            onChangeText={(v) => setDetails((d) => ({ ...d, [field.key]: v }))}
          />
        ))}

        <ThemedText style={[styles.eyebrow, styles.section]}>İLETİŞİM BİLGİLERİNİZ</ThemedText>
        <Field label="Ad soyad" value={contact.full_name} onChangeText={(v) => setContact((c) => ({ ...c, full_name: v }))} />
        <Field
          label="Telefon"
          value={contact.phone}
          placeholder="+90…"
          keyboardType="phone-pad"
          onChangeText={(v) => setContact((c) => ({ ...c, phone: v }))}
        />
        <Field
          label="E-posta"
          value={contact.email}
          keyboardType="email-address"
          onChangeText={(v) => setContact((c) => ({ ...c, email: v }))}
        />
        <Field
          label="Notunuz (isteğe bağlı)"
          value={contact.notes}
          multiline
          onChangeText={(v) => setContact((c) => ({ ...c, notes: v }))}
        />

        {errors.length > 0 && (
          <View style={styles.errors}>
            {errors.map((e) => (
              <ThemedText key={e} style={styles.errorText}>
                {`• ${e}`}
              </ThemedText>
            ))}
          </View>
        )}

        <Pressable onPress={submit} style={({ pressed }) => [styles.button, pressed && styles.pressed]}>
          <ThemedText style={styles.buttonText}>WhatsApp ile gönder</ThemedText>
        </Pressable>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function Field({
  label,
  ...input
}: { label: string } & Pick<
  React.ComponentProps<typeof TextInput>,
  'value' | 'placeholder' | 'keyboardType' | 'multiline' | 'onChangeText'
>) {
  return (
    <View style={styles.field}>
      <ThemedText style={styles.label}>{label}</ThemedText>
      <TextInput
        {...input}
        autoCapitalize={input.keyboardType === 'email-address' ? 'none' : 'sentences'}
        placeholderTextColor={colors.muted}
        style={[styles.input, input.multiline && styles.multiline]}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  scroll: { flex: 1, backgroundColor: colors.paper },
  content: {
    paddingHorizontal: Spacing.four,
    gap: Spacing.three,
    width: '100%',
    maxWidth: MaxContentWidth,
    alignSelf: 'center',
  },
  eyebrow: { fontSize: 11, letterSpacing: 2, color: colors.gold },
  section: { marginTop: Spacing.three },
  title: { fontFamily: 'serif', fontSize: 30, lineHeight: 38, color: colors.ink },
  text: { fontSize: 14, lineHeight: 21, marginBottom: Spacing.two },
  field: { gap: Spacing.one },
  label: { fontSize: 13, color: colors.ink },
  input: {
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.line,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two + Spacing.one,
    fontSize: 15,
    color: colors.ink,
  },
  multiline: { minHeight: 90, textAlignVertical: 'top' },
  errors: { backgroundColor: '#fbeeea', padding: Spacing.three, gap: Spacing.one },
  errorText: { fontSize: 13, color: '#8a3b2a' },
  button: { backgroundColor: colors.ink, paddingVertical: Spacing.three, alignItems: 'center', marginTop: Spacing.two },
  pressed: { opacity: 0.8 },
  buttonText: { color: colors.white, fontSize: 14, letterSpacing: 1 },
});
