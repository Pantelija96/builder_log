# BuilderLog React / Laravel deployment

## Raspored

Glavni full-project repozitorijum sada je `builderLogBackend/`. Sadrži Laravel i običan frontend izvorni folder bez zasebnog Git repozitorijuma:

```text
workspace/
  BuilderLogPWA/                 # originalni samostalni repo, ostaje netaknut
  builderLogBackend/             # glavni Git repozitorijum
    BuilderLogPWA/               # njegova nezavisna kopija frontend izvora
      src/
      public/
      package.json
      package-lock.json
      vite.config.js
    public/
      index.php                  # postojeći Laravel ulaz
      app/                       # samo generisani React build
```

Za build i deployment glavnog projekta dovoljan je jedan checkout `builderLogBackend/`. Frontend se gradi iz njegove kopije `BuilderLogPWA/`, bez oslanjanja na naziv ili prisustvo sibling repozitorijuma. Na produkciji ne radi zaseban React/Vite server. DocumentRoot ostaje Laravel `public/`.

Originalni sibling frontend repo ostaje dostupan za frontend-only rad. Kopije se ne sinhronizuju automatski: nema submodula, subtree-a, hook-ova ili pull/push skripti. Eventualni prenos izmena je za sada ručan i nameran. Aktuelna provera konsolidacije je u [FRONTEND-CONSOLIDATION.md](FRONTEND-CONSOLIDATION.md).

`public/app/` je ignorisan u Laravel Git repozitorijumu. Generiše se prilikom builda u CI-ju ili pripreme release-a, pa ulazi u deployment artefakt. Ne treba ga ručno kopirati iz različitih verzija izvora niti čistiti release nakon builda komandom koja briše ignorisane fajlove.

## Razvoj

U `builderLogBackend/` koristiti postojeći `.env` i postojeću MySQL bazu:

```sh
php artisan route:clear
php artisan serve
```

`route:clear` je potreban posle dodavanja rute ako je prethodno postojao keš ruta. Nije potrebna migracija, seeder ili promena baze za ovu integraciju.

U drugom terminalu, takođe iz Laravel korena:

```sh
cd BuilderLogPWA
npm ci
npm run dev
```

Vite prikaz je tipično `http://localhost:5173/app/`. Proxy prosleđuje `/api/*` lokalnom Laravelu na `http://127.0.0.1:8000`, bez promene putanje zahteva. Za drugi Laravel port postaviti `PWA_DEV_API_TARGET` u ignorisanom `BuilderLogPWA/.env.local`. Primer bez kredencijala postoji u `.env.example`.

Novi `builderLogBackend/server.php` služi samo ugrađenom PHP / `artisan serve` serveru. Normalizuje ulazni PHP script path za zahteve koji treba da stignu u Laravel: PHP inače može izabrati fizički `app/index.html` kao bazu i ukloniti `/app` iz putanje. Postojeći statički fajlovi ostaju direktno servisirani. Apache/Nginx i `public/.htaccess` nisu menjani.

## Produkcioni build i isporuka

Iz korena `builderLogBackend/` (jedan checkout):

```sh
composer install --no-dev --optimize-autoloader
npm --prefix BuilderLogPWA ci
npm --prefix BuilderLogPWA run build
npm --prefix BuilderLogPWA run verify:build
```

Zatim, u pripremljenom Laravel release direktorijumu sa odgovarajućim produkcionim `.env`:

```sh
php artisan optimize:clear
php artisan optimize
```

Ove deployment komande su uputstvo; produkcioni deploy nije izvršen ovim zadatkom. Zadržati postojeći zaseban build Laravel `resources/` resursa ako ga postojeća isporuka koristi. Laravel `vite.config.js`, `package.json`, Blade i postojeći resursi nisu menjani.

Vite baza je **`/app/`** i u razvoju i u produkciji. `build.outDir` je apsolutno razrešen put **`../public/app/`** u odnosu na frontend folder (Laravel **`public/app/`**). `emptyOutDir: true` važi isključivo za taj direktorijum. Konfiguracija pre builda odbija drugi izlazni direktorijum i simboličke putanje koje bi čišćenje odvele izvan očekivanog foldera. Test negativnih konfiguracija poziva samo Vite rezoluciju konfiguracije, bez pokretanja destruktivnog builda ka `public/`.

## API i navigacija

