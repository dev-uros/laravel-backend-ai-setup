# Deploy na produkciju

Kako se ova aplikacija podiže na server, kako se na njega izlivaju izmene, i šta
frontend mora da zna.

Pisano da se čita redom. Sve komande su probane protiv živog stacka.

Pretpostavka je jedan VPS sa Dockerom, Traefik kao edge proxy i build **na
serveru** — nema registry-ja, nema image-a koji se negde gura.

---

## 0. Šta ćeš imati kad završiš

```
                        internet :80 :443
                               │
                    ┌──────────▼──────────┐
                    │      Traefik        │   TLS, Let's Encrypt, routing
                    │  compose.edge.yaml  │   projekat: `edge`
                    └──┬───────────┬──────┘
        ── mreža `edge` (external, deljena) ──
                       │           │
        ┌──────────────▼──┐   ┌────▼─────────┐
        │ api.tvoj.rs     │   │ logs.tvoj.rs │
        │  nginx (web)    │   │   Grafana    │
        │    ↓ :9000      │   └──────┬───────┘
        │  php-fpm        │          │
        │  horizon        │      Loki + Alloy
        │  scheduler      │   compose.observability.prod.yaml
        │  postgres       │
        │  redis          │
        │ compose.prod.   │
        │     yaml        │
        └─────────────────┘
     ── mreža `internal` (bez ijednog objavljenog porta) ──
```

Tri odvojena compose projekta. Nijedan ne može da obori drugi: `make prod-down`
ne dira Traefik, `make edge-down` ne dira bazu.

Frontend, kad ga budeš dodavao, kači se na istu `edge` mrežu sa svojim compose
fajlom. Ništa u `compose.edge.yaml` se ne menja. Detaljno u sekciji 5.

---

## 1. Preduslovi

### Server

Minimum koji ovaj stack realno traži: **2 vCPU, 4 GB RAM, 40 GB diska**.

Sa uključenom observability (Loki + Grafana + Alloy) računaj da ta tri servisa
pojedu oko 1 GB RAM-a. Ako si na 2 GB mašini, stavi
`ENABLE_OBSERVABILITY=false` i čitaj logove kroz `make prod-logs`.

### Docker

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
newgrp docker            # ili se izloguj i uloguj
docker compose version   # mora da odgovori, v2
```

Nemoj raditi deploy kao root. `deploy.sh` očekuje da tvoj korisnik može da priča
sa Docker socketom, i to je sve.

### DNS

Tri A zapisa, **pre prvog deploya**, sva na IP servera:

| Zapis | Čemu služi |
| --- | --- |
| `api.tvoj-domen.rs` | ovaj API |
| `logs.tvoj-domen.rs` | Grafana |
| `app.tvoj-domen.rs` | frontend (može i kasnije) |

> **Ovo je jedina stvar koju je skupo pogrešiti.** Let's Encrypt izdaje **5
> sertifikata nedeljno po domenu**. Ako pustiš deploy pre nego što DNS
> propagira, ACME challenge padne, a par takvih pokušaja te zaključa do sledeće
> nedelje. Proveri prvo:
>
> ```bash
> dig +short api.tvoj-domen.rs    # mora da vrati IP servera
> ```

### Firewall

Otvoreni samo 80, 443 i SSH:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Postgres i Redis ne objavljuju nijedan port, pa nema šta da se zatvara — ali
firewall je druga brava i košta te jedan minut.

---

## 2. Prvo postavljanje

### 2.1 Klon

```bash
sudo mkdir -p /opt/api
sudo chown $USER:$USER /opt/api
git clone <tvoj-repo> /opt/api
cd /opt/api
```

Putanja nije bitna, samo budi dosledan — cron za backup je referencira.

### 2.2 Environment

```bash
cp .env.production.example .env.production
make prod-key          # ispiše APP_KEY, kopiraj ga
nano .env.production
```

Popuni ovo, ostalo ima razuman default:

| Ključ | Šta ide |
| --- | --- |
| `API_DOMAIN` | `api.tvoj-domen.rs` |
| `GRAFANA_DOMAIN` | `logs.tvoj-domen.rs` |
| `ACME_EMAIL` | tvoj email, tu stižu upozorenja o isteku sertifikata |
| `APP_KEY` | iz `make prod-key` |
| `APP_URL` | `https://api.tvoj-domen.rs` |
| `FRONTEND_URL` | `https://app.tvoj-domen.rs` |
| `DB_PASSWORD` | `openssl rand -base64 32` |
| `REDIS_PASSWORD` | `openssl rand -base64 32` |
| `GRAFANA_ADMIN_PASSWORD` | nešto dugačko |
| `MAIL_*` | podaci tvog SMTP provajdera |

