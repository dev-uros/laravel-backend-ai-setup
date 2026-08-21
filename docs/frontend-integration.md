# Integracija frontenda sa ovim API-jem

Uputstvo za SPA koji se vrti na `http://localhost:5173` i priča sa Laravel
backendom na `http://localhost:8000`.

Pisano da se čita redom. Sve u njemu je provereno protiv žive aplikacije.

---

## 0. Šta treba da znaš pre nego što napišeš prvu liniju

**Autentikacija su Sanctum personal access tokeni, NE kolačići.** Ne postoji
`/sanctum/csrf-cookie`, nema CSRF-a, nema deljene sesije. Dobiješ string, čuvaš
ga, šalješ ga kao `Authorization: Bearer {token}`. Ako u nekom tutorijalu vidiš
`withCredentials: true` i CSRF cookie — to je Sanctumov drugi režim i ovde ne
radi.

**Svaki uspešan odgovor ima isti omotač:**

```json
{ "message": "Korisnik uspešno kreiran.", "data": { "id": 1, "name": "Uroš" } }
```

`message` je uvek tu i **napisan je da ga korisnik pročita** (na srpskom).
Prikaži ga direktno u toast-u. `data` je uvek tu, `null` kad akcija nema šta da
vrati.

**Greške NEMAJU taj omotač** — zadržavaju Laravelov oblik:

```json
{ "message": "The name field is required. (and 1 more error)",
  "errors": { "name": ["The name field is required."],
              "email": ["The email field must be a valid email address."] } }
```

Za 401/403/404 stiže samo `{ "message": "..." }`. To znači da klijent mora da
gleda HTTP status, ne oblik tela.

**CORS je već otvoren** za tvoj origin (provereno: preflight sa
`Origin: http://localhost:5173` vraća `Access-Control-Allow-Origin: *`). Ne treba
ti proxy u Vite konfiguraciji, ali smeš da ga koristiš ako ti je lakše.

---

## 1. Podigni backend i pogledaj dokumentaciju

U backend folderu:

```bash
make up
```

| | |
| --- | --- |
| API | `http://localhost:8000/api` |
| **Interaktivna dokumentacija** | `http://localhost:8000/docs/api` |
| OpenAPI spec | `http://localhost:8000/docs/api.json` |

Dokumentacija se generiše iz koda i uvek je tačna. Kad nešto nije jasno iz ovog
fajla, tamo je izvor istine.

**Nalog za razvoj** (seed): `uros.minic@fifthguild.com` / `password`, uloga
`admin`.

### Demo podaci

Prazna baza znači prazni ekrani. U backend folderu:

```bash
make demo
```

Dobiješ osam naloga sa istorijom od nekih šest nedelja, u svim stanjima koja
ekrani moraju da pokriju. **Lozinka je `password` za sve.**

| nalog | uloga | stanje |
| --- | --- | --- |
| `uros.minic@fifthguild.com` | admin | osnovni nalog |
| `milica.jovanovic@fifthguild.com` | admin | aktivan, redovno se prijavljuje |
| `teodora.markovic@fifthguild.com` | admin | unapređena iz `user`, sa tragom |
| `marko.petrovic-ilic@fifthguild.com` | user | menjao prezime i email |
| `ana.nikolic@fifthguild.com` | user | resetovala lozinku |
| `stefan.djordjevic@fifthguild.com` | user | aktivan |
| `jovana.stankovic@fifthguild.com` | user | **nikad aktiviran**, dva podsetnika |
| `nikola.ilic@fifthguild.com` | user | **isključen**, sa pokušajem prijave posle toga |

Uz to ide 28 zapisa u istoriji izmena i preko 100 prijava — uspešnih, promašenih
lozinki, jedna na isključen nalog, i pet na adrese koje ne postoje (te nemaju
korisnika i vidljive su samo u bazi, ne kroz `/users/{id}/logins`).

Ponovno pokretanje ništa ne duplira. Za čist početak: `make fresh` pa `make demo`.

---

## 2. Konfiguracija

`.env` u frontend projektu:

```
VITE_API_URL=http://localhost:8000/api
```

Nikad ne hardkoduj `localhost:8000` u kodu — backend na produkciji nije na istom
hostu kao frontend.

