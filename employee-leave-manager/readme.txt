=== Menaxhimi i Pushimeve të Punonjësve ===
Contributors: komunaprishtine
Tags: pushime, punonjës, burime njerëzore, kalendar, raporte pdf
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sistem për administrimin e kërkesave për pushim, ditëve të pushimit vjetor, miratimeve, afateve, kalendarit dhe raporteve zyrtare PDF, i përshtatur me terminologjinë e Rregullores (QRK) Nr. 04/2024.

== Përshkrimi ==

Menaxhimi i Pushimeve të Punonjësve është shtojcë për WordPress për paraqitjen, shqyrtimin dhe administrimin e kërkesave për pushim vjetor dhe mjekësor, me funksione ndihmëse për gjendjen vjetore, raportet dhe auditimin.

Shtojca mundëson:

* paraqitjen e kërkesave për pushim nga punonjësit;
* shqyrtimin, miratimin dhe refuzimin e kërkesave nga mbikëqyrësit e drejtpërdrejtë;
* administrimin e numrit vjetor të ditëve të pushimit, ditëve shtesë dhe pushimit mjekësor;
* kufizimin e punonjësve që shfaqen për secilin mbikëqyrës në portal;
* ruajtjen e pozitës dhe sektorit/njësisë së punonjësit;
* administrimin e kalendarit të punës, festave dhe parametrave operacionalë të institucionit;
* krijimin e raporteve zyrtare PDF në format A4;
* regjistrin e auditimit për veprimet kryesore;
* portalin për punonjësit dhe mbikëqyrësit;
* skedën informative **Rregullat e pushimit** për të dy rolet, me afatet dhe të drejtat kryesore të Rregullores (QRK) Nr. 04/2024;
* qasjen e plotë të administratorëve në panelin e WordPress-it.

Ndërfaqja përdor terminologji të standardizuar në gjuhën shqipe për platformat softuerike dhe administrimin e burimeve njerëzore. Skeda Rregullat e pushimit është informative; të drejtat që varen nga të dhëna ose procedura jashtë portalit verifikohen nga Burimet Njerëzore dhe, sipas nevojës, nga njësia juridike.

== Instalimi ==

1. Ngarkojeni arkivin ZIP nga **Shtojcat > Shto të re > Ngarko shtojcë**.
2. Aktivizojeni shtojcën.
3. Krijoni një faqe për portalin dhe vendosni njërin nga kodet e shkurtra:

`[elm_leave_portal]`

ose

`[employee_leave_manager]`

4. Caktoni rolet dhe lejet e nevojshme për punonjësit dhe mbikëqyrësit.
5. Te **Menaxhimi i Pushimeve > Qasja e mbikëqyrësve në portal** caktoni për secilin mbikëqyrës vetëm punonjësit që ai/ajo mbikëqyr drejtpërdrejt. Pa caktim, mbikëqyrësi nuk sheh kërkesat e punonjësve të tjerë.
6. Plotësoni pozitën dhe sektorin/njësinë në profilin e secilit punonjës.

== Përdorimi ==

Punonjësi mund të shohë gjithsej ditët e pushimit dhe gjendjen e tyre, kalendarin, kërkesat e veta, të paraqesë kërkesë të re dhe të konsultojë skedën Rregullat e pushimit.

Mbikëqyrësi mund të shqyrtojë kërkesat e punonjësve që i janë caktuar, të marrë vendime brenda afateve të përcaktuara dhe, kur paraqet kërkesë personale, ta miratojë ose refuzojë atë nga skeda Pushimi im sipas të drejtave të rolit; gjithashtu mund të verifikojë kërkesat +1 ditë për përvojë pune, të krijojë raporte dhe të konsultojë skedën Rregullat e pushimit.

Administratori i WordPress-it ruan qasje të plotë në panelin e administrimit të shtojcës. Qasja vetëm përmes portalit është opsionale dhe e çaktivizuar si parazgjedhje.

== Siguria dhe privatësia ==

Veprimet kontrollohen në server përmes lejeve të posaçme ELM, verifikimit të nonce-it të sigurisë, pastrimit të të dhënave dhe kontrollit të pronësisë ose caktimit të punonjësit. Leja e përgjithshme WordPress `read` dhe roli i integruar Subscriber nuk përdoren si autorizim për portalin e pushimeve.

