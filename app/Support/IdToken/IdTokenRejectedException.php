<?php

namespace App\Support\IdToken;

use RuntimeException;

/**
 * The token failed a check the decoder makes. The message is the reason, for
 * the log only.
 *
 * Not an HTTP exception on purpose: the decoder does not know whose token it
 * was checking, so it cannot say "signing in with Google did not work". The
 * verifier that called it catches this and throws its own.
 */
class IdTokenRejectedException extends RuntimeException {}
