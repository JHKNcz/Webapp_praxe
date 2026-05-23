dosáhnout generování hodnot pro JEDNU položku s BASIC FE
    -Definovat 1 aktivum (název, startovní cena, interval aktualizace).
    -Vytvořit jednoduchý generátor ceny + ukládání posledních N hodnot.
    -Zobrazit název aktiva, aktuální cenu a historii (graf nebo seznam).
    -Přidat základní akce „Koupit“ a „Prodat“ s validací hotovosti.
    -Zobrazit hotovost a hodnotu portfolia (cash + aktivum).

udělat tomu tomu lepší FE
    -Upravit layout do panelů (Cena, Portfolio, Akce) + responsivní zobrazení.
    -Přidat tooltipy / vysvětlivky k hlavním prvkům UI.
    -Vizuálně odlišit růst/pokles ceny (barvy, šipky, trend indikátor).
    -Vylepšit graf (osa času, marker aktuální hodnoty, N posledních bodů).
    -Přidat zpětnou vazbu na akce (toast „Nákup OK“, „Nedostatek hotovosti“).

rozšířit to na více položek
    -Přidat seznam aktiv (min. 3) s nezávislými generátory cen.
    -Umožnit výběr aktiva + detailní panel s grafem a akcemi.
    -Rozšířit portfolio o přehled všech držených aktiv a jejich hodnot.
    -Zajistit pravidelný update cen pro všechna aktiva (polling/stream).
    -Připravit základ leaderboardu (zápis výsledku po session, UI později).