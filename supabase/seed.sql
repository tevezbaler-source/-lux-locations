-- packages/shared/src/catalog.ts ile aynı içerik.
insert into public.services (id, title, kicker, tags, image_url, sort) values
  ('tur', 'Özel turlar', 'Keşfetmenin size özel hali', '{"Şehir keşifleri","Kişisel rota","Kültür & deneyim"}', '/assets/tours.jpg', 1),
  ('transfer', 'Transfer', 'Rahat bir başlangıç', '{"Havalimanı","Şehir içi","Özel ulaşım"}', '/assets/transfer.jpg', 2),
  ('konaklama', 'Oteller', 'Kendinize ayırdığınız alan', '{"Otel seçenekleri","Kahvaltı & oda","Kişisel tercihler"}', '/assets/villa.jpg', 3),
  ('organizasyon', 'Organizasyon', 'Hatırlanmaya değer anlar', '{"Özel davetler","Kurumsal buluşmalar","Etkinlik planlama"}', '/assets/events.jpg', 4),
  ('kiralama', 'Kiralama', 'Planınıza uygun seçenekler', '{"Kişisel ihtiyaçlar","Tarih aralığı","Teslim planlama"}', '/assets/rental.jpg', 5),
  ('rehberlik', 'Rehberlik', 'Her şehrin bir hikâyesi var', '{"Kültür & tarih","Dil tercihi","Esnek süre"}', '/assets/guide.jpg', 6);

insert into public.destinations (slug, name, country, sort) values
  ('istanbul', 'İstanbul', 'Türkiye', 1),
  ('kapadokya', 'Kapadokya', 'Türkiye', 2),
  ('bodrum', 'Bodrum', 'Türkiye', 3),
  ('baku', 'Bakü', 'Azerbaycan', 4);
