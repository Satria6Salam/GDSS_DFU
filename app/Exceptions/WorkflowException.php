<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Transisi status ditolak karena syarat (guard) Bab 7.5 tidak terpenuhi.
 * Pesan ditulis untuk pengguna (Bab 7.6) dan ditampilkan apa adanya.
 */
class WorkflowException extends RuntimeException {}