Tri stvari koje ljudi ovde zezaju:

**Nema `${VAR}` referenci u tom fajlu.** Compose te linije prosleđuje
kontejnerima doslovno, pa bi `APP_URL=https://${API_DOMAIN}` stiglo do PHP-a kao
taj tekst. Ispiši svaku vrednost celu.

**`FRONTEND_URL` je origin, ne URL stranice.** Šema, host, port — bez putanje i
**bez kose crte na kraju**. `https://app.tvoj-domen.rs/` neće proći CORS.

**`APP_KEY` čuvaj i van servera.** Ako se izgubi, svaka enkriptovana kolona i
svaki potpisani URL postaju smeće. Password manager, bilo šta.

### 2.3 Deploy

```bash
./deploy.sh
```

Prvi put traje 3-5 minuta jer se povlače base image-i i gradi `vendor`. Prolazi
kroz: preflight → Traefik → build → baza → backup → maintenance → migracije →
podizanje → health check → Grafana.

Ako padne, reći će ti gde. Preflight namerno odbija start na nepotpunom
environmentu — pre nego što bilo šta dirne.

### 2.4 Prvi administrator

**Ovo je neophodan korak, ne opcioni.** Posle deploya u bazi nema nijednog
naloga, a `POST /api/users` traži da si ulogovan kao admin. Klasična petlja.

`DatabaseSeeder` ovde ne pomaže: ide preko `User::factory()`, faktori traže
Faker, a Faker je `require-dev` i ne postoji u produkcijskom image-u.

```bash
make prod-artisan c="admin:create ti@tvoj-domen.rs --name=Uroš --surname=Minić"
```

Nalog se pravi sa **nasumičnom lozinkom koju niko ne zna** i dobija email sa
aktivacionim linkom — isti tok kojim aplikacija pravi svakog korisnika, pa ostaje
i trag u `user_logs` (akter je `null`, jer niko nije bio ulogovan).

Ako SMTP još ne radi — a to je uobičajeno stanje deset minuta posle prvog
deploya:

```bash
make prod-artisan c="admin:create ti@tvoj-domen.rs --name=Uroš --surname=Minić --show-link"
```

Ispisaće aktivacioni link direktno u terminal. **Taj link poništava onaj iz
emaila** — token je jedan, novi briše stari.

Otvori link, postavi lozinku, gotovo.

### 2.5 Provera

```bash
curl -sI https://api.tvoj-domen.rs/up | head -1        # 200
curl -s  https://api.tvoj-domen.rs/api/users \
     -H "Accept: application/json" | head -c 100        # 401, znači brava radi
```

Pa u browseru:

- `https://logs.tvoj-domen.rs` → Grafana login (`admin` + tvoja lozinka)
- `https://api.tvoj-domen.rs/horizon` → 403 dok se ne uloguješ kao admin
- `https://api.tvoj-domen.rs/docs/api` → Scramble dokumentacija

Sertifikati: `make edge-logs` i traži `certificate obtained`. Prvi put ume da
potraje 30-60 sekundi.

### 2.6 Backup u cron

`deploy.sh` pravi dump pre svake migracije. To pokriva loš deploy, ne pokriva
utorak.

```bash
crontab -e
```

```
17 3 * * *  cd /opt/api && ./docker/scripts/backup.sh >> /var/log/db-backup.log 2>&1
```

> Ti dumpovi stoje na **istom disku** kao i baza. To pokriva grešku, ne pokriva
> mrtav server. Kopiraj ih negde van mašine — `rclone`, `restic`, `scp` u cronu,
> bilo šta. Ovaj setup to namerno ne rešava umesto tebe.

---

## 3. Povlačenje promena

