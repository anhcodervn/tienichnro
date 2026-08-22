<?php

namespace App\Jobs;

/**
 * Compatibility bridge for jobs serialized before the Topup feature migration.
 *
 * New code must dispatch the feature-owned job directly.
 */
class ProcessTopupOrder extends \App\Features\Topup\Jobs\ProcessTopupOrder {}
