# BuilderLog React PWA

Ovaj frontend je običan izvorni folder glavnog Laravel repozitorijuma. Produkcioni build se generiše u `../public/app/` i servira na `/app/` kroz Laravel deployment.

```sh
npm ci
npm run dev
```

Razvojni prikaz: `http://localhost:5173/app/`. Podrazumevani `/api/*` proxy očekuje Laravel na `http://127.0.0.1:8000`. Opcione promenljive su dokumentovane u `.env.example`; sopstvene vrednosti čuvati u `.env.local`.

```sh
npm run build
npm run verify:build
npm test
```

Iz Laravel korena iste komande mogu da se pokrenu kao `npm --prefix BuilderLogPWA ci` i `npm --prefix BuilderLogPWA run build`. Nije potreban drugi checkout. Samo Laravel `public/app/` je build izlaz. Nema service workera, offline caching-a, Firebase-a ili novog URL rutera.

Originalni sibling repozitorijum `../BuilderLogPWA/` u odnosu na Laravel koren ostaje dostupan za zaseban frontend razvoj. Ovaj folder predstavlja nezavisnu kopiju izvora, bez sopstvenog `.git` direktorijuma. Sinhronizacija nije automatizovana.

Detaljno uputstvo, Laravel ruta i rezultati provera: [PWA-DEPLOYMENT.md](../PWA-DEPLOYMENT.md).
