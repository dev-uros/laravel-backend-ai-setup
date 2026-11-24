<?php

namespace App\Support\IdToken;

/**
 * Who signs the ID tokens a verifier accepts, and for whom.
 *
 * Everything that differs between Google and Apple as far as the signature and
 * the standard claims go: where the keys are published, what the cache keys
 * are called, which `iss` values are theirs and which `aud` values are ours. What the
 * remaining claims mean is each verifier's own business.
 */
final readonly class IdTokenIssuer
{
    /**
     * @param  string  $name  Used in log lines only: `google`, `apple`.
     * @param  string  $cachePrefix  The keys are cached under `{prefix}:jwks`.
     * @param  list<string>  $issuers  Accepted `iss` values, compared exactly.
     * @param  list<string>  $audiences  Any of these as `aud` makes a token ours.
     */
    public function __construct(
        public string $name,
        public string $keysUrl,
        public string $cachePrefix,
        public array $issuers,
        public array $audiences,
    ) {}
}
