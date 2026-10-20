<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when a change would push the stock of a category below zero. */
class InsufficientStockException extends RuntimeException {}
