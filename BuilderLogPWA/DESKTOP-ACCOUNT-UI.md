# Login, korisnički meni i podešavanja — 2. novembar 2026.

Ova iteracija dodaje tri tražena desktop poboljšanja, bez novog dizajna aplikacije ili novih zavisnosti.

## Promene

- Login forma je horizontalno centrirana i ograničena na **480 px**, samo od postojećeg desktop praga **1024 px**. Visine polja, dugmad i login komponenta nisu menjani.
- `DesktopUserMenu`, u postojećem zaglavlju `DesktopShell`, otvara se klikom na ime korisnika. Sadrži tačno dve lokalizovane akcije: **Podešavanja** i **Odjavi se**. Zatvara se ponovnim klikom, klikom van njega, izlaskom fokusa i tasterom Escape. Strelice, Home i End pomeraju fokus; Escape vraća fokus na okidač.
- Akcija Podešavanja koristi postojeći `SettingsActivity` i stanje `settingsOpen`. Aplikacija nema URL ruter; nisu dodati ni menjani URL-ovi.
- Odjava koristi isti postojeći sled operacija, izdvojen u zajednički `logoutFromSettings`: `LoginCredentials.clear(preferences)`, uklanjanje `user_role`, pa postojeći `logout()` iz `RadulovicApplication`. Mobilna potvrda odjave ostaje ista.
- Podešavanja administratora i poslovođe nalaze se u istom montiranom `DesktopShell` okviru kao njihov prethodni ekran. Prethodni sadržaj je skriven, a ne demontiran. Povratak tako čuva postojeće lokalno stanje ekrana.
- Za ostale početne/radne ekrane, podešavanja koriste isti `DesktopShell` sa postojećom povratnom destinacijom Početna, bez izmišljanja dodatnih sekcija.
- Sadržaj podešavanja ima najviše **1000 px** i desktop raspored polja u dve kolone. Navigacija novog okvira je onemogućena tokom postojećeg čuvanja.
- Ispod 1024 px sidebar i desktop zaglavlje su skriveni, a postojeća mobilna forma i mobilni pristup podešavanjima ostaju dostupni.

## Fajlovi izmenjeni u ovoj iteraciji

- `src/com/s2d2/radulovic/RadulovicApplication.jsx`
- `src/com/s2d2/radulovic/screens/admin/AdminHomeActivity.jsx`
- `src/com/s2d2/radulovic/screens/manager/ManagerHomeActivity.jsx`
- `src/com/s2d2/radulovic/screens/general/SettingsActivity.jsx`
- `src/com/s2d2/radulovic/ui/components/DesktopShell.jsx`
- `src/com/s2d2/radulovic/ui/components/DesktopUserMenu.jsx` — nov fajl
- `src/com/s2d2/radulovic/ui/theme/Desktop.css`
- `DESKTOP-ACCOUNT-UI.md` — ovaj izveštaj

Nisu menjani `LoginActivity.jsx`, API/service sloj, autentifikacioni zahtevi, token servis, validacija, payload čuvanja podešavanja, Firebase, Laravel niti konfiguracija zavisnosti. Postojeći korisnički fajlovi `.idea/` i `utils/DateSorting.js` nisu menjani.

## Izvršene provere

Pregledačka provera koristi stvarni `RadulovicApplication` i stvarne login/settings komponente u ignorisanoj lokalnoj probnoj stranici `.local/account-review.html`. Korisnik i transport su probni; pravi backend nije pozivan. Postojeći `LoginCredentials` helper izvršavan je nad probnim podacima zasebnog localhost porekla.

- Login: proverene širine 390, 768, 1280, 1440 i 1920 px. Na desktopu forma je izmerena na 480 px i centrirana. Izvršena je probna prijava i na mobilnoj i na desktop širini.
- Meni: provereno otvaranje, ponovni klik, klik van menija, tastatura, dve stavke, zatvaranje pri odabiru i položaj unutar viewporta na 1024, 1280, 1440 i 1920 px. Vizuelno je potvrđeno da meni izlazi izvan zaglavlja preko sadržaja bez odsecanja.
- Podešavanja: provereni zajednički sidebar i zaglavlje za administratora i poslovođu, šest postojećih polja, povratak i simulirano čuvanje. Pretraga dokumenata ostaje sačuvana posle povratka i posle čuvanja.
- Tokom simuliranog čuvanja potvrđeno je da su navigacija, korisnički meni i postojeće dugme Sačuvaj onemogućeni.
- Podešavanja su proverena na 390, 768, 1024, 1280, 1440 i 1920 px bez horizontalnog prelivanja. Sidebar i desktop zaglavlje nisu vidljivi na mobilnim širinama.
- Na 390 i 768 px upoređeni su položaji, dimenzije, fontovi i prikaz 20 postojećih elemenata podešavanja sa originalnom komponentom iz Git verzije: rezultati su jednaki.
- Desktop odjava i postojeća mobilna potvrda odjave vraćaju aplikaciju na login; instrumentacija potvrđuje jedno čišćenje pristupnih podataka i jedno čišćenje sesije po odjavi.
- Statičko poređenje potvrđuje da su tela postojećih funkcija `save`, `logout`, `openSession` i `requireLogin` neizmenjena.
- `npm run build` prolazi. Ostaje upozorenje o veličini glavnog JS paketa preko 500 kB; nije uveden novi paket ili širi refaktoring.
- `git diff --check` prolazi. Konzola probne stranice nema zabeležene greške.
- `npm test` završava uspešno, ali projekat nema definisane testove: pronađeno je 0 testova.

Nisu testirani pravi server, produkcioni pristupni podaci ni radni tokovi vozača/bageriste. Njihova podešavanja koriste zajednički rezervni okvir, ali nisu zasebno potvrđena u pregledaču.

Slika probnog desktop prikaza sa otvorenim korisničkim menijem: `.local/account-settings-desktop.png`. Probni fajlovi nisu deo produkcionog ulaza ni Git izmena.