---

## 3. API klijent

Jedno mesto kroz koje ide svaki poziv. Prilagodi svom framework-u, logika je ista.

```ts
// src/lib/api.ts
const BASE = import.meta.env.VITE_API_URL

export type ApiError = {
  status: number
  message: string                      // spreman za prikaz korisniku
  errors?: Record<string, string[]>    // samo kod 422
  requestId?: string                   // za prijavu problema
}

let token: string | null = localStorage.getItem('token')

export function setToken(value: string | null) {
  token = value
  value ? localStorage.setItem('token', value) : localStorage.removeItem('token')
}

export function getToken() {
  return token
}

export async function api<T>(
  path: string,
  options: { method?: string; body?: unknown } = {},
): Promise<T> {
  const response = await fetch(`${BASE}${path}`, {
    method: options.method ?? 'GET',
    headers: {
      // Bez ovoga Laravel ume da odgovori HTML-om umesto JSON-om.
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  })

  // Svaki odgovor nosi ovaj header. Sačuvaj ga uz grešku: sa njim se u logovima
  // nalazi tačno šta se desilo, uključujući i job koji je zahtev pokrenuo.
  const requestId = response.headers.get('X-Request-Id') ?? undefined

  if (response.status === 204) return null as T

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    // 401 znači da token ne važi (istekao, povučen logout-om ili resetom
    // lozinke). Jedini ispravan potez je odjava.
    if (response.status === 401) setToken(null)

    throw {
      status: response.status,
      message: payload.message ?? 'Došlo je do greške.',
      errors: payload.errors,
      requestId,
    } satisfies ApiError
  }

  return payload as T
}
```

Uz to jedan tip za omotač:

```ts
export type ApiResponse<T> = { message: string; data: T }

export type Paginated<T> = {
  message: string
  data: T[]
  links: { first: string; last: string; prev: string | null; next: string | null }
  meta: {
    current_page: number
    from: number | null
    last_page: number
    links: { url: string | null; label: string; page: number | null; active: boolean }[]
    path: string
    per_page: number
    to: number | null
    total: number
  }
}
```

---

## 4. Korisnik i uloge

```ts
export type UserRole = 'admin' | 'user'

export type User = {
  id: number
  name: string
  surname: string
  email: string
  role: UserRole
  deactivated_at: string | null   // null = nalog radi
  email_verified_at: string | null // null = nalog nije aktiviran
  created_at: string
  updated_at: string
}
```

Dve uloge i to je sve: **`admin` sme sve, `user` sme samo da čita.** Sakrij
dugmad koja `user` ne sme da vidi, ali **ne oslanjaj se na to** — API svejedno
odbija sa 403.

---

## 5. Prijava, odjava, obnavljanje sesije

```ts
// POST /auth/login
const { data } = await api<ApiResponse<{ user: User; token: string }>>('/auth/login', {
  method: 'POST',
  body: { email, password },
})

setToken(data.token)   // čuvaj korisnika u store-u
```

**Pri svakom pokretanju aplikacije**, ako token postoji u `localStorage`, pozovi:

```ts
// GET /auth/me  -> sveži podaci naloga
const { data: user } = await api<ApiResponse<User>>('/auth/me')
```

Ako to padne sa 401, token više ne važi — klijent ga briše sam (vidi gore) i
šalješ korisnika na login. Time se sesija obnavlja bez ponovnog unosa lozinke.

```ts
// POST /auth/logout  -> poništava SAMO token kojim je poslat
await api('/auth/logout', { method: 'POST' })
setToken(null)
```

Odjava na jednom uređaju ne izbacuje korisnika sa drugih — to je namerno.

### Gde čuvati token

Gore je `localStorage` jer je najjednostavnije i preživi refresh. Iskreno o
ceni: **XSS na tvom frontendu čita `localStorage`.** Alternativa je token samo u
memoriji (nestaje na refresh, pa se ponovo loguješ). Ako biraš `localStorage`,
onda ozbiljno shvati sanitizaciju svega što renderuješ.

---

## 6. Dve stranice koje moraš da napraviš za linkove iz mejlova

