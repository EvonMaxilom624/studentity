<?php

declare(strict_types=1);

namespace Tests\Support;

/** Synthetic typed adapter failure. Never registered by application code. */
final class SessionLabTransportUnavailable extends \RuntimeException {}