Svaki sledeći deploy je isto:

```bash
cd /opt/api
git pull
./deploy.sh
```

Šta se dešava, redom:

1. **Preflight** — environment se proverava ponovo, svaki put
2. **Trenutni image-i se pretaguju u `:previous`** — to je tvoj rollback
3. **Build** — sloj sa `vendor` se ponovo koristi ako `composer.lock` nije menjan
4. **Dump baze** u `backups/`, sa verifikacijom arhive
5. **Maintenance mode** — svi kontejneri vraćaju 503 sa `Retry-After`
6. **Migracije** (`--force`)
7. **Kontejneri se recreate-uju** sa novim image-om
8. **Maintenance se gasi**
9. **Health gate** — poll `/up` dok ne odgovori; ne kaže "deployed" dok ne prođe

Traje oko minut kad se `vendor` sloj recikliran, 2-3 minuta kad se menjao
`composer.lock`.

**Nedostupnost:** maintenance mode traje koliko i migracije — obično par
sekundi. Korisnici dobiju čist 503, ne 502 i ne beli ekran. Nije zero-downtime i
ne pretvara se da jeste.

### Kad promena ne traži pun deploy

| Promenio si | Dovoljno je |
| --- | --- |
| samo `.env.production` | `make prod-reload` |
| samo `docker/traefik/dynamic.yml` | ništa, Traefik gleda taj fajl i primenjuje u letu |
| flagove u `compose.edge.yaml` | `make edge-up` (čitaju se samo pri startu) |
| **bilo koji PHP kod, `routes/`, `config/`** | pun `./deploy.sh` |

Dva reda koja zavaravaju:

**`make prod-reload`, ne `make prod-restart`.** `restart` ponovo pokreće proces u
postojećem kontejneru, a environment je zapečen u trenutku kad je kontejner
*napravljen* — izmenjen `.env.production` neće biti pokupljen. `prod-reload` radi
`up -d`, compose primeti da se `env_file` promenio i recreate-uje kontejnere.

**U produkciji je i `routes/console.php` u image-u.** U dev-u ga scheduler čita
na svaki tik jer je kod bind-mountovan; ovde nije. Svaka izmena PHP koda, ruta
ili configa traži pun deploy.

Iza svega toga stoji jedno: **nema bind mounta u produkciji.** Ne postoji fajl na
serveru koji možeš da izmeniš da bi promenio ponašanje aplikacije. To je namerno
— deploy menja image, ne fajlove.

---

## 4. Kad nešto pukne

### Deploy je pao

Skripta sama:

- gasi maintenance mode (trap na izlazu — sajt ne ostaje dole)
- ispiše zadnjih 40 linija loga
- ostavi `:previous` image-e netaknute

```bash
make rollback
```

Vraća prethodne image-e, podiže kontejnere i čeka da `/up` odgovori. Trenutni
par se pretaguje u `:failed` da možeš da ga gledaš.

> **`make rollback` NE vraća migracije.** Kod ide unazad, baza ne. Ako je deploy
> dodao kolonu — stari kod je ignoriše, sve radi. Ako je obrisao ili preimenovao
> kolonu — stari kod puca i treba ti dump.

### Vraćanje baze iz dumpa

```bash
ls -la backups/
gunzip -c backups/laravel-20260822T061735Z.sql.gz \
  | docker compose -f compose.prod.yaml --env-file .env.production \
      exec -T postgres psql -U laravel -d laravel
```

Dump je `--clean --if-exists`, pa se prelivа preko postojeće baze bez brisanja.

### Gde se gleda

```bash
make prod-ps                      # ko je gore, ko je unhealthy
make prod-logs                    # sve
make prod-logs s=php              # jedan servis
make prod-logs s=horizon
make edge-logs                    # ruta vraća 404? ovde
make prod-artisan c="horizon:status"
```

Ili u Grafani, `https://logs.tvoj-domen.rs`:

```logql
{service="php"} | json | context_status >= 500
{service="php"} | json | context_request_id = "01M0GG..."
```

`request_id` iz `X-Request-Id` zaglavlja povezuje HTTP liniju sa svakim job
logom koji je taj zahtev pokrenuo — i po nekoliko minuta kasnije, u `horizon`
kontejneru.

