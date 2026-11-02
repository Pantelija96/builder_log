# Desktop prikaz BuilderLog PWA

Izmene su ograničene na prezentaciju u `BuilderLogPWA/`. Nisu menjani API pozivi, poslovna logika, autentifikacija, modeli, Firebase, putanje navigacije, postojeći handleri, validacija niti proračuni. Laravel i struktura isporuke nisu menjani.

## Izmenjeni fajlovi

- `src/main.jsx`: import desktop stilova.
- `src/com/s2d2/radulovic/screens/admin/AdminHomeActivity.jsx`: postojeći prikaz i njegove podstranice obavijeni zajedničkim okvirom.
- `src/com/s2d2/radulovic/screens/manager/ManagerHomeActivity.jsx`: isti prezentacioni okvir za poslovođu.
- `src/com/s2d2/radulovic/ui/components/DesktopShell.jsx`: nova zajednička komponenta bočne navigacije, zaglavlja i korisničkih podataka. Dobija postojeći meni i postojeće callback funkcije; nema sopstveno poslovno stanje, API pozive ili proveru uloga.
- `src/com/s2d2/radulovic/ui/theme/Desktop.css`: izolovani desktop rasporedi.
- `DESKTOP-UI.md`: ovaj izveštaj.

## Raspored

Desktop počinje od **1024 px**. Bočni meni je širok 232 px; na **1440 px** prelazi na 256 px. Na većim ekranima početni meni dobija četiri kolone. Glavni sadržaj sa unutrašnjim razmacima ima maksimalnu širinu 1664 px, odnosno do 1584 px korisne širine. Mobilni i tablet prikaz ispod 1024 px zadržavaju postojeći CSS; novi omotači koriste `display: contents`, a desktop navigacija i zaglavlje su skriveni.

Zajednički desktop obrasci obuhvataju:

- početni pregled administratora i poslovođe sa karticama postojećih destinacija;
- zaglavlja stranica i vidljiv fokus tastature;
- horizontalne birače gradilišta i datuma i čitljivije redove prisustva;
- mreže dokumenata, građevinskog dnevnika, mašina i podizvođača;
- šire preglede gradilišta i njihove postojeće akcije;
- profil radnika uz period, finansijske sažetke i istoriju;
- raspored troškova, sažetaka i njihovih akcija;
- forme u dve kolone, duža tekstualna polja preko cele širine i akcije desno;
- ograničene širine odabranih modala, uključujući birač vremena;
- desktop panele radnog dana vozača i bageriste i ograničen ekran podešavanja.

## Stvarno izvršene provere

Početno Git stanje bilo je čisto. Poređenje sa `HEAD` potvrđuje da, posle uklanjanja novih omotača i importa, sadržaj oba izmenjena početna ekrana ostaje identičan izvornom fajlu.

Pošto puna aplikacija ima postojeći problem sa nedostajućim modulom, napravljena je ignorisana lokalna probna stranica u `.local/desktop-review.html`. Ona koristi stvarne komponente `DesktopShell`, `AdminWorkHistoryActivity`, `AdminDocumentsActivity`, `AdminNotesActivity` i `TaskCreationActivity`, uz probne podatke. Početni meni na toj stranici je skraćeni probni prikaz. Mrežni pozivi i upisi blokirani su u probnoj stranici; produkcioni kod nije prilagođavan za testiranje.

- Za evidenciju rada, dokumente, dnevnik i kreiranje zadatka proverene su širine **390, 768, 1023, 1024, 1280, 1440 i 1920 px**. Nije zabeleženo horizontalno prelivanje dokumenta; bočni meni je skriven ispod 1024 px.
- Na **390 i 768 px** poređeni su položaji, dimenzije, veličine fonta i `display` vrednosti postojećih naslova, polja, dugmadi i kartica u ta četiri ekrana sa izvornim stilovima bez desktop okvira. Sva poređenja su jednaka. To je provera rasporeda reprezentativnih komponenti, a ne svih mobilnih tokova aplikacije.
- Provereni su PDF filter dokumenata, otvaranje i otkazivanje modala za brisanje bez brisanja podataka, i unos u postojeće polje naziva zadatka bez slanja forme.
- Modal dokumenta je na 1024 px centriran i širok 680 px.
- Izolovani Vite build probne stranice sa 90 modula prolazi. Upozorenja za apsolutne putanje fontova i SVG-a očekivana su za ovu konfiguraciju koja ne kopira `public/`.
- `npm test` završava uspešno, ali pronalazi **0 testova**; to nije dokaz funkcionalnog pokrića.
- `git diff --check` prolazi.

## Ograničenje pune provere

`npm run build` ne prolazi zbog već postojećeg nedostajućeg fajla `src/com/s2d2/radulovic/utils/DateSorting.js`. Uvoze ga:

- `screens/manager/TasksActivity.jsx`
- `screens/admin/AdminExpensesByManagerActivity.jsx`
- `screens/admin/AdminAssignAdvanceToManagerActivity.jsx`
- `ui/components/DriverTasksCard.jsx`

Fajl nije pronađen ni u trenutnom Git stablu ni u dve postojeće ZIP arhive. Nije dodavana zamenska logika sortiranja, jer je zadatak ograničen na vizuelne izmene.

Nisu testirani prijavljivanje, stvarni API odgovori, CRUD upisi i kompletni radni tokovi. Nakon vraćanja originalnog modula treba izvršiti pun build i proveru kroz autentifikovanu aplikaciju, posebno duboke ekrane radnika, finansije, mašine, gradilišta, podizvođače i radni dan vozača/bageriste. Ti ekrani imaju desktop CSS podršku, ali nisu vizuelno potvrđeni sa stvarnim podacima.

Lokalni pregled evidencije sa probnim podacima sačuvan je u `.local/desktop-attendance.png`. Probni fajlovi ostaju van Git izmena i van produkcionog ulaza aplikacije.
