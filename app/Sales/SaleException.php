<?php

namespace App\Sales;

use RuntimeException;

/**
 * Something the till cannot do, with a message for the person at it.
 */
class SaleException extends RuntimeException {}