Backend šalje mejlove sa linkovima **na tvoj frontend**, ne na sebe. Putanje su
fiksne i moraju da postoje:

| link u mejlu | tvoja ruta |
| --- | --- |
| aktivacija naloga | `http://localhost:5173/auth/activate-account?email=…&token=…` |
| zaboravljena lozinka | `http://localhost:5173/auth/reset-password?email=…&token=…` |

Obe stranice rade isto: pročitaj `email` i `token` iz query stringa, prikaži
formu za lozinku, i pošalji sve četiri vrednosti nazad.

```ts
// POST /auth/activate-account   (nalog koji još nije aktiviran)
// POST /auth/reset-password     (nalog koji jeste)
const { data } = await api<ApiResponse<{ user: User; token: string }>>(
  '/auth/activate-account',
  { method: 'POST', body: { email, token, password, password_confirmation } },
)

setToken(data.token)   // korisnik je odmah ulogovan, ne šalji ga na login formu
```

**Politika lozinke** (validira backend, ali proveri i u formi da korisnik ne
šalje uzalud): najmanje 8 karaktera, bar jedno veliko i jedno malo slovo, bar
jedan broj, bar jedan simbol, i lozinka ne sme da bude u poznatim procurelim
bazama.

Ako je link istekao, već iskorišćen ili nije tog naloga — dobijaš **422** sa
porukom koja ne kaže koje od to troje. Prikaži poruku i ponudi „pošalji novi
link".

### Zaboravljena lozinka

```ts
// POST /auth/forgot-password
await api('/auth/forgot-password', { method: 'POST', body: { email } })
```

**Odgovor je uvek isti 200**, bez obzira da li nalog postoji. To je namerno — ne
pokušavaj da iz odgovora zaključiš da li email postoji i **ne prikazuj ništa što
bi to odalo**. Prikaži poruku koju API vrati, doslovno.

---

## 7. Korisnici

| akcija | poziv | ko sme |
| --- | --- | --- |
| lista | `GET /users` | svako ulogovan |
| jedan | `GET /users/{id}` | svako ulogovan |
| kreiranje | `POST /users` | admin |
| izmena | `PUT /users/{id}` | admin |
| promena uloge | `PUT /users/{id}/role` | admin |
| isključivanje | `POST /users/{id}/deactivate` | admin |
| uključivanje | `POST /users/{id}/reactivate` | admin |
| ponovo pošalji aktivacioni mejl | `POST /users/{id}/resend-activation` | admin |
| istorija izmena | `GET /users/{id}/logs` | admin |
| istorija prijava | `GET /users/{id}/logins` | admin |

Detalji koji se najčešće promaše:

**Lista nije paginirana** — vraća sve korisnike, sortirane po imenu pa prezimenu.

**Kreiranje** prima `name`, `surname`, `email` i opciono `role`. Bez `role`
korisnik je `user`. Lozinku **ne šalješ** — backend generiše nasumičnu i šalje
korisniku aktivacioni mejl.

**Izmena je `PUT` i sva polja su obavezna.** `PATCH` namerno ne postoji; pošalji
i polja koja nisi menjao. Menjaju se samo `name`, `surname`, `email`.

**Uloga se menja zasebnim pozivom** (`PUT /users/{id}/role` sa `{ "role":
"admin" }`), ne kroz izmenu profila. Admin **ne može sebi** da promeni ulogu —
odgovor je 403 sa objašnjenjem. Isto važi i za isključivanje sopstvenog naloga.

**Isključen nalog nije obrisan.** Ostaje u listi sa popunjenim `deactivated_at`;
prikaži ga kao takvog i ponudi dugme za vraćanje. Njegovi tokeni su poništeni
istog trena.

**`{id}` mora biti broj**, inače je 404 pre nego što zahtev uđe u aplikaciju.

### Paginirani odgovori

`/users/{id}/logs` i `/users/{id}/logins` vraćaju `data`, `links` i `meta` pored
`message`, po 25 po strani. Stranica se traži sa `?page=2`.

U istoriji izmena `actor` je uvek prisutan; `null` znači da je izmenu napravio
sistem (seeder, konzolna komanda, scheduler), a ne da je autor nepoznat.
`old_values` i `new_values` su objekti sa samo onim poljima koja su se
promenila, ili `null` kad događaj nema šta da uporedi.