Dokumentet mjekësore dhe regjistri i auditimit përdorin çelësa afatgjatë të posaçëm për ELM, të ndarë nga çelësat/salts e sesioneve të WordPress-it. Gjatë përditësimit nga versionet e vjetra ruhet në mënyrë të kontrolluar çelësi i vjetër vetëm për të vazhduar dekriptimin dhe verifikimin e të dhënave ekzistuese. Për instalime me menaxhim të jashtëm të sekreteve mund të përcaktohen konstantet `ELM_ENCRYPTION_KEY` dhe `ELM_AUDIT_KEY` para përdorimit të parë të versionit të ri. Këto konstante nuk duhet të ndryshohen pa procedurë migrimi të të dhënave.

Kërkesa e miratuar nuk mund të fshihet fizikisht. Ajo duhet të anulohet fillimisht, në mënyrë që kthimi i ditëve dhe historiku i vendimit të ruhen në regjistrin e auditimit. E drejta e mbikëqyrësit për vendim mbi kërkesën e vet mbetet sipas konfigurimit aktual të këtij sistemi.

Shtojca ruan të dhëna për kërkesat për pushim, numrin e ditëve të pushimit, pozitën, sektorin/njësinë, dokumentet mjekësore dhe regjistrin e auditimit. Organizata duhet ta përdorë shtojcën në përputhje me politikat e veta të privatësisë dhe ruajtjes së të dhënave.

== Ndryshimet ==

= 1.5.93 =

* U hoq përdorimi i lejes së përgjithshme WordPress `read` si autorizim për ELM dhe u hoqën lejet ELM nga roli i integruar Subscriber. Përdoruesit me të dhëna ekzistuese ELM migrohen te roli i posaçëm `elm_employee` kur është e nevojshme.
* U mbyllën boshllëqet e kontrollit të objektit te shkarkimi i dokumenteve mjekësore dhe eksporti PDF i punonjësit. Vetëm administratori, vetë pronari kur ka lejen përkatëse, ose mbikëqyrësi i caktuar mund të hyjë në objektin përkatës.
* Leximi i historikut të rregullimeve të ditëve përmes REST tani kërkon `elm_adjust_balances` ose administrator.
* Kërkesat e miratuara nuk mund të fshihen fizikisht. Për kthim të ditëve përdoret anulimi, i cili ruan gjurmën e vendimit.
* Dokumentet e reja mjekësore enkriptohen me çelës të posaçëm ELM. Dokumentet e vjetra mbeten të dekriptueshme edhe pas rotacionit të salts të WordPress-it.
* HMAC-i i auditimit përdor çelës të posaçëm ELM për regjistrimet e reja dhe ruan çelësin historik për verifikimin e regjistrimeve të mëparshme.
* Shkrimi në regjistrin e auditimit serializohet me bllokim të bazës së të dhënave për të shmangur degëzimin e zinxhirit gjatë kërkesave konkurruese.
* Punonjësve të zakonshëm nuk u ekspozohen më numrat organizativë të kërkesave në pritje ose të miratuara sipas datës. Ruhet vetëm informacioni i nevojshëm nëse data është e disponueshme ose jo.
* Paketa e prodhimit nuk përfshin dokumentacionin e brendshëm të zhvillimit dhe manifestin opsional Composer. Gjeneruesi i integruar PDF vazhdon të funksionojë pa varësi të jashtme.

= 1.5.77 =

* U standardizuan gjendjet informative të listave dhe tabelave në portal dhe në panelin administrativ: **Po ngarkohet...**, **Nuk u gjet asnjë...**, gjendja pa rezultate nga filtrat dhe mesazhet udhëzuese tani paraqiten në mënyrë të njëjtë.
* Çdo kartë që përmban të dhëna dinamike shfaq një gjendje të qartë kur nuk ka rezultate, në vend që të mbetet bosh.
* Mesazhet për kërkesat, kërkesat +1 ditë, punonjësit dhe ditët shtesë u harmonizuan me të njëjtën formë gjuhësore.
* U shtua stil i përbashkët vizual për gjendjet **ngarkim**, **pa të dhëna**, **informacion** dhe **gabim**, pa ndryshuar logjikën e të dhënave.

