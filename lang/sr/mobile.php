<?php

/**
 * Everything the external mobile app's API says to a person, in Serbian
 * (Latin). `lang/en/mobile.php` has the same keys; `MobileTranslationsTest`
 * fails when they drift.
 */
return [
    'auth' => [
        'code_sent' => 'Poslali smo kod na vašu email adresu.',
        'no_account' => 'Ne postoji nalog sa ovom email adresom.',
        'signed_in' => 'Uspešno ste prijavljeni.',
        'registered' => 'Nalog je napravljen.',
        'signed_out' => 'Uspešno ste odjavljeni.',
        'invalid_code' => 'Kod nije ispravan ili je istekao. Zatražite novi.',
        'resend_too_soon' => 'Novi kod možete zatražiti za :seconds s.',
        'account_not_found' => 'Ne postoji nalog za ovaj :provider nalog. Napravite novi.',
        'account_exists' => 'Nalog sa ovom email adresom već postoji. Prijavite se.',
        'not_linkable' => 'Za ovu email adresu već postoji nalog. Prijavite se kodom na email.',
        'invalid_provider_token' => 'Prijava preko naloga :provider nije uspela. Pokušajte ponovo.',
        'provider_unavailable' => 'Prijava preko naloga :provider trenutno nije dostupna.',
    ],
    'access' => [
        'mobile_only' => 'Ovaj deo API-ja je samo za korisnike mobilne aplikacije.',
        'internal_only' => 'Ovaj deo API-ja je samo za zaposlene.',
        'blocked' => 'Vaš nalog je blokiran.',
    ],
    'account' => [
        'profile' => 'Podaci o nalogu.',
        'updated' => 'Profil je sačuvan.',
        'avatar_updated' => 'Profilna slika je sačuvana.',
        'avatar_removed' => 'Profilna slika je uklonjena.',
        'deleted' => 'Nalog je obrisan.',
    ],
    'device' => [
        'show' => 'Podaci o uređaju.',
        'saved' => 'Uređaj je sačuvan.',
        'not_found' => 'Ovaj uređaj nije registrovan.',
        'push_disabled' => 'Obaveštenja nisu uključena na ovom uređaju.',
        'test_push_sent' => 'Probno obaveštenje je poslato.',
    ],
    'mail' => [
        'code' => [
            'subject' => 'Vaš kod: :code',
            'greeting' => 'Zdravo,',
            'line' => 'Unesite ovaj kod u aplikaciju :app:',
            'expiry' => 'Kod važi :minutes minuta.',
            'ignore' => 'Ako niste vi tražili kod, slobodno zanemarite ovu poruku. Niko ne može da uđe u vaš nalog bez njega.',
        ],
    ],
    'push' => [
        'test' => [
            'title' => 'Probno obaveštenje',
            'body' => 'Obaveštenja rade na ovom uređaju.',
        ],
    ],
];
