# Frontend na serveru — šta treba da namestiš kod sebe

Uputstvo za onoga ko održava SPA. Backend je već na serveru; ovo je sve što tvoj
repo treba da dobije da bi se deployovao pored njega i dobio HTTPS.

Ne moraš znati ništa o backend stacku. Treba ti tri fajla i jedna komanda.

---

## Šta dobijaš

```
                    internet :443
                          │
                ┌─────────▼─────────┐
                │      Traefik      │   već postoji, ne diraš ga
                └──┬─────────────┬──┘
                   │             │
        app.tvoj-domen.rs   api.tvoj-domen.rs
        ┌──────────────┐    ┌──────────────┐
        │ TVOJ nginx   │    │  backend     │
        │ (dist/)      │    │              │
        └──────────────┘    └──────────────┘
             tvoj repo          tuđi repo
```

- **HTTPS dobijaš besplatno.** Sertifikat se vadi i obnavlja sam.
- **Ne diraš ništa zajedničko.** Nema centralnog config fajla koji se edituje.
- **Ne postavljaš Traefik.** On već radi. Ti se samo prijaviš.

Tvoj build ide u mali nginx kontejner koji servira statiku. Traefik stoji ispred
i rutira po domenu.

---

## Preduslovi (traži od onoga ko drži server)

Pre nego što išta radiš, neka ti potvrde:

- [ ] **DNS**: `app.tvoj-domen.rs` A zapis pokazuje na IP servera
- [ ] **`edge` mreža postoji** — pravi je backend deploy, `docker network ls | grep edge`
- [ ] **SSH pristup** serveru, korisnik u `docker` grupi
- [ ] **Backend zna tvoj tačan origin** — moraju upisati `FRONTEND_URL=https://app.tvoj-domen.rs`
      i restartovati API, inače te CORS odbija

Poslednja stavka je jedina koja traži koordinaciju. Javi im origin pre prvog
deploya.

---

## Tri fajla u tvoj repo

### 1. `Dockerfile`

```dockerfile
# --- build ---------------------------------------------------------------
FROM node:22-alpine AS build

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .

# Vite peče env vrednosti u bundle u BUILD vremenu, ne u runtime-u. Zato je ovo
# build argument, a ne `environment:` u compose fajlu.
ARG VITE_API_URL
ENV VITE_API_URL=$VITE_API_URL

RUN npm run build

# --- serve ---------------------------------------------------------------
FROM nginx:1.29-alpine

COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
```

Ako ne koristiš Vite, menjaju se tačno dve stvari: `npm run build` i putanja
`/app/dist`. Za Next.js sa SSR-om ovo ne važi — to nije statika i traži Node
proces u runtime-u; javi se pa ćemo drugačije.

Ako koristiš pnpm ili yarn, zameni `npm ci` odgovarajućim (`pnpm i --frozen-lockfile`,
`yarn install --immutable`) i kopiraj njihov lock fajl umesto `package*.json`.

### 2. `nginx.conf`

```nginx
server {
    listen 80;
    listen [::]:80;

    root /usr/share/nginx/html;
    index index.html;

    # Bez ovoga refresh na /users/5 vraća 404: nema takvog fajla na disku.
    # Sve što nije fajl ide na index.html, a router u browseru odlučuje dalje.
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Vite u ime fajla ubacuje hash, pa se sadržaj pod istim imenom nikad ne
    # menja -- može da se kešira zauvek.
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # index.html se NE kešira, inače korisnik posle deploya dobija staru
    # stranicu koja traži assete kojih više nema.
    location = /index.html {
        add_header Cache-Control "no-cache";
    }
}
```

Kompresiju ne diraj — radi je Traefik (`compress@file` u labelama ispod).
Duplirati je ovde znači kompresovati dva puta.

### 3. `compose.yaml`

```yaml
name: web

services:
  web:
    build:
      context: .
      args:
        VITE_API_URL: https://api.tvoj-domen.rs
    restart: unless-stopped

    # Nema `ports:`. Jedini put do ovog kontejnera je kroz Traefik.
    networks: [edge]

    labels:
      traefik.enable: "true"

      # OBAVEZNO kad je kontejner na više mreža. Bez ovoga Traefik ume da uzme
      # pogrešnu adresu i dobiješ 502 bez ijedne poruke igde.
      traefik.docker.network: edge

      traefik.http.routers.web.rule: Host(`app.tvoj-domen.rs`)
      traefik.http.routers.web.entrypoints: websecure
      traefik.http.routers.web.tls.certresolver: le
      traefik.http.routers.web.middlewares: security-headers@file,compress@file
      traefik.http.services.web.loadbalancer.server.port: "80"

    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "3"

networks:
  # Već postoji, pravi je backend deploy. `external` znači "nađi je, ne pravi je".
  edge:
    external: true
    name: edge
```

### I `.dockerignore`

```
node_modules
dist
.git
.env
.env.*
```

Bez ovoga se `node_modules` sa tvoje mašine šalje u build kontekst — spor build
i binarni paketi kompajlirani za pogrešnu platformu.

---