### Česti kvarovi

| Simptom | Uzrok |
| --- | --- |
| 404 na domenu | Traefik labela ima tipfeler — labele ne prijavljuju grešku, `make edge-logs` |
| Sertifikat nevažeći | DNS ne pokazuje na server, ili si potrošio ACME limit |
| 502 | php kontejner pada, `make prod-logs s=php` |
| Email ne stiže | `MAIL_MAILER=log` u `.env.production`, ili SMTP odbija |
| Avatari 404 | volume `storage-public` nije montiran u oba kontejnera |
| CORS greška u browseru | `FRONTEND_URL` se ne poklapa tačno sa originom SPA |

---

## 5. Frontend

### Mora li frontend u Docker?

**Ne mora.** API ne zna i ne mari gde frontend živi — komunikacija je HTTPS plus
bearer token, bez kolačića i bez deljene sesije. Jedino što povezuje to dvoje je
`FRONTEND_URL` u `.env.production`, koji određuje CORS i gde vode emailovani
linkovi.

Dve opcije koje obe rade:

| | Docker na istom serveru | Statički hosting (Vercel, Netlify, Cloudflare Pages) |
| --- | --- | --- |
| TLS | Traefik, kao i za API | provajder |
| CDN | nema | ima, globalno |
| Cena | 0 | 0 na free tier |
| Deploy | `git pull && docker compose up -d --build` | `git push` |
| Rollback | ručno | jedan klik |
| Broj stvari koje održavaš | jedna | dve |

**Preporuka: statički hosting**, osim ako imaš razlog da bude na istom serveru.
SPA je gomila statičkih fajlova; CDN to radi bolje nego tvoj VPS, a ti imaš
jedan server manje da čuvaš. Docker se isplati kad frontend ima svoj server-side
deo (SSR, Next.js API rute) ili kad iz nekog razloga sve mora na jednoj mašini.

Šta god izabereš, **backend se ne menja** — samo `FRONTEND_URL`.

### Opcija A: statički hosting

1. Deploy SPA na provajdera, zakači `app.tvoj-domen.rs`
2. Na serveru:
   ```bash
   nano .env.production      # FRONTEND_URL=https://app.tvoj-domen.rs
   make prod-reload
   ```
3. Gotovo.

### Opcija B: Docker na istom serveru

Frontendu treba svoj repo/folder sa dva fajla.

`Dockerfile`:

```dockerfile
FROM node:22-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
# Vite peče env vrednosti u bundle u BUILD vremenu, ne u runtime-u.
ARG VITE_API_URL
ENV VITE_API_URL=$VITE_API_URL
RUN npm run build

FROM nginx:1.29-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
```

`nginx.conf` — jedina stvar koja mora da bude tačno, inače refresh na
`/users/5` vraća 404:

```nginx
server {
    listen 80;
    listen [::]:80;
    root /usr/share/nginx/html;
    index index.html;

    # SPA fallback: svaka ruta koju ne pokriva fajl ide na index.html,
    # a router u browseru odlučuje šta da prikaže.
    location / {
        try_files $uri $uri/ /index.html;
    }

    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

`compose.yaml`:

```yaml
name: web

services:
  web:
    build:
      context: .
      args:
        VITE_API_URL: https://api.tvoj-domen.rs
    restart: unless-stopped
    networks: [edge]
    labels:
      traefik.enable: "true"
      traefik.docker.network: edge
      traefik.http.routers.web.rule: Host(`app.tvoj-domen.rs`)
      traefik.http.routers.web.entrypoints: websecure
      traefik.http.routers.web.tls.certresolver: le
      traefik.http.routers.web.middlewares: security-headers@file,compress@file
      traefik.http.services.web.loadbalancer.server.port: "80"
    logging:
      driver: json-file
      options: { max-size: "10m", max-file: "3" }

networks:
  edge:
    external: true
    name: edge
