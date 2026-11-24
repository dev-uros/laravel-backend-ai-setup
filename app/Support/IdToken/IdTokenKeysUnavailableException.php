<?php

namespace App\Support\IdToken;

use RuntimeException;

/**
 * The issuer's keys could not be fetched. Already logged by the decoder; the
 * verifier turns it into its own 503.
 */
class IdTokenKeysUnavailableException extends RuntimeException {}
