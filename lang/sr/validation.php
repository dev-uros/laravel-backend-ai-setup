<?php

/**
 * Serbian (Latin) validation messages, for the external mobile app's API
 * (`SetLocale` picks the language off `Accept-Language`).
 *
 * Laravel renders the error shape itself (`errors` keyed by field) -- only the
 * sentences are translated. A key missing here falls back to English
 * (APP_FALLBACK_LOCALE), so a rule added later shows up in English rather
 * than as a raw key.
 */

return [
    'accepted' => 'Polje :attribute mora biti prihvaćeno.',
    'active_url' => 'Polje :attribute mora biti ispravan URL.',
    'after' => 'Polje :attribute mora biti datum posle :date.',
    'after_or_equal' => 'Polje :attribute mora biti datum posle ili jednak :date.',
    'alpha' => 'Polje :attribute može sadržati samo slova.',
    'alpha_dash' => 'Polje :attribute može sadržati samo slova, brojeve, crtice i donje crte.',
    'alpha_num' => 'Polje :attribute može sadržati samo slova i brojeve.',
    'array' => 'Polje :attribute mora biti niz.',
    'before' => 'Polje :attribute mora biti datum pre :date.',
    'before_or_equal' => 'Polje :attribute mora biti datum pre ili jednak :date.',
    'between' => [
        'array' => 'Polje :attribute mora imati između :min i :max stavki.',
        'file' => 'Polje :attribute mora biti između :min i :max kilobajta.',
        'numeric' => 'Polje :attribute mora biti između :min i :max.',
        'string' => 'Polje :attribute mora imati između :min i :max karaktera.',
    ],
    'boolean' => 'Polje :attribute mora biti tačno ili netačno.',
    'confirmed' => 'Potvrda polja :attribute se ne poklapa.',
    'current_password' => 'Uneta šifra nije ispravna.',
    'date' => 'Polje :attribute mora biti ispravan datum.',
    'date_equals' => 'Polje :attribute mora biti datum jednak :date.',
    'date_format' => 'Polje :attribute mora odgovarati formatu :format.',
    'different' => 'Polja :attribute i :other moraju biti različita.',
    'digits' => 'Polje :attribute mora imati :digits cifara.',
    'digits_between' => 'Polje :attribute mora imati između :min i :max cifara.',
    'dimensions' => 'Slika u polju :attribute nema dozvoljene dimenzije.',
    'distinct' => 'Polje :attribute ima duplu vrednost.',
    'email' => 'Polje :attribute mora biti ispravna email adresa.',
    'ends_with' => 'Polje :attribute mora se završavati jednom od vrednosti: :values.',
    'enum' => 'Izabrana vrednost za :attribute nije dozvoljena.',
    'exists' => 'Izabrana vrednost za :attribute ne postoji.',
    'file' => 'Polje :attribute mora biti fajl.',
    'filled' => 'Polje :attribute mora imati vrednost.',
    'gt' => [
        'array' => 'Polje :attribute mora imati više od :value stavki.',
        'file' => 'Polje :attribute mora biti veće od :value kilobajta.',
        'numeric' => 'Polje :attribute mora biti veće od :value.',
        'string' => 'Polje :attribute mora imati više od :value karaktera.',
    ],
    'gte' => [
        'array' => 'Polje :attribute mora imati najmanje :value stavki.',
        'file' => 'Polje :attribute mora biti najmanje :value kilobajta.',
        'numeric' => 'Polje :attribute mora biti najmanje :value.',
        'string' => 'Polje :attribute mora imati najmanje :value karaktera.',
    ],
    'image' => 'Polje :attribute mora biti slika.',
    'in' => 'Izabrana vrednost za :attribute nije dozvoljena.',
    'integer' => 'Polje :attribute mora biti ceo broj.',
    'ip' => 'Polje :attribute mora biti ispravna IP adresa.',
    'json' => 'Polje :attribute mora biti ispravan JSON zapis.',
    'lt' => [
        'array' => 'Polje :attribute mora imati manje od :value stavki.',
        'file' => 'Polje :attribute mora biti manje od :value kilobajta.',
        'numeric' => 'Polje :attribute mora biti manje od :value.',
        'string' => 'Polje :attribute mora imati manje od :value karaktera.',
    ],
    'lte' => [
        'array' => 'Polje :attribute sme imati najviše :value stavki.',
        'file' => 'Polje :attribute sme biti najviše :value kilobajta.',
        'numeric' => 'Polje :attribute sme biti najviše :value.',
        'string' => 'Polje :attribute sme imati najviše :value karaktera.',
    ],
    'max' => [
        'array' => 'Polje :attribute sme imati najviše :max stavki.',
        'file' => 'Polje :attribute sme biti najviše :max kilobajta.',
        'numeric' => 'Polje :attribute sme biti najviše :max.',
        'string' => 'Polje :attribute sme imati najviše :max karaktera.',
    ],
    'mimes' => 'Polje :attribute mora biti fajl tipa: :values.',
    'mimetypes' => 'Polje :attribute mora biti fajl tipa: :values.',
    'min' => [
        'array' => 'Polje :attribute mora imati najmanje :min stavki.',
        'file' => 'Polje :attribute mora biti najmanje :min kilobajta.',
        'numeric' => 'Polje :attribute mora biti najmanje :min.',
        'string' => 'Polje :attribute mora imati najmanje :min karaktera.',
    ],
    'not_in' => 'Izabrana vrednost za :attribute nije dozvoljena.',
    'not_regex' => 'Format polja :attribute nije dozvoljen.',
    'numeric' => 'Polje :attribute mora biti broj.',
    'password' => [
        'letters' => 'Polje :attribute mora sadržati bar jedno slovo.',
        'mixed' => 'Polje :attribute mora sadržati bar jedno veliko i jedno malo slovo.',
        'numbers' => 'Polje :attribute mora sadržati bar jedan broj.',
        'symbols' => 'Polje :attribute mora sadržati bar jedan simbol.',
        'uncompromised' => 'Izabrana šifra se pojavila u poznatim krađama podataka. Izaberite drugu šifru.',
    ],
    'present' => 'Polje :attribute mora biti poslato.',
    'prohibited' => 'Polje :attribute nije dozvoljeno.',
    'prohibited_if' => 'Polje :attribute nije dozvoljeno kada je :other jednako :value.',
    'prohibited_unless' => 'Polje :attribute nije dozvoljeno osim kada je :other jedno od: :values.',
    'regex' => 'Format polja :attribute nije ispravan.',
    'required' => 'Polje :attribute je obavezno.',
    'required_if' => 'Polje :attribute je obavezno kada je :other jednako :value.',
    'required_unless' => 'Polje :attribute je obavezno osim kada je :other jedno od: :values.',
    'required_with' => 'Polje :attribute je obavezno kada je poslato :values.',
    'required_without' => 'Polje :attribute je obavezno kada nije poslato :values.',
    'same' => 'Polja :attribute i :other moraju biti ista.',
    'size' => [
        'array' => 'Polje :attribute mora imati :size stavki.',
        'file' => 'Polje :attribute mora biti :size kilobajta.',
        'numeric' => 'Polje :attribute mora biti :size.',
        'string' => 'Polje :attribute mora imati :size karaktera.',
    ],
    'starts_with' => 'Polje :attribute mora počinjati jednom od vrednosti: :values.',
    'string' => 'Polje :attribute mora biti tekst.',
    'unique' => 'Vrednost polja :attribute je već zauzeta.',
    'uploaded' => 'Slanje fajla u polju :attribute nije uspelo.',
    'url' => 'Polje :attribute mora biti ispravan URL.',
    'uuid' => 'Polje :attribute mora biti ispravan UUID.',

    /**
     * Per-field overrides. Left empty on purpose: a message that only makes
     * sense next to its rules belongs in that FormRequest's messages(), where
     * the rule it explains is visible.
     */
    'custom' => [
        'username' => [
            'regex' => 'Korisničko ime sme da sadrži samo mala slova, brojeve, tačku i donju crtu.',
        ],
    ],

    /**
     * Field names as a person reads them, in the language of the answer. Kept
     * here rather than in each FormRequest's `attributes()` so a field is
     * named the same way everywhere and both languages are edited side by side
     * (`lang/en/validation.php` has the same keys).
     */
    'attributes' => [
        'email' => 'email adresa',
        'code' => 'kod',
        'username' => 'korisničko ime',
        'first_name' => 'ime',
        'last_name' => 'prezime',
        'display_personal_name' => 'prikaz imena',
        'avatar' => 'profilna slika',
        'locale' => 'jezik',
        'id_token' => 'token',
        'nonce' => 'nonce',
        'authorization_code' => 'autorizacioni kod',
        'given_name' => 'ime',
        'family_name' => 'prezime',
        'device' => 'uređaj',
        'device.device_id' => 'ID uređaja',
        'device.platform' => 'platforma',
        'device_id' => 'ID uređaja',
        'platform' => 'platforma',
        'expo_push_notification_token' => 'token za obaveštenja',
        'apple_authorization_code' => 'Apple autorizacioni kod',
    ],
];