= 1.5.76 =

* U korrigjua plotësisht paraqitja e seksionit **Kërkesat për +1 ditë për përvojë pune**: karta nis e fshehur me `hidden` dhe me një klasë mbrojtëse CSS, dhe shfaqet vetëm kur ka kërkesa në pritje ose kur mbikëqyrësi e ka aktivizuar shfaqjen manuale.
* Cilësimi i vjetër me checkbox të madh u zëvendësua me një **toggle** kompakt standard në **Cilësimet → Kërkesat +1 ditë**.
* Toggle ruhet menjëherë sapo ndryshohet; nuk kërkon më klikim te **Ruaj cilësimet** dhe e përditëson menjëherë kartën +1 ditë.
* Ruajtja e këtij toggle-i përdor një veprim të veçantë në server dhe ndryshon vetëm këtë preferencë, pa rishkruar parametrat e tjerë të cilësimeve.
* Të gjithë përdoruesit me të drejtën `elm_manage_leave` mund ta përdorin toggle-in dhe të shqyrtojnë kërkesat +1 ditë; punonjësit e zakonshëm nuk kanë qasje.
* Kur shfaqja manuale është aktive dhe nuk ka kërkesa në pritje, pjesa bosh **Në pritje** fshihet dhe mbetet vetëm historiku.
* U shtua statusi i qartë **Duke ruajtur / U ruajt / gabim** pranë toggle-it.

= 1.5.75 =

* Seksioni **Kërkesat për +1 ditë për përvojë pune** te **Kërkesat për shqyrtim** tani është i fshehur si parazgjedhje dhe shfaqet automatikisht vetëm kur ka kërkesa të reja në pritje për miratim.
* Pas miratimit ose refuzimit të kërkesës së fundit në pritje, seksioni hiqet menjëherë nga pamja për të kursyer hapësirë dhe për ta mbajtur portalin të pastër.
* U hoq butoni i dyfishtë **Rifresko** nga seksioni +1 ditë; rifreskimi kryesor i skedës **Kërkesat për shqyrtim** rifreskon edhe këto kërkesa.
* Te **Cilësimet** u shtua opsioni **Shfaq seksionin +1 ditë edhe kur nuk ka kërkesa në pritje** për rastet kur udhëheqësi dëshiron të shohë historikun ose ta mbajë pjesën gjithmonë të dukshme.
* Ky opsion menaxhohet vetëm nga përdoruesit me të drejtë për cilësimet e udhëheqësit/administrimit; punonjësit nuk kanë qasje në të.

= 1.5.74 =

* U rikthye e drejta e mbikëqyrësit të drejtpërdrejtë për të miratuar ose refuzuar kërkesën e vet personale nga skeda **Pushimi im**, njësoj si në rrjedhën e mëparshme të portalit.
* Butonat **Mirato** dhe **Refuzo** shfaqen vetëm për kërkesën personale në pritje dhe vetëm kur përdoruesi ka të drejtën `elm_manage_leave`.
* Serveri verifikon që kërkesa është e vetë përdoruesit dhe që përdoruesi ka rol menaxhues; vendimi regjistrohet normalisht në regjistrin e auditimit.
* Kufizimi i caktimit të punonjësve mbetet i pandryshuar për kërkesat e personave të tjerë.
* Afatet 15/5 ditë, rregullat e pushimit vjetor, kontrollet e kapacitetit dhe terminologjia e versionit 1.5.73 mbeten në fuqi.

= 1.5.73 =

