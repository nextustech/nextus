<?php

namespace App\Jobs\Concerns;

trait HasTenantContext
{
    public function tags(): array
    {
        return $this->tenant_id
            ? ['tenant:'.$this->tenant_id]
            : ['tenant:null'];
    }
}