`VITE_API_BASE_URL` je opciona build-time konfiguracija. Fallback je **`/api/v1/`** na trenutnom origin-u, u svim režimima. Završna kosa crta normalizuje se zbog postojećeg spajanja sa endpointom. Na produkciji zahtevi idu na isti domen, na primer `/api/v1/login`. Vrednost je javna konfiguracija frontenda; ne unositi tajne ili pristupne podatke baze u `VITE_*` promenljive.

Nisu menjani endpointi, payload-i, obrada odgovora, tokeni, auth flow, uloge ili poslovna logika. Dinamičke lokalne slike koriste zajednički `ASSET_BASE = import.meta.env.BASE_URL + 'assets/'`; CSS/HTML public URL-ove prilagođava Vite. CSS pravila i vizuelni dizajn nisu menjani.

React nema URL ruter. Ostaju postojeća screen-state navigacija i postojeći history slojevi. Nisu dodati React Router, `basename`, `/settings`, `/workers` ili drugi URL ekrani. Tokom korišćenja aplikacije URL ostaje `/app/`.

Laravel GET/HEAD ruta `/app/{path?}` pokriva samo `/app` i `/app/*`. Vraća generisani `app/index.html` sa revalidacijom; bez builda vraća 503. Nedostajući `/app/assets/*` i resursi sa ekstenzijom vraćaju 404. API, storage, početna Laravel stranica i ostale rute nisu globalno preuzeti SPA fallback-om.

## Manifest, service worker i Firebase

Manifest sada koristi relativne `start_url` i `scope` (`./`) i relativne ikone. Pri učitavanju sa `/app/manifest.webmanifest` razrešavaju se pod `/app/`. Postojeći manifest `id: "/"` je zadržan radi identiteta aplikacije; to nije navigaciona ruta.

U React projektu nije postojala Firebase/FCM ili service-worker implementacija. Prema izričitom zahtevu **nije dodat service worker, Firebase/FCM, offline režim ili cache logika**. `/app/sw.js` ne postoji i vraća 404. Backend Firebase nije menjan. Fizička instalacija PWA na telefonu nije testirana; mobilni browser raspored jeste.

## Provere prethodne integracione faze

Sledeće funkcionalne provere pripadaju prethodnoj integracionoj fazi. Nisu ponavljane tokom strukturne konsolidacije; njeni rezultati su u zasebnom izveštaju.

- `npm run build`: uspešan build u `public/app/`. Ostaje upozorenje o veličini glavnog JS paketa preko 500 kB; nije rađen nepovezan refaktoring paketa.
- `npm run verify:build`: ispravni `/app/` URL-ovi u HTML-u, JS/CSS-u i manifestu; ikone i ulazni resursi postoje; nema service workera.
- `npm test`: 2 testa zaštite izlaznog direktorijuma prolaze.
- `PwaRoutingTest`: 4 testa / 28 provera prolaze bez pristupa bazi. Provereni su SPA ulaz/fallback, metode, odsutni asseti, postojeći ping, neautentifikovani API, validacija praznog login zahteva, nepoznat API URL i ruta van `/app`.
- HTTP provera stvarnog Laravel servera: `/app`, `/app/`, tehnička rezervna putanja `/app/technical-fallback/path`, nedostajući asseti, `/api/ping`, `/api/v1/me`, `/`, manifest i 45 ulaznih resursa, uz dodatno proverene CSS resurse. Tehnička rezervna putanja nije novi React ekran.
- HTTP provera Vite dev servera i njegovog API proxy-ja prolazi.
- Pregledačka provera produkcionog builda: stvarna prijava odobrenim postojećim testnim nalogom, stvarno učitavanje zaposlenih, otvaranje/povratak iz Settings, početni ekran i postojeća desktop odjava. Bez čuvanja izmena naloga.
- Stvarna mobilna prijava, otvaranje Settings i postojeća potvrda odjave takođe su provereni.
- Podešavanja su proverena na 390, 768, 1024, 1280, 1440 i 1920 px: bez horizontalnog prelivanja, postojeći sidebar breakpoint i šest postojećih polja su očuvani. Slike na proverenom ekranu nemaju greške učitavanja.
- Hash poređenje sa početnim stanjem potvrđuje da nijedan postojeći Laravel `app/`, `routes/api.php` ili `public/` fajl nije promenjen; jedini izuzetak u postojećim Laravel source fajlovima je `routes/web.php`. Svi postojeći React CSS fajlovi su identični prethodnom stanju.
- Postojeći generisani Laravel route cache očišćen je standardnom `route:clear` komandom. `.env`, konfiguracija baze, poslovni podaci, migracije i seederi nisu menjani.