* Terminologjia, përshkrimet dhe afatet u harmonizuan me Rregulloren (QRK) Nr. 04/2024 për Orarin e Punës, Pushimet dhe Vijushmërinë e Zyrtarëve Publik.
* Pushimi vjetor bazë u korrigjua në 20 ditë pune; u hoq kufiri artificial janar-qershor dhe rrjedha e vjetër e "miratimit të veçantë".
* Afati 15-ditor për pushimin vjetor shfaqet si vërejtje në kalendar dhe në kërkesë; datat mund të përzgjidhen, ndërsa vendimi i miratimit duhet të jetë së paku 5 ditë para fillimit.
* Pushimi mjekësor nuk bllokohet nga kapaciteti ditor i pushimit vjetor; kur pushimi mjekësor i miratuar përputhet me pushimin vjetor, ditët përkatëse nuk zbriten nga gjendja e pushimit vjetor.
* U ndalua vetëmiratimi dhe vetërefuzimi i kërkesave personale; vendimi duhet të merret nga mbikëqyrësi i drejtpërdrejtë i autorizuar për punonjësin.
* Mbikëqyrësit jo-administratorë nuk marrin më qasje të parazgjedhur te të gjithë punonjësit; shfaqen vetëm punonjësit e caktuar shprehimisht, ndërsa pa caktim mbikëqyrësi sheh vetëm pushimin e vet.
* U shtua rikujtimi që pas vendimit të njoftohet Njësia për Menaxhimin e Burimeve Njerëzore dhe vendimi të evidentohet në dosjen individuale.
* U shtua skeda informative **Rregullat e pushimit** për punonjësit dhe mbikëqyrësit, me përmbledhje të neneve 4 dhe 8-15 dhe lidhje me Rregulloren, Ligjin për Zyrtarët Publik dhe Ligjin për Festat Zyrtare.
* U përfshinë në përmbledhje edhe: së paku 30 ditë për punët me ndikime të dëmshme, +1 ditë për çdo 5 vjet përvojë, +2 ditë për kategoritë e përcaktuara, rregulli i 10 ditëve të pandërprera, afati 30 qershor, kushtet e punësimit të parë dhe bartja ndërmjet institucioneve.
* Rrjedha +1 ditë u kufizua qartë në një ditë për kërkesë dhe kërkon verifikim të pragut të përvojës së punës nga mbikëqyrësi/BNJ.
* Cilësimet u thjeshtuan duke mbajtur parametrat operacionalë veç nga të drejtat ligjore; statuset dhe sqarimet e vjetra të përjashtimit u hoqën nga ndërfaqja.

= 1.5.72 =

* U thjeshtua plotësisht kërkesa për ditë shtesë: punonjësi kërkon vetëm +1 ditë dhe plotëson vetëm arsyetimin.
* Kërkesat për +1 ditë u zhvendosën te skeda “Kërkesat për shqyrtim”, ku udhëheqësi i miraton ose i refuzon drejtpërdrejt.
* U hoqën nga Cilësimet formulari i ndërlikuar për ndryshimin e numrit vjetor të ditëve, përzgjedhja e punonjësit, viti, numri i ri, cilësimi i qasjes dhe seksioni i dyfishtë “Ditët shtesë”.
* Kërkesat në pritje shfaqen veçmas; historiku është i palosur për të kursyer hapësirë.
* Lejohet vetëm një kërkesë +1 në pritje njëkohësisht. Pas miratimit, refuzimit ose anulimit, punonjësi mund të paraqesë një kërkesë tjetër +1 kur nevojitet.
* Logjika e punonjësit tani e kufizon kërkesën vetëshërbyese në saktësisht +1 ditë, ndërsa miratimi mbetet te udhëheqësi i autorizuar.

= 1.5.71 =

* Sqarimet e kalendarit tani janë të palosura si parazgjedhje për të kursyer hapësirë.
* U shtua një kontroll kompakt “Shfaq/Fshih” me paraqitje të përshtatur për desktop dhe celular.
* Seksioni hapet automatikisht kur përzgjedhja e datave gjeneron një paralajmërim të rregullave.

= 1.5.70 =

* U hoqën titujt e dyfishtë në portal dhe te cilësimet; çdo seksion tani ka një titull të vetëm dhe përshkrim të shkurtër.
* Termi "kuotë" u zëvendësua në ndërfaqe me formulime të qarta si "Gjithsej ditë pushimi", "Ditët e pushimit vjetor" dhe "Numri bazë i ditëve të pushimit vjetor".
* U standardizuan emërtimet e seksioneve: Kalendari i punës, Ditët shtesë, Qasja vetëm përmes portalit dhe Dokumentet.
* U korrigjua ngarkimi fillestar i ditëve shtesë, në mënyrë që të mos shfaqet gabim para zgjedhjes së punonjësit.
* Fushat për ngarkimin e skedarëve tani shfaqin tekst shqip për zgjedhjen dhe gjendjen e skedarit.
* Identifikuesit teknikë, struktura e bazës së të dhënave dhe logjika e llogaritjeve mbetën të pandryshuara.