```

Podizanje:

```bash
cd /opt/web
docker compose up -d --build
```

Traefik ga pokupi sam. **U `compose.edge.yaml` se ne menja ništa** — to je cela
poenta label-driven pristupa.

Dve zamke:

- **`traefik.docker.network: edge`** je obavezno kad je kontejner na više mreža.
  Bez toga Traefik ume da uzme pogrešnu adresu i ruta tiho ne radi.
- **`VITE_API_URL` je build argument, ne runtime env.** Vite ga zapeče u bundle.
  Promena adrese API-ja znači rebuild, ne restart.

### Šta frontend mora da uradi, bez obzira gde stoji

**1. Origin mora tačno da se poklapa.** `FRONTEND_URL` na backendu i stvarni
origin SPA — šema, host, port. Bez kose crte na kraju. Ako treba drugi origin
(staging, preview build), postoji `CORS_ALLOWED_ORIGINS` kao lista razdvojena
zarezima.

**2. Dve rute moraju da postoje**, jer emailovani linkovi vode tačno tu:

| Ruta | Šalje na |
| --- | --- |
| `/auth/activate-account?email=…&token=…` | `POST /api/auth/activate-account` |
| `/auth/reset-password?email=…&token=…` | `POST /api/auth/reset-password` |

Dve različite rute namerno: oba nose reset token, ali samo prvi ujedno i
verifikuje email adresu, pa SPA mora da ih razlikuje.

**3. Token ide kao `Authorization: Bearer {token}`.** Nema
`/sanctum/csrf-cookie`, nema `withCredentials`, nema CSRF-a.

**4. `X-Request-Id` iz odgovora sačuvaj uz frontend greške.** Izložen je kroz
CORS baš zato — sa njim se u Grafani nađe tačan zahtev.

**5. Sve ostalo je u [`frontend-integration.md`](frontend-integration.md)** —
omotač odgovora, oblik grešaka, statusi i šta klijent radi na svaki. To je deo
API ugovora; ako se menja oblik odgovora, menja se i taj fajl u istom commitu.

---

## 6. Dodavanje trećeg servisa

Isti recept kao frontend u Dockeru:

1. svoj folder, svoj `compose.yaml`
2. `networks: [edge]` sa `external: true`
3. Traefik labele sa svojim `Host(...)`
4. DNS zapis
5. `docker compose up -d`

Ništa centralno se ne dira. Ako servisu treba baza, dodaje svoj Postgres na svoju
`internal` mrežu — ne deli ovaj.

---

## 7. Komande

| | |
| --- | --- |
| `./deploy.sh` / `make deploy` | pun deploy |
| `make rollback` | prethodni image-i nazad |
| `make backup` | dump baze odmah |
| `make prod-ps` | status kontejnera |
| `make prod-logs [s=php]` | logovi |
| `make prod-artisan c="..."` | artisan komanda |
| `make prod-psql` | psql (port nije objavljen, ovo je jedini ulaz) |
| `make prod-shell` | shell u php kontejneru |
| `make prod-reload` | recreate aplikacije, pokupi izmenjen `.env.production` |
| `make prod-restart` | restart procesa, zadržava postojeći environment |
| `make prod-key` | novi APP_KEY |
| `make edge-up` / `edge-down` / `edge-logs` | Traefik |
| `make observe-prod` / `observe-prod-down` | Loki + Grafana |

---

## 8. Šta ovaj setup NE pokriva

Pošteno, da ne otkriješ u pogrešnom trenutku:

- **Jedna mašina.** Padne li server, padne sve. Nema replika, nema failovera.
- **Nije zero-downtime.** Maintenance mode traje koliko i migracije.
- **Backupi su na istom disku.** Odnesi ih negde. Ovo je tvoj posao.
- **Docker socket je root nad hostom**, i Traefik i Alloy ga čitaju. Ublažava se
  socket proxy kontejnerom; za jedan lični VPS je svesno preskočeno.
- **Nema staging okruženja.** `main` ide pravo u produkciju.
- **Secrets su fajl na disku.** Bez Vaulta, bez SSM-a. Za jedan server u redu;
  za tim od deset ljudi nije.
- **Nema alertovanja.** Grafana pokazuje šta se desilo kad odeš da pogledaš.
  Niko te ne zove u tri ujutru.

Sve su to svesne odluke za jedan VPS, ne propusti. Svaka od njih je razgovor za
onaj dan kad aplikacija preraste ovaj oblik.