Pokretanje regresionog testa:

```sh
cd builderLogBackend
php artisan test --filter=PwaRoutingTest
```

Ako postoje lokalni keševi konfiguracije/ruta koje ne želite da dirate tokom testiranja, `APP_CONFIG_CACHE` i `APP_ROUTES_CACHE` mogu za taj proces pokazivati na nepostojeće relativne fajlove pod `.cache/`. Izolovane provere koristile su i `SESSION_DRIVER=array` / `CACHE_STORE=array` samo za test proces.

Opciona HTTP provera servera koji već radi:

```sh
cd BuilderLogPWA
node scripts/verify-http.js http://127.0.0.1:8000
node scripts/verify-http.js http://127.0.0.1:5173 --dev
```

Nisu testirane poslovne CRUD mutacije, rad na pravom mobilnom uređaju ili produkcioni web-server/deploy; nisu deo promene funkcionalnosti u ovoj integraciji.

## Fajlovi prethodne integracione faze

Laravel: `.gitignore`, `routes/web.php`, novi `server.php`, novi `tests/Feature/PwaRoutingTest.php` i ovaj dokument.

React konfiguracija/pakovanje: `.gitignore`, `.env.example`, `package.json`, novi `vite.config.js`, novi `tests/deployment.test.js`, novi `scripts/verify-build.js`, novi `scripts/verify-http.js`, `public/manifest.webmanifest` i novi `src/com/s2d2/radulovic/utils/AssetPaths.js`.

U `network/WebUtils.js` promenjen je samo način određivanja API baze. U sledećim komponentama korigovani su isključivo import zajedničke asset baze i lokalni asset URL-ovi:

- `src/com/s2d2/radulovic/screens/admin/AdminConstructionSiteExpensesActivity.jsx`
- `src/com/s2d2/radulovic/screens/admin/AdminEmployeeForm.jsx`
- `src/com/s2d2/radulovic/screens/admin/AdminShared.jsx`
- `src/com/s2d2/radulovic/screens/admin/AdminSubcontractorDetailsActivity.jsx`
- `src/com/s2d2/radulovic/screens/admin/AdminWorkerPaymentsActivity.jsx`
- `src/com/s2d2/radulovic/screens/admin/AdminWorkersActivity.jsx`
- `src/com/s2d2/radulovic/screens/admin/AdminWorkHistoryActivity.jsx`
- `src/com/s2d2/radulovic/screens/general/LoginActivity.jsx`
- `src/com/s2d2/radulovic/screens/general/SettingsActivity.jsx`
- `src/com/s2d2/radulovic/screens/manager/DeliveryNotesActivity.jsx`
- `src/com/s2d2/radulovic/screens/manager/DocumentsActivity.jsx`
- `src/com/s2d2/radulovic/screens/manager/ExpensesActivity.jsx`
- `src/com/s2d2/radulovic/screens/manager/ManagerHomeActivity.jsx`
- `src/com/s2d2/radulovic/screens/manager/ManagerMachinesScreen.jsx`
- `src/com/s2d2/radulovic/screens/manager/NotesActivity.jsx`
- `src/com/s2d2/radulovic/screens/manager/SubcontractorsActivity.jsx`
- `src/com/s2d2/radulovic/screens/manager/TasksActivity.jsx`
- `src/com/s2d2/radulovic/screens/manager/WorkersActivity.jsx`
- `src/com/s2d2/radulovic/ui/components/AttendancePickers.jsx`
- `src/com/s2d2/radulovic/ui/components/AuthenticatedImage.jsx`
- `src/com/s2d2/radulovic/ui/components/DesktopShell.jsx`
- `src/com/s2d2/radulovic/ui/components/DesktopUserMenu.jsx`
- `src/com/s2d2/radulovic/ui/components/DriverTasksCard.jsx`
- `src/com/s2d2/radulovic/ui/components/DriverWorkScreen.jsx`
- `src/com/s2d2/radulovic/ui/components/NetworkLoadingOverlay.jsx`
- `src/com/s2d2/radulovic/ui/components/SelectionDropdown.jsx`
- `src/com/s2d2/radulovic/ui/components/StandardSiteDateControls.jsx`
- `src/com/s2d2/radulovic/ui/components/StartupSplash.jsx`

Lokalne slike pregleda su u ignorisanom `BuilderLogPWA/.local/integration/` direktorijumu. `BuilderLogPWA/README.md` sadrži kratke komande za razvoj i build.
