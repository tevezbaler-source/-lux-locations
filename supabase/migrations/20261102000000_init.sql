-- Lux Locations: içerik, müşteri profilleri ve talepler.
-- Site, uygulama ve ekip paneli bu tabloları paylaşır.

create table public.services (
  id text primary key,
  title text not null,
  kicker text,
  body text,
  tags text[] not null default '{}',
  image_url text,
  price_from numeric,
  currency text default 'EUR',
  sort int not null default 0,
  is_active boolean not null default true
);

create table public.destinations (
  slug text primary key,
  name text not null,
  country text not null default 'Türkiye',
  body text,
  image_url text,
  sort int not null default 0,
  is_active boolean not null default true
);

create table public.profiles (
  id uuid primary key references auth.users on delete cascade,
  full_name text,
  phone text,
  is_staff boolean not null default false,
  expo_push_token text,
  created_at timestamptz not null default now()
);

create type public.request_status as enum ('received', 'quoted', 'confirmed', 'cancelled');

create table public.requests (
  id uuid primary key default gen_random_uuid(),
  user_id uuid references auth.users on delete set null,
  service_id text not null references public.services,
  full_name text not null,
  email text,
  phone text,
  start_date date,
  details jsonb not null default '{}',
  notes text,
  source text not null check (source in ('web', 'mobile')),
  status public.request_status not null default 'received',
  quote_url text,
  staff_note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint contact_required check (coalesce(email, phone) is not null)
);

create index requests_status_idx on public.requests (status, created_at desc);
create index requests_user_idx on public.requests (user_id);

create function public.is_staff() returns boolean
language sql stable security definer set search_path = public as $$
  select coalesce((select is_staff from public.profiles where id = auth.uid()), false);
$$;

create function public.touch_updated_at() returns trigger
language plpgsql as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create trigger requests_touch before update on public.requests
  for each row execute function public.touch_updated_at();

-- Yeni kullanıcıya otomatik profil.
create function public.handle_new_user() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  insert into public.profiles (id, full_name) values (new.id, new.raw_user_meta_data ->> 'full_name');
  return new;
end;
$$;

create trigger on_auth_user_created after insert on auth.users
  for each row execute function public.handle_new_user();

-- Erişim kuralları
alter table public.services enable row level security;
alter table public.destinations enable row level security;
alter table public.profiles enable row level security;
alter table public.requests enable row level security;

create policy "herkes aktif hizmetleri okur" on public.services
  for select using (is_active or public.is_staff());
create policy "ekip hizmetleri yönetir" on public.services
  for all using (public.is_staff()) with check (public.is_staff());

create policy "herkes aktif şehirleri okur" on public.destinations
  for select using (is_active or public.is_staff());
create policy "ekip şehirleri yönetir" on public.destinations
  for all using (public.is_staff()) with check (public.is_staff());

create policy "kişi kendi profilini görür" on public.profiles
  for select using (id = auth.uid() or public.is_staff());
create policy "kişi kendi profilini günceller" on public.profiles
  for update using (id = auth.uid())
  with check (id = auth.uid() and is_staff = (select p.is_staff from public.profiles p where p.id = auth.uid()));

-- Giriş yapmadan da talep gönderilebilir (sitedeki form); hesap varsa talep ona bağlanır.
create policy "herkes talep oluşturur" on public.requests
  for insert with check (
    status = 'received' and (user_id is null or user_id = auth.uid())
  );
create policy "müşteri kendi taleplerini görür" on public.requests
  for select using (user_id = auth.uid() or public.is_staff());
create policy "ekip talepleri günceller" on public.requests
  for update using (public.is_staff()) with check (public.is_staff());