= 1.5.69 =

* U rishikua dhe u përshtat profesionalisht terminologjia shqipe në portal, panelin administrativ, njoftimet, gabimet dhe dialogët dinamikë.
* U standardizuan termat Kërkesë për pushim, Numri vjetor i ditëve të pushimit, Ditët shtesë, Arsyetimi dhe Regjistri i auditimit.
* Teksti i formularit PDF u harmonizua me terminologjinë zyrtare të kërkesave për pushim dhe u korrigjuan formulimet gjuhësore.
* Identifikuesit teknikë, vlerat e statusit, rrugët REST, struktura e bazës së të dhënave dhe logjika e llogaritjeve mbetën të pandryshuara.


= 1.5.68 =

* U aplikua skema e kërkuar e ngjyrave: Redakto blu, Mirato gjelbër, Refuzo kuq, Anulo gri dhe Fshi gri me tekst të kuq.
* Udhëheqësi tani mund t'i miratojë ose refuzojë kërkesat e veta në pritje drejtpërdrejt te skeda Pushimi im.
* Te Pushimi im shfaqet grupi i plotë Redakto, Mirato, Refuzo, Anulo, Fshi dhe PDF sipas statusit dhe të drejtave.
* Vendimi personal verifikohet në server, lejohet vetëm për kërkesën e vet dhe regjistrohet në auditim me udhëheqësin si vendimmarrës.
* Kërkesat e punonjësve te Kërkesat për shqyrtim vazhdojnë të përdorin të njëjtat kontrolle të caktimit dhe të drejtave.

= 1.5.67 =

* Te skeda Pushimi im u shtuan butonat e plotë dhe të stilizuar Redakto, Anulo, Fshi dhe PDF, sipas statusit dhe të drejtave ekzistuese.
* Udhëheqësi mund ta fshijë përgjithmonë vetëm kërkesën e vet nga Pushimi im, me konfirmimin e detyrueshëm DELETE dhe kontroll në server të pronësisë.
* Mirato dhe Refuzo nuk shfaqen për kërkesat personale, për të ruajtur ndalimin e vetëmiratimit dhe vetërefuzimit.
* Butoni Fshi në hapësirën e udhëheqësit tani paraqitet si buton normal me kufi dhe sfond të kuq të zbehtë, jo si tekst i thjeshtë.
* Paneli administrativ, rrjedha e miratimit, llogaritjet dhe raportet PDF mbeten të pandryshuara.

= 1.5.66 =

* U korrigjuan emërtimet e zbrazëta të butonave Mirato dhe Refuzo në portalin e udhëheqësit, me emërtime rezervë të sigurta në shqip.
* U forcua stili dhe dukshmëria e butonave Redakto, Mirato, Refuzo, Anulo, Fshi dhe PDF ndaj stileve të temës së faqes.
* Veprimet e butonave tani trajtojnë gabimet e papritura dhe i paraqesin qartë në njoftimin e portalit.
* Kërkesat personale të udhëheqësit nuk shfaqen më te Kërkesat për shqyrtim; ato vazhdojnë të menaxhohen te Pushimi im.
* U shtua kontroll në server që ndalon vetëmiratimin, vetërefuzimin ose menaxhimin e kërkesës personale nga hapësira e udhëheqësit.
* Paneli administrativ dhe rrjedha ekzistuese e administrimit mbeten të pandryshuara.

= 1.5.65 =

