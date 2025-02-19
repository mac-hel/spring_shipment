# README

## Uruchomienie
użyj skryptów w `composer.json`, ex:
```
composer test
composer run
```

## Dodatkowe info
- wiele klas w pojedynczym pliku - autoloading nie jest używany, nie ma konieczności zachowania zasady single-class-per-file
- ErrorLevel=1 jest traktowany tak samo jak ErrorLevel=10 (fatalny). 1 zwraca etykietę "tymczasową", ale nie jestem pewien czym ona jest, uznałem że lepiej wymusić pobranie "normalnej"
- hard-copy kodów krajów nie jest najlepszym rozwiązaniem walidacji, ale powinno wystarczyć do tego projektu