---

## 8. Statusi i šta UI treba da uradi

| status | značenje | postupak |
| --- | --- | --- |
| **200 / 201** | prošlo | prikaži `message` |
| **401** | nema tokena, token ne važi ili su podaci za prijavu pogrešni | obriši token, na login |
| **403** | nemaš pravo, ILI je nalog isključen, ILI je akcija nad samim sobom zabranjena | prikaži `message`, ne pokušavaj ponovo |
| **404** | ne postoji | prikaži `message` |
| **422** | validacija, ili neispravan/istekao link iz mejla | ispiši `errors` ispod polja |
| **429** | previše pokušaja (login 10/min, forgot-password 5/min po IP-u) | prikaži „pokušajte za koji trenutak"; odgovor nosi `Retry-After: 60` i `X-RateLimit-Remaining` |
| **500** | greška na serveru | generička poruka + `X-Request-Id` |

Pogrešna lozinka je **401, ne 422** — to je namerno: 422 znači „polje je
neispravno", 401 znači „podaci nisu tačni". Poruka nikad ne kaže da li je
pogrešio email ili lozinku; ne pokušavaj da to razlučiš i ne pogađaj u UI-ju.

---

## 9. Šta NE raditi

- ❌ Ne koristi Sanctum cookie/CSRF režim (`withCredentials`, `/sanctum/csrf-cookie`).
- ❌ Ne sastavljaj sam linkove za aktivaciju i reset — dolaze u mejlu sa tokenom.
- ❌ Ne oslanjaj se na skrivanje dugmadi kao na zaštitu; API je taj koji brani.
- ❌ Ne prikazuj sopstvene poruke o greškama umesto `message` iz odgovora — one
  su pisane za krajnjeg korisnika i na srpskom.
- ❌ Ne pretpostavljaj da `forgot-password` odgovor išta govori o postojanju naloga.
- ❌ Ne šalji lozinku pri kreiranju korisnika; taj endpoint je ne prima.

---

## 10. Tipovi iz OpenAPI spec-a (preporučeno)

Backend izlaže kompletan spec, pa tipove ne moraš pisati ručno:

```bash
npx openapi-typescript http://localhost:8000/docs/api.json -o src/types/api.d.ts
```

Kad se API promeni, pokreni ponovo. Alternativa sa gotovim klijentom je `orval`
ili `openapi-fetch`.

---

## 11. Kad nešto ne radi

**Prvo proveri golim pozivom** — ako ovo radi, problem je u frontendu:

```bash
curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"uros.minic@fifthguild.com","password":"password"}'
```

**Zatim iskoristi `X-Request-Id`.** Svaki odgovor ga nosi. Sa tom vrednošću
backend tim (ili ti, u backend folderu) vidi tačno šta se desilo:

```bash
make observe    # Grafana na http://localhost:3000
```

pa zalepi id u polje „Request ID" na dashboard-u „Laravel — zahtevi i jobovi".
Dobijaš i sam zahtev i svaki job koji je iz njega proistekao — na primer slanje
aktivacionog mejla, koje se dešava sekundama kasnije u drugom kontejneru.

**Mejlovi u razvoju** idu kroz SMTP podešen u backend `.env`-u (trenutno
Mailtrap). Ako ti aktivacioni link ne stiže, ne traži grešku u frontendu —
proveri `MAIL_*` vrednosti i da li je job prošao:

```bash
make observe-logs s=alloy     # ili jednostavno:
docker compose logs horizon | grep '"message":"job"'
```

Job koji padne vidi se kao `"outcome":"failed"` sa porukom izuzetka. Aktivacioni
link se uvek može poslati ponovo sa `POST /users/{id}/resend-activation`.

---

## 12. Kad se API promeni

Ovaj fajl i `http://localhost:8000/docs/api` su ugovor između dva projekta. Ako
backend promeni oblik odgovora, dodatak polja ili status kod, **prvo se menja
ovde**, pa onda frontend. Dokumentacija na `/docs/api` se generiše iz koda i ne
može da zastari; ovaj fajl može, pa ga tretiraj kao deo izmene, ne kao naknadnu
misao.