* Veprimet në tabelën e kërkesave të udhëheqësit shfaqen me butonat e plotë të panelit administrativ: Redakto, Mirato, Refuzo, Anulo, Fshi dhe PDF, sipas statusit dhe të drejtave ekzistuese.
* Seksioni Punonjësit dhe raportet u zhvendos nga skeda e veçantë në skedën Cilësimet të portalit të udhëheqësit.
* Te Menaxhimi i Pushimeve > Raportet PDF u shtua regjistri Punonjësit dhe raportet për pozitën, sektorin/njësinë, gjendjen vjetore dhe krijimin e raportit A4.
* Redaktimi i pozitës dhe sektorit nga faqja Raportet PDF mbrohet me të drejtat dhe nonce-in REST të WordPress-it.
* Nuk u ndryshuan llogaritjet, statuset, rrjedha e miratimit, caktimet e punonjësve ose faqosja e raporteve PDF.

= 1.5.64 =

* U përkthye profesionalisht në gjuhën shqipe ndërfaqja e portalit, administrimit, mesazhet e validimit dhe dialogët dinamikë.
* U standardizua terminologjia për punonjësit, udhëheqësit, kërkesat për pushim, gjendjet, ditët vjetore të pushimit dhe regjistrin e auditimit.
* Datat dhe emërtimet e muajve në ndërfaqen dinamike përdorin lokalizimin `sq-XK`.
* Ndarësit e gjatë `—` dhe `–` në tekstet e shfaqura u zëvendësuan me vizën normale `-`.
* Identifikuesit teknikë, statuset e ruajtura, rrugët REST, veprimet, të drejtat dhe kodet e shkurtra mbetën të pandryshuara.
* Nuk u ndryshuan llogaritjet, struktura e bazës së të dhënave, rrjedha e miratimit ose faqosja e raporteve PDF.

== Pyetje të shpeshta ==

= A mbetet i disponueshëm administrimi i WordPress-it? =

Po. Faqet ekzistuese të administrimit mbeten të disponueshme. Administratori i WordPress-it ruan qasje të plotë.

= A mund të shohë udhëheqësi vetëm punonjësit e caktuar? =

Po. Administratori mund të caktojë veçmas punonjësit që shfaqen për secilin udhëheqës në portal.

= A ndryshon përkthimi të dhënat ekzistuese? =

Jo. Përkthimi ndryshon vetëm tekstet që shfaqen. Vlerat teknike dhe të dhënat e ruajtura mbeten të pandryshuara.



== Changelog ==

= 1.6.1 =
* Ndërfaqja e portalit dhe e panelit administrativ u rindërtua mbi një sistem të vetëm dizajni me variabla CSS (ngjyrat, hapësirat, rrezet, hijet, tipografia dhe lëvizja përcaktohen në një vend të vetëm).
* Fletët e stilit `portal.css` dhe `admin.css` u rishkruan nga e para dhe u organizuan në seksione të dokumentuara; rregullat e vjetruara dhe të papërdorura u hoqën.
* U eliminuan pothuajse të gjitha deklaratat `!important` (mbeten vetëm aty ku janë strukturalisht të domosdoshme: fshehja me atributin `hidden`, printimi dhe `prefers-reduced-motion`).
* U përmirësua qasshmëria: unaza fokusi të dukshme dhe të njëtrajtshme (`:focus-visible`), mbështetje për `prefers-reduced-motion` dhe për modalitetin me kontrast të lartë (`forced-colors`).
* U përmirësua paraqitja në ekrane të vogla: kartelat e gjendjes, kalendari, tabelat, dritaret modale dhe skedat përshtaten më mirë; teksti i kokës nuk mbivendoset më me sfondin.
* U shtuan stile printimi për portalin.
* Stilet e paraqitjes u hoqën nga kodi PHP (atributet `style`) dhe u zhvendosën në fletët e stilit.
* Nuk ka ndryshime në logjikën e biznesit, në bazën e të dhënave, në REST API, në lejet ose në formularët PDF.

