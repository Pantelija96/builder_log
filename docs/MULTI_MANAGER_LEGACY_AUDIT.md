# M4 — popis upotrebe pre izmene

| Klasa | Nalaz |
|---|---|
| A — uklanjanje | `DailyLog::siteManager()`, fillable `site_manager_id`, `Worker::managedDailyLogs()`, singularni eager-load u `DailyLogService`, upis u `CreateDailyLogAction::createLog`, singularna polja u `DailyLogResource`. |
| B — članstvo | `DailyLogFilter` već koristi pivot za `site_manager_id` i ostaje takav. Fabrika i testovi prelaze sa singularnog vlasnika na povezane članove. `EnsuresWorkerCanManageDailyLog`, čitanje dodela i istorija koriste članstvo. |
| C — atribucija | `GetSiteManagerOverallAction`, `FinancialSummaryService` i `ConstructionFinancialSummaryService` grupišu troškove i evidenciju po poljima na zapisima. Agregati gradilišta polaze od zapisa, bez spajanja sa pivotom članova. |
| D — kompatibilnost | Istorijska migracija iz jula kreira kolonu i FK, a M1 migracija iz novembra kopira postojeći ID u pivot. Obe ostaju u redosledu migracija. Stari `POST /daily-logs` i dalje prihvata istorijska polja zahteva, iako se identitet uzima sa autentifikovanog radnika. Ulazni parametar liste je filter članstva. |
| E — legitimno singularno | Atribucija na Expense, WorkerAttendance, MachineAssignment, DeliveryNote, Note, SubcontractorLog, CashAdvance i dokumentima ostaje. `locked_by` je akter zaključavanja. `ConstructionSite::siteManagers()` je stalna dodela, odvojena od dnevnog članstva. |

Scheduler pristupa dnevnicima po datumu i koristi sistemskog aktera. Pre M4 u produkcionom kodu nema drugog čitanja singularne veze dnevnika. Direktne upotrebe kolone u istorijskim migracijama i njihovim testovima obrađuju se na staroj šemi.
