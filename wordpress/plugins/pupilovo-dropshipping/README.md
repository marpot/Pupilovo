# Pupilovo Dropshipping

To jest wyłącznie szkielet przyszłej integracji. Żaden dostawca ani fikcyjna synchronizacja nie są podłączone.

Adapter implementuje `Pupilovo_Dropshipping_Adapter`: pobiera rekordy dostawcy i mapuje je do pól SKU, nazwa, cena, stock, opis, zdjęcia i status. W przyszłości można dodać adapter REST, XML lub CSV, a następnie osobno wywoływać mapowanie i zapis do WooCommerce. WooCommerce pozostaje źródłem prawdy dla katalogu sklepu.

Po wyborze dostawcy należy ustalić autoryzację, limity, harmonogram, mapowanie kategorii i wariantów, zasady cen i stanów magazynowych, obsługę błędów oraz testy synchronizacji. Nie twórz synchronizacji bez konkretnej specyfikacji dostawcy.
