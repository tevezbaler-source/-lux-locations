import { colors, RESERVATIONS, saveRequestCopy, type Reservation, type ReservationContact } from '@lux/shared';
import { fill } from '@lux/shared/i18n';
import { useState } from 'react';
import { KeyboardAvoidingView, Linking, Platform, Pressable, StyleSheet, TextInput, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Button, Eyebrow, LangButton, Screen, ui } from '@/components/ui';
import { Spacing } from '@/constants/theme';
import { useApp, waLink } from '@/lib/app-state';
import { SITE } from '@/lib/site';

const emptyContact: ReservationContact = { full_name: '', phone: '', email: '', notes: '' };

// Alanlar seçili dilde; talep, aynı dilde hazırlanmış bir WhatsApp mesajı olarak gönderilir (sitedeki formla aynı).
export function ReservationScreen({ id, onChange }: { id: Reservation['id']; onChange: (id: Reservation['id']) => void }) {
  const { t, lang, whatsapp } = useApp();
  const reservation = RESERVATIONS.find((r) => r.id === id)!;
  const text = t.reservations[id];
  const fields = reservation.fields.map((f) => ({ ...f, ...text.fields[f.key] }));
  const f = t.form;
  const [details, setDetails] = useState<Record<string, string>>({});
  const [contact, setContact] = useState(emptyContact);
  const [errors, setErrors] = useState<string[]>([]);

  function submit() {
    const found: string[] = [];
    if (!contact.full_name.trim()) found.push(f.errName);
    if (!contact.phone.trim() && !contact.email.trim()) found.push(f.errContact);
    if (contact.email.trim() && !/^\S+@\S+\.\S+$/.test(contact.email.trim())) found.push(f.errEmail);
    for (const field of fields) if (!field.optional && !details[field.key]?.trim()) found.push(fill(f.errRequired, { field: field.label }));
    setErrors(found);
    if (found.length) return;
    const lines = [fill(f.messageIntro, { title: text.title }), ''];
    for (const field of fields) {
      const value = details[field.key]?.trim();
      if (value) lines.push(`${field.label}: ${value}`);
    }
    lines.push('', `${f.fullName}: ${contact.full_name.trim()}`);
    if (contact.phone.trim()) lines.push(`${f.phone}: ${contact.phone.trim()}`);
    if (contact.email.trim()) lines.push(`${f.email}: ${contact.email.trim()}`);
    if (contact.notes.trim()) lines.push(`${f.noteLabel}: ${contact.notes.trim()}`);
    const message = lines.join('\n');
    saveRequestCopy(SITE, {
      source: 'app',
      lang,
      title: text.title,
      full_name: contact.full_name.trim(),
      phone: contact.phone.trim(),
      email: contact.email.trim(),
      message,
    });
    Linking.openURL(waLink(whatsapp, message));
  }

  return (
    <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Screen>
        <View style={ui.row}>
          <Eyebrow>{t.home.reservationsEyebrow.toLocaleUpperCase(lang)}</Eyebrow>
          <LangButton />
        </View>
        <View style={styles.segment}>
          {RESERVATIONS.map((r) => (
            <Pressable
              key={r.id}
              onPress={() => {
                onChange(r.id);
                setDetails({});
                setErrors([]);
              }}
              style={[styles.segButton, r.id === id && styles.segOn]}>
              <ThemedText style={[styles.segText, r.id === id && styles.segTextOn]} numberOfLines={2}>
                {t.reservations[r.id].title}
              </ThemedText>
            </Pressable>
          ))}
        </View>

        <ThemedText style={ui.kicker}>{text.kicker.toLocaleUpperCase(lang)}</ThemedText>
        <ThemedText style={ui.title}>{text.title}</ThemedText>
        <ThemedText style={ui.text}>{text.text}</ThemedText>

        {fields.map((field) => (
          <Field
            key={`${id}-${field.key}`}
            label={field.optional ? `${field.label} (${f.optional})` : field.label}
            value={details[field.key] ?? ''}
            placeholder={field.type === 'date' ? (lang === 'tr' ? 'GG.AA.YYYY' : 'DD.MM.YYYY') : field.placeholder}
            keyboardType={field.type === 'number' ? 'number-pad' : field.type === 'date' ? 'numbers-and-punctuation' : 'default'}
            onChangeText={(v) => setDetails((d) => ({ ...d, [field.key]: v }))}
          />
        ))}

        <Eyebrow style={styles.section}>{f.contactEyebrow.toLocaleUpperCase(lang)}</Eyebrow>
        <Field label={f.fullName} value={contact.full_name} onChangeText={(v) => setContact((c) => ({ ...c, full_name: v }))} />
        <Field
          label={f.phone}
          value={contact.phone}
          placeholder="+90…"
          keyboardType="phone-pad"
          onChangeText={(v) => setContact((c) => ({ ...c, phone: v }))}
        />
        <Field
          label={f.email}
          value={contact.email}
          keyboardType="email-address"
          onChangeText={(v) => setContact((c) => ({ ...c, email: v }))}
        />
        <Field label={f.notes} value={contact.notes} multiline onChangeText={(v) => setContact((c) => ({ ...c, notes: v }))} />

        {errors.length > 0 && (
          <View style={styles.errors}>
            {errors.map((e) => (
              <ThemedText key={e} style={styles.errorText}>
                {`• ${e}`}
              </ThemedText>
            ))}
          </View>
        )}

        <Button label={f.submit} onPress={submit} />
      </Screen>
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
  section: { marginTop: Spacing.three },
  segment: { flexDirection: 'row', gap: Spacing.one },
  segButton: { flex: 1, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.white, padding: Spacing.two, justifyContent: 'center' },
  segOn: { backgroundColor: colors.ink, borderColor: colors.ink },
  segText: { fontSize: 12, lineHeight: 16, color: colors.ink, textAlign: 'center' },
  segTextOn: { color: colors.white },
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
});
