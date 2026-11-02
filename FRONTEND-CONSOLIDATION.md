# Konsolidacija frontend izvora

Originalni frontend repozitorijum ostaje na:

`C:\Projects\BuilderLogProject\BuilderLogPWA`

Nova nezavisna kopija izvora u glavnom Laravel repozitorijumu je:

`C:\Projects\BuilderLogProject\builderLogBackend\BuilderLogPWA`

## Kopiranje i Git

Kopiran je 461 izvorni, konfiguracioni, dokumentacioni i statički fajl, uključujući `src/`, `public/`, `scripts/`, `tests/`, `reference/`, npm manifest/lock i Vite konfiguraciju. Kopirano je aktuelno radno stanje, uključujući postojeće necommitovane frontend izmene.

Nisu kopirani `.git/`, `node_modules/`, `dist/`, `.local/`, IDE i drugi lokalni keševi, ZIP arhive, lokalni logovi ili privatni `.env` fajlovi. `.env.example` je kopiran.

`builderLogBackend/BuilderLogPWA/.git` ne postoji. Nema Git submodula, subtree-a, hook-ova ili automatske sinhronizacije. Originalni frontend `.git` postoji i nije izvršavana nijedna destruktivna Git operacija. Hash poređenje svih 461 kopiranih originalnih fajlova potvrđuje da je originalni izvor ostao neizmenjen. Zavisnosti u kopiji su zasebno instalirane kroz `npm ci`, nisu prenete iz originala.

Laravel Git vidi `BuilderLogPWA/` kao običan novi izvorni folder (`?? BuilderLogPWA/`). `src/`, `public/`, `package.json`, `package-lock.json` i `vite.config.js` nisu ignorisani i pojavljuju se kao normalni fajlovi za dodavanje. Commit i staging nisu izvršavani. Prethodno postojeće izmene Laravel ruta i integracionih fajlova ostale su sačuvane.

## Prilagođene putanje

U kopiranom frontendu promenjena su samo četiri fajla u odnosu na original:

- `BuilderLogPWA/vite.config.js`: Laravel koren se sada razrešava kao roditelj frontend foldera (`../`), umesto preko sibling naziva `../builderLogBackend/`. Izlaz je apsolutno razrešen **`../public/app/`**.
- `BuilderLogPWA/scripts/verify-build.js`: putanja izlaza je `../../public/app/` u odnosu na skriptu.
- `BuilderLogPWA/tests/deployment.test.js`: očekivane i zabranjene putanje prate novi raspored; ostaje provera odbijanja `public/` pre pokretanja builda.
- `BuilderLogPWA/README.md`: razvojne/build komande i link ka deployment dokumentaciji prilagođeni su glavnom repozitorijumu.

U Laravel korenu:

- `.gitignore`: eksplicitno su dodati `/BuilderLogPWA/node_modules/`, `/BuilderLogPWA/dist/`, `/BuilderLogPWA/.local/` i `/BuilderLogPWA/.idea/`. Postojeći `/public/app/` ostaje ignorisan. Kopirani frontend `.gitignore` dodatno isključuje njegove lokalne env/privremene fajlove.
- `PWA-DEPLOYMENT.md`: glavni workflow sada zahteva jedan Laravel checkout i koristi ugrađenu kopiju frontend izvora.
- `FRONTEND-CONSOLIDATION.md`: ovaj izveštaj.

Originalni standalone repozitorijum i njegova konfiguracija nisu menjani. Dve kopije se za sada održavaju nezavisno; prenos izmena nije automatizovan.

## Build i razvoj iz glavnog projekta

Iz `builderLogBackend/`:

```sh
npm --prefix BuilderLogPWA ci
npm --prefix BuilderLogPWA run build
npm --prefix BuilderLogPWA run verify:build
```

Generisani izlaz ostaje **`builderLogBackend/public/app/`** i ne ulazi u Git. Vite baza ostaje **`/app/`**. `emptyOutDir: true` ostaje ograničen na tačan Laravel `public/app/`; zaštita odbija drugi izlaz ili simboličku putanju van predviđenog foldera.

Frontend razvoj iz ugrađene kopije pokreće se sa `npm --prefix BuilderLogPWA run dev`, uz postojeći Laravel server. Originalni frontend developer može nastaviti sa `npm install` / `npm run dev` u svom originalnom sibling repozitorijumu.

Kasniji deployment iz jednog glavnog repozitorijuma može da koristi:

```sh
git pull
composer install --no-dev --optimize-autoloader
npm --prefix BuilderLogPWA ci
npm --prefix BuilderLogPWA run build
php artisan optimize
```

Deployment nije izvršen tokom ovog zadatka. Postojeće postupke za produkcioni `.env`, storage i Laravel cache/release održavanje zadržati prema `PWA-DEPLOYMENT.md`.

## Izvršene strukturne provere

- `npm --prefix BuilderLogPWA ci`: uspešna instalacija 18 zaključanih paketa u novoj kopiji.
- `npm --prefix BuilderLogPWA run build`: uspešan build; izlaz samo u `../public/app/`. Ostaje postojeće upozorenje o JS paketu preko 500 kB.
- `npm --prefix BuilderLogPWA run verify:build`: ispravni resursi i manifest pod `/app/`.
- `npm --prefix BuilderLogPWA test`: prolaze 2 postojeća strukturna testa izlazne putanje i zaštite `public/`.
- `php artisan --version`: Laravel 13.37.0 se učitava.
- `php artisan route:list --path=app --except-vendor`: postojeća GET/HEAD ruta `app/{path?}` ostaje registrovana; `public_path('app/index.html')` i dalje pokazuje na generisani ulaz.
- Git potvrđuje da su lokalno instalirani nested `node_modules` i generisani `public/app/` ignorisani, a frontend izvor dostupan glavnom repozitorijumu.
- Hash poređenje potvrđuje da svi kopirani `src/`, `public/`, reference asseti, npm manifest i lock odgovaraju originalu. Osim četiri navedena putanjska/dokumentaciona fajla, kopija je neizmenjena.
- Zaštićeni postojeći Laravel `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, Composer/npm manifesti, Laravel Vite i ulazni public fajlovi ostali su neizmenjeni tokom ove konsolidacije.

Nisu menjani React ekrani, CSS, auth, API baza (`VITE_API_BASE_URL` uz fallback `/api/v1/`), screen-state navigacija, Laravel rute, poslovna logika ili baza. Nisu dodati service worker, Firebase/FCM ili offline keširanje.

Prijava, CRUD i ostali ručni funkcionalni testovi nisu ponavljani u ovoj fazi, prema zahtevu. Lokalni rezultati hash provera nalaze se u ignorisanom `.cache/frontend-consolidation/` direktorijumu.
