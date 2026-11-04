# BuilderLog React / Laravel deployment

## Build script and cPanel upload

From this project root, run `./build.sh` for local development or
`./build.sh --production` to prepare dependencies without development packages
for upload. The script automatically uses `.cache/php84/activate` when present,
installs locked dependencies, builds and verifies `public/app/`, and clears
Laravel's local configuration, route, event and view caches. It stops on errors.
Production mode changes the local `vendor/` directory; run `./build.sh` again
to restore development packages.

It does not generate an application key, migrate the database, start a server,
or create production caches. For local preview, activate PHP and run
`php artisan serve`, then open `http://127.0.0.1:8000/app/`.

Upload these runtime directories and files to the Laravel directory on cPanel:

- `app/`, `bootstrap/`, `config/`, `database/migrations/`, `resources/`, `routes/`
- `public/`, including the generated `public/app/` and hidden `.htaccess`
- `vendor/` from the production build
- `artisan`, `composer.json`, `composer.lock`
- The `storage/` directory structure (including `framework/cache/data`,
  `framework/sessions`, `framework/views` and `logs`), writable by PHP.

Exclude laptop-generated `bootstrap/cache/*.php`, `storage/framework` cache,
session and compiled view contents, local logs, local database files,
`public/hot`, `public/storage` symlinks and development font manifests.
Keep existing server uploads and storage data when updating an installation.
Do not upload `.git/`, `.cache/`, `.aws/`, `.codex/`, `.agents/`, `node_modules/`,
`BuilderLogPWA/`, tests, or the laptop activation script. Frontend sources and
Node are not needed on the server because the assets are already compiled.

Keep the server's production `.env` and `APP_KEY`. On a fresh installation,
create a production `.env` with `APP_ENV=production`, `APP_DEBUG=false`, the
HTTPS `APP_URL`, database credentials and required Firebase settings; generate
an `APP_KEY` once with `php artisan key:generate`. Provision Firebase credential
files separately outside `public/`. Never overwrite an existing production key
with the laptop key.

The domain document root must point to the project's `public/` directory,
for example `/home/ACCOUNT/builder_log/public`. The application is served at
`https://YOUR-DOMAIN/app/`. Do not expose the whole Laravel project through
`public_html`. If cPanel does not let you change the domain's document root,
ask the host to configure it before uploading to a public location.

On cPanel, use PHP 8.4.1+ (8.4 or 8.5 compatible with the current lock file)
for both the website and terminal. From the deployed Laravel root, after
configuring `.env` and writable `storage/` and `bootstrap/cache/`, run:

```sh
composer check-platform-reqs --no-dev
php artisan config:clear
php artisan route:clear
php artisan event:clear
php artisan view:clear
php artisan storage:link
php artisan optimize
```

Create the storage link only if needed and not already correctly configured.
If the release includes pending database migrations, back up the production
database and apply `php artisan migrate --force` before caching/serving the new
release. Do not run seeders or `migrate:fresh` against an existing production
database. Configure a queue worker/scheduler separately if the deployed app
uses them. `php artisan serve` is only for local preview.

References: [Laravel deployment](https://laravel.com/framework/docs/deployment),
[cPanel domain management](https://docs.cpanel.net/cpanel/domains/domains/manage-the-domain/110/).

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

Firebase Web SDK i worker `/app/firebase-messaging-sw.js` sada su dodati kao osnova za obaveštenja. Worker se registruje samo iz instalirane iOS/iPadOS PWA aplikacije, sa scope `/app/`. Vite ga pakuje u jedan lokalni JavaScript fajl, u razvoju i produkciji. Nema fetch handlera, offline režima niti keširanja stranica ili asseta. Zajedničko skladište za stranicu i worker koristi IndexedDB bazu `radulovic.notifications`. Backend registracija prihvata platformu `web`. Posle prijave, uz odobrenu sistemsku dozvolu i uključena obaveštenja, PWA dobija FCM token pomoću Web/VAPID konfiguracije i šalje ga na `push/register`. Odobravanje dozvole i uključivanje u podešavanjima pokreću isti postupak. Povratak u aplikaciju i ponovno povezivanje proveravaju token i ponavljaju neuspelu registraciju. Isključivanje obaveštenja uklanja lokalnu push pretplatu čak i kada server nije dostupan; neuspešna odjava tokena se ponavlja pri sledećem povezivanju sa istim nalogom. Odjava korisnika, kao u Androidu, čeka uspešan `push/unregister`; ako ne uspe, sesija ostaje otvorena i prikazuje se Toast. Token, ID korisnika, uloga i stanje registracije dostupni su workeru kroz IndexedDB, bez lozinke ili API autorizacionog tokena. Postojeći sačuvani spiskovi obaveštenja još nisu migrirani. Prijem i sistemski prikaz poruka sada obrađuje `MyFirebaseMessagingService.js` u workeru. Koristi iste tipove, tekst i parsiranje ID-jeva kao Android, isti ID ažurira postojeće obaveštenje, a test poruka servera radi i bez `notification_id`. Isključena obaveštenja ili obrisana registracija sprečavaju prikaz. Worker čeka završetak `showNotification` i sprečava dupli automatski prikaz kroz Firebase.

iOS zahteva vidljivo obaveštenje za svaki push, pa se sistemsko obaveštenje kreira i kada je aplikacija otvorena. Zbog toga je standardni `push` handler registrovan pre Firebase handlera koji bi preskočio sistemski prikaz za vidljiv prozor. Grupisanje, izgled i zvuk određuje iOS; ne pravi se dodatno Android zbirno obaveštenje. Otvaranje ili povratak u instaliranu iOS PWA aplikaciju sada uklanja sva njena sistemska obaveštenja, kao Android pri nastavku aktivnosti. Dodir obaveštenja takođe uklanja sva sistemska obaveštenja i fokusira postojeći prozor; ako prozor ne postoji, otvara se `/app/`. `NotificationRouter.js` zatim otvara isti ekran kao Android: konkretan dnevni unos, dokumente za navedeno gradilište, zadatke sa odgovarajućim tabom i filterom zaposlenog/gradilišta, ili aktivnost radnika. Odredište se čuva kroz prijavu i izbor gradilišta, vezano za nalog koji je primio obaveštenje. Uspešno otvoreno obaveštenje uklanja se iz postojećeg sačuvanog spiska; neuspešno učitavanje dnevnog unosa zadržava obaveštenje i prikazuje Android Toast. Registracija tokena i stanje prijave se time ne brišu. Prijem u sačuvanu istoriju i povezivanje zvonca/liste ostaju naredni koraci. Routing provere su u `BuilderLogPWA/tests/notification-routing/`: 30 unit testova i 20 browser scenarija sa produkcionim workerom. Klikovi u browser testovima su sintetički; OS focus/open je zabeležen, a IndexedDB, postMessage i navigacija aplikacije su stvarni. Dodir sistemskog obaveštenja na fizičkom iPhone-u još zahteva proveru.

Provere u `BuilderLogPWA/tests/notification-background/` pokrivaju stvarni produkcioni worker i browser Notifications API sa lokalno ubrizganim push događajima: otvoren, sakriven i zatvoren prozor, ponovno pokretanje workera, duplikate, dozvole i OFF/odjavu. Korisnik je potvrdio osnovni prijem na iPhone-u. Novi tok brisanja i otvaranja nakon dodira još zahteva proveru na fizičkom uređaju. Smernice: [Apple Web Push](https://webkit.org/blog/12945/meet-web-push/) i [Firebase prijem poruka](https://firebase.google.com/docs/cloud-messaging/web/receive-messages).

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
