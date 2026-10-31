<?php

namespace App\Sales;

/**
 * The action needs a Supervisor, Manager or Owner to approve it with their
 * PIN. The POS asks for one and tries again.
 */
class ApprovalRequired extends SaleException
{
    public function __construct()
    {
        parent::__construct('This needs approval. Ask a supervisor to enter their PIN.');
    }
}