= 1.6.0 =
* U shtuan njoftimet automatike me email për punonjësit dhe udhëheqësit kur paraqitet, miratohet, refuzohet ose anulohet një kërkesë për pushim ose një kërkesë për +1 ditë. Mund të çaktivizohen ose të përfshijnë edhe email-in e administratorit te Cilësimet.
* U shtua eksportimi CSV i kërkesave (buton "Eksporto CSV" te Menaxhimi i Pushimeve > Kërkesat), me të njëjtat kufizime qasjeje si tabela e kërkesave.
* U shtua stil vizual i ri për gjendjen "Të mbetura" në portal (unazë përparimi) dhe stile gjendjesh ngarkimi më të qarta.
* U korrigjua një rrezik konkurrence te kërkesat për +1 ditë: dy paraqitje njëkohshme për të njëjtin punonjës dhe vit nuk mund të krijojnë më dy kërkesa në pritje.
* U korrigjua rendi i veprimeve kur hidhet një dokument mjekësor i pashoqëruar, në mënyrë që një gabim gjatë regjistrimit të auditimit të mos lërë një regjistrim të bazës së të dhënave pa skedarin përkatës.
* Shkarkimi i dokumentit mjekësor tani ndalet me gabim të qartë nëse regjistrimi i tij në auditim dështon, në vend që të vazhdojë pa gjurmë auditimi.
* U dalluan emërtimet e roleve "Mbikëqyrës i drejtpërdrejtë (bazë)" dhe "Mbikëqyrës i drejtpërdrejtë (i plotë)" në listën e roleve të WordPress-it.
* U shtua indeks në bazën e të dhënave për kërkimet sipas dokumentit mjekësor, për performancë më të mirë me shumë kërkesa.
* Skriptet dhe stilet e panelit administrativ ngarkohen tani vetëm në faqet e sakta të shtojcës, në vend të një përputhjeje të përafërt të emrit të faqes.

= 1.5.98 =
* Harmonizon terminologjinë e skedës dhe dritares **Historiku** me termat ekzistues të portalit.
* Përdor njësoj **Të mbetura**, **Paraqitur më**, **Periudha e zgjedhur**, **Datat e zgjedhura**, **Statusi** dhe **Arsyetimi**.
* Gjendjet e kërkesave në përmbledhje përdorin të njëjtat etiketa **Në pritje**, **Miratuar**, **Refuzuar** dhe **Anuluar** si në pjesët e tjera të portalit.

= 1.5.97 =
* Adds the manager "Historiku" tab with employee, status, and year filters.
* Adds yearly entitlement/used/pending/available summaries plus detailed leave request history.
* Adds a "Historiku" action beside manager request actions that opens the same employee report in a modal.

= 1.5.96 =
* Ruajtur paraqitja kompakte e vitit në krye të portalit dhe shtuar kontrolli standard me shigjeta lart/poshtë.
* Kufizuar përzgjedhja e vitit të kalendarit nga 2024 deri në 2060.


= 1.5.95 =
* Datat brenda afatit 15-ditor shënohen vetëm me ! pa rrethin portokalli rreth datës.
* Viti në krye të portalit është zgjedhës aktiv dhe lejon kalimin e kalendarit në një vit tjetër.
* Ndryshimi i vitit rifreskon kalendarin dhe gjendjen vjetore për vitin e zgjedhur dhe pastron përzgjedhjen e datave të vitit të mëparshëm.

= 1.5.94 =
* Datat brenda afatit 15-ditor të pushimit vjetor mund të përzgjidhen dhe shënohen me ! si vërejtje.
* Datat e zgjedhura mbi pragun e 10 ditëve në periudhën janar-qershor shënohen individualisht me ! në kalendar.
* Vërejtjet për afatin 15-ditor dhe pragun 10-ditor ruhen me kërkesën dhe i shfaqen mbikëqyrësit gjatë shqyrtimit.

= 1.5.91 =
* Emri i skedarit PDF të kërkesës sugjerohet gjithmonë me shkronja të vogla.
* Shembull: guxim.krasniqi_pushim-vjetor_miratuar_07-08-2026_16-11-20.pdf.

= 1.5.90 =
* Standardizon emërtimin e llojit të pushimit në ndërfaqe: “Pushim vjetor” dhe “Pushim mjekësor”.
* Emrat dinamikë të PDF-ve përdorin “Pushim-vjetor” / “Pushim-mjekesor” për terminologji të plotë.

