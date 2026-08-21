# Posmatranje aplikacije lokalno (Loki + Alloy + Grafana)

Dev alat. Diže se odvojeno od aplikacije i ne dira je ni u čemu.

```
make observe          # digni Grafanu, Loki i Alloy
make observe-down     # spusti (logovi ostaju na disku)
make observe-destroy  # spusti i obriši sve prikupljene logove
make observe-logs     # šta rade sami ovi servisi (make observe-logs s=alloy)
```

Grafana: **http://localhost:3000** — nema prijave, otvara se odmah.
Dashboard je u folderu **Laravel**, zove se **„Laravel — zahtevi i jobovi"**.

## Šta je šta

| servis | čemu služi |
| --- | --- |
| **Loki** | skladište logova i pretraga. Nije objavljen na host, priča samo sa Alloy-em i Grafanom |
| **Alloy** | čita stdout svih kontejnera preko Docker socket-a i šalje u Loki |
| **Grafana** | UI |

Aplikacija ne zna da ovo postoji. Nema logging drajvera po servisu, nema
sidecar-a, nema montiranih fajlova — Alloy čita Docker socket, pa se `php`,
`horizon`, `scheduler`, `nginx` i baze pokupe same od sebe.

> Alloy je naslednik Promtail-a, koji je Grafana ugasila (EOL mart 2026).
> Stariji tutorijali pominju Promtail; ovde ga nema namerno.

## Prvi koraci

1. `make up` pa `make observe`
2. Napravi malo saobraćaja (bilo koji poziv API-ja)
3. Otvori http://localhost:3000 → Dashboards → Laravel

Ako je sve prazno, prvo proveri da li aplikacija uopšte radi, pa `make
observe-logs s=alloy`.

## Kako da nađeš ono što ti treba

**Ceo put jednog zahteva.** Svaki odgovor nosi header `X-Request-Id`. Zalepi tu
vrednost u polje **Request ID** na vrhu dashboard-a i dobiješ zahtev i **svaki
job koji je iz njega proistekao**, iako job radi u drugom kontejneru:

```
php       request  api/users                                    -> 201        (476ms)
horizon   job      App\Notifications\AccountCreatedNotification  -> processed  (2403ms)
```

**Sve greške:** panel „Greške i upozorenja" ili u Explore:

```logql
{service=~"php|horizon|scheduler", level=~"ERROR|CRITICAL"}
```

**Šta se dešavalo sa jednim korisnikom:**

```logql
{service="php"} | json | context_user_id = "12"
```

**Spori zahtevi:**

```logql
{service="php"} | json | message="request" | context_duration_ms > 500
```

**Koliko je 5xx bilo u poslednjem satu:**

```logql
sum(count_over_time({service="php"} | json | message="request" | context_status >= 500 [1h]))
```

## Kako se čita naša log linija

`| json` u Loki-ju spljošti ugnježdene objekte donjom crtom, pa polja izgledaju
ovako:

| u liniji | u upitu |
| --- | --- |
| `message` | `message` (`"request"`, `"job"`, `"job attempt failed"`) |
| `context.status` | `context_status` |
| `context.route` | `context_route` |
| `context.duration_ms` | `context_duration_ms` |
| `context.user_id` | `context_user_id` |
| `extra.request_id` | `extra_request_id` |

**Labele su samo `service`, `project`, `container`, `env` i `level`.** Sve
ostalo — `request_id`, `user_id`, `route` — živi unutar linije i vadi se sa
`| json`. To nije previd: label sa mnogo vrednosti pravi zaseban stream po
vrednosti i obori Loki. Ako ikad dodaješ label, pitaj se koliko različitih
vrednosti može da ima; ako odgovor nije „šačica", nije label.

## Zašto Prometheus i Tempo nisu ovde

Metrike koje ti trebaju na početku već postoje **unutar logova**: `context_status`
i `context_duration_ms` daju i stopu grešaka i p95 po ruti, bez ijednog novog
servisa. Prometheus ima smisla kad zatrebaju metrike koje log ne nosi (CPU,
konekcije, dubina queue-a). Tempo ima smisla kad servisa bude više od jednog —
do tad `request_id` radi isti posao.

Za stanje same aplikacije tu su i dalje **Horizon** (`/horizon`) i **Pulse**
(`/pulse`); oni odgovaraju na „šta se dešava sada", a Grafana na „šta se
desilo".

## Zašto je ovo zaseban compose fajl

- `make up` ostaje brz; ovaj stack pojede oko gigabajt RAM-a
- kad se pojavi drugi servis, observability ne može da živi unutar jednog od njih
- na produkciji Grafana nije deo aplikacije, pa nije ni ovde

Jedina spona sa aplikacijom je Docker socket, pa se ovaj folder seli u zaseban
`infra` repo bez ijedne izmene u aplikaciji.

## Ovo NIJE produkcioni setup

- Grafana je bez lozinke i sa anonimnim admin pristupom
- Loki nema retenciju — čisti se sa `make observe-destroy`
- nema TLS-a, nema alertovanja, nema backup-a
- Docker socket je montiran (read-only, ali je i dalje root pristup hostu)

U logovima su email adrese i IP adrese korisnika. Port je vezan za `127.0.0.1`
baš zato. Produkciona verzija je zaseban posao.
