<?php

namespace fkooman\RemoteStorage;

use fkooman\OAuth\ResourceServer\TokenIntrospection;
use fkooman\OAuth\Common\Scope;

class RemoteStorageTokenIntrospection extends TokenIntrospection
{
    /**
     * Check wheather the user has granted "rw" permissions for the specified
     * module
     */
    public function hasWriteScope($moduleName)
    {
        $hasReadWriteScope = $this->getScope()->hasScope(Scope::fromString(sprintf("%s:%s", $moduleName, "rw")));

        return $hasReadWriteScope;
    }

    /**
     * Check wheather the user has granted "r" permissions for the specified
     * module
     */
    public function hasReadScope($moduleName)
    {
        $hasReadWriteScope = $this->getScope()->hasScope(Scope::fromString(sprintf("%s:%s", $moduleName, "rw")));
        $hasReadScope = $this->getScope()->hasScope(Scope::fromString(sprintf("%s:%s", $moduleName, "r")));

        return $hasReadWriteScope || $hasReadScope;
    }
}
