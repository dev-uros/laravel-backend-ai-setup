<?php

namespace App\Support\Apple;

use RuntimeException;

/**
 * Apple's token endpoint did not do what was asked of it, or this deployment
 * has no key to ask with. The message is the reason, for the log
 * only.
 *
 * Not an HTTP exception on purpose: nobody waiting on a request ever sees it.
 * Signing in carries on without the refresh token, and the revocation job
 * throws it so the queue tries again.
 */
class AppleTokenRequestFailedException extends RuntimeException {}