## Provera lokalno, pre nego što išta pošalješ

Ne treba ti Traefik da bi proverio da image radi:

```bash
docker build --build-arg VITE_API_URL=https://api.tvoj-domen.rs -t web:test .
docker run --rm -p 8080:80 web:test
```

Pa u browseru na `http://localhost:8080`:

- [ ] stranica se učitava
- [ ] otvoriš neku dublju rutu direktno (`/users/5`) i **refreshuješ** — mora da radi, ne 404
- [ ] u Network tabu API pozivi idu na `https://api.tvoj-domen.rs`, ne na `localhost`

Treća stavka je najčešća greška: ako vidiš `localhost`, `VITE_API_URL` nije
stigao do builda.

---

## Deploy

### Prvi put

```bash
ssh tvoj-server
sudo mkdir -p /opt/web && sudo chown $USER:$USER /opt/web
git clone <tvoj-repo> /opt/web
cd /opt/web
docker compose up -d --build
```

Traefik te pokupi za par sekundi. Sertifikat stiže za 30-60 sekundi.

```bash
curl -sI https://app.tvoj-domen.rs | head -1     # 200
```

### Svaki sledeći put

```bash
cd /opt/web
git pull
docker compose up -d --build
docker image prune -f
```

To je ceo deploy. Nema migracija, nema stanja, nema šta da se pokvari osim
builda.

Traje koliko i `npm run build` plus par sekundi. Kontejner se recreate-uje, pa
ima 2-3 sekunde u kojima Traefik vraća 404 — za statički sajt je to obično u
redu. Ako smeta, javi, ima rešenje.

---

## Pravila kojih se drži

**Ne dodaji svoj Traefik.** Jedan je za ceo server i već radi. Ako pokreneš još
jedan, tuće se za portove 80 i 443 i oboriće i backend.

**Ne stavljaj `ports:`.** Objavljen port zaobilazi Traefik, znači zaobilazi i
HTTPS i security headere. Jedini ulaz je kroz proxy.

**Ne pravi `edge` mrežu.** `external: true` znači da je tražiš, ne praviš. Ako je
nema, backend deploy nije odrađen — pitaj.

**Ništa tajno u `VITE_` promenljive.** Sve što počinje sa `VITE_` završi u
bundle-u koji svako može da otvori u browseru i pročita. API ključevi, tokeni,
lozinke — nikad. Adrese i feature flagovi — može.

**Ne diraj `/opt/api` ni `compose.edge.yaml`.** Tvoj projekat je samodovoljan.

---

## Šta aplikacija mora da radi

Infrastruktura je gore, ovo je ugovor sa API-jem. Detaljno je u
[`frontend-integration.md`](frontend-integration.md) — ovde samo ono što utiče
na routing.

**Dve rute moraju da postoje**, jer emailovi koje backend šalje vode tačno tu:

| Ruta u tvom routeru | Šalje na |
| --- | --- |
| `/auth/activate-account?email=…&token=…` | `POST /api/auth/activate-account` |
| `/auth/reset-password?email=…&token=…` | `POST /api/auth/reset-password` |

Dve različite namerno: oba linka nose reset token, ali samo prvi ujedno
verifikuje email adresu.

**Token ide kao `Authorization: Bearer {token}`.** Nema kolačića, nema CSRF-a,
nema `withCredentials`.

**`X-Request-Id` iz odgovora sačuvaj uz frontend greške.** Backend loguje pod
istim id-jem, pa se sa njim nađe tačan zahtev u logovima.

---

## Kad nešto ne radi

| Vidiš | Uzrok |
| --- | --- |
| 404 na tvom domenu | tipfeler u labeli — labele ne prijavljuju grešku, samo ne naprave rutu |
| 502 | fali `traefik.docker.network: edge`, ili kontejner nije na `edge` mreži, ili port u labeli nije 80 |
| refresh na dubljoj ruti → 404 | `try_files` fali u `nginx.conf` |
| CORS greška u konzoli | backendov `FRONTEND_URL` se ne poklapa sa tvojim originom — tačno, bez kose crte na kraju |
| API pozivi idu na `localhost` | `VITE_API_URL` nije prosleđen kao build arg |
| sertifikat nevažeći | DNS ne pokazuje na server, ili nije još propagirao |
| stara stranica posle deploya | `index.html` se kešira — vidi `location = /index.html` gore |

Prva dva se dijagnostikuju na serveru:

```bash
cd /opt/api && make edge-logs
```

Logovi tvog kontejnera se skupljaju automatski i vide se u Grafani
(`https://logs.tvoj-domen.rs`) pod `{service="web"}` — ne moraš ništa da
podešavaš za to.

---

## Sažetak

1. Traži DNS zapis i potvrdu da je `edge` mreža gore
2. Javi backendu svoj tačan origin
3. Dodaj `Dockerfile`, `nginx.conf`, `compose.yaml`, `.dockerignore`
4. Proveri lokalno da refresh na dubljoj ruti radi
5. `git clone` na server, `docker compose up -d --build`
6. Svaki sledeći put: `git pull && docker compose up -d --build`