= 1.5.88 =
* PDF-ja e secilës kërkesë merr automatikisht një emër përshkrues nga punonjësi, lloji i pushimit, statusi dhe data/ora e paraqitjes.
* Formati i emrit është p.sh. guxim.krasniqi_Vjetor_Miratuar_07-08-2026_16-11-20.pdf.
* Emërtimi zbatohet njësoj nga Pushimi im dhe Kërkesat për shqyrtim, për të gjitha statuset.

= 1.5.87 =
* Nis drejtpërdrejt nga baza 1.5.83 dhe korrigjon vetëm veprimet te skeda Kërkesat për shqyrtim.
* Përdor të njëjtën pamje të butonave si Pushimi im: Redakto, Mirato, Refuzo, Anulo, Fshi dhe PDF sipas statusit.
* PDF shfaqet për çdo status të kërkesës dhe URL-ja gjenerohet edhe nga serveri, që të mos varet vetëm nga konfigurimi JavaScript.
* Mbikëqyrësi mund të hapë PDF-në e kërkesave të punonjësve të caktuar nën mbikëqyrjen e tij.
* Kolona Veprimet është zgjeruar dhe nuk e pret butonin PDF në fund.

= 1.5.83 =
* Standardizon emërtimet e ditëve të javës në kalendar në shqip: HËN, MAR, MËR, ENJ, PRE, SHT, DIE.
* Përdor emrat shqip të muajve në kalendar, pa u varur nga mbështetja e lokalizimit të shfletuesit ose e WordPress-it.
* Zbaton të njëjtat etiketa në portalin e punonjësit, portalin e mbikëqyrësit dhe kalendarin administrativ.


= 1.5.82 =
* Festat me datë fikse përsëriten automatikisht çdo vit dhe nuk kërkojnë më shtim manual sipas vitit.
* Bajrami i Madh dhe Bajrami i Vogël menaxhohen me dy fusha date, me zgjedhje të drejtpërdrejtë nga kalendari.
* Datat e Bajrameve ruhen sipas vitit; ndryshimi i një viti të ri nuk i fshin datat e viteve të mëparshme.
* U hoq nevoja për tekstin/CSV-në e festave nga ndërfaqja e cilësimeve.

= 1.5.81 =
* Rikthen dhe forcon dukshmërinë e butonave të menaxhimit në portalin e mbikëqyrësit: Redakto, Mirato, Refuzo, Anulo, Fshi dhe PDF sipas statusit të kërkesës.
* Mbikëqyrësi me `elm_manage_leave` mund t'i përdorë këto veprime si për kërkesat e punonjësve të caktuar nën mbikëqyrje, ashtu edhe për kërkesat e veta kur veprimi është i zbatueshëm.
* Butonat Mirato/Refuzo për kërkesën personale të mbikëqyrësit renderohen edhe nga PHP, kështu që nuk varen vetëm nga rifreskimi JavaScript.
* Veprimi Fshi tani lejohet për mbikëqyrësin vetëm për kërkesën e vet ose të punonjësve të caktuar nën mbikëqyrjen e tij; kontrollet e serverit mbeten aktive.
* Kolona Veprimet mbahet e dukshme në tabela të gjera në desktop dhe butonat kanë stile mbrojtëse ndaj temave që mund t'i fshehin.

= 1.5.80 =
* Lejon përzgjedhjen e fundjavave dhe festave si pjesë e intervalit të kërkesës pa i llogaritur si ditë pushimi.
* Shton paralajmërim informues kur përdorimi janar-qershor kalon pragun 10-ditor; kërkesa nuk bllokohet.
* E ruan paralajmërimin me kërkesën dhe ia shfaq mbikëqyrësit në listën e shqyrtimit dhe para miratimit.


= 1.5.79 =
* Added a complete front-end login card when the portal is viewed while logged out.
* Logout now returns to the configured portal page so users can immediately sign in again.
* Added Albanian login labels and a password-recovery link while keeping WordPress authentication.


= 1.5.78 =
* Added a portable front-end brand/profile header for both employees and direct supervisors.
* Added an Albanian profile/authentication badge and WordPress logout action.
* Added a portal hero using the existing Employee Leave Manager terminology.
* Bundled the portal hero artwork inside the plugin and reused the municipality crest already shipped with the plugin; no site-specific image URL is required.
