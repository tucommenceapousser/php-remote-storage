<?php

namespace fkooman\RemoteStorage;

use fkooman\OAuth\ResourceServerStorageInterface;
use fkooman\OAuth\ResourceServer;

class RemoteStorageResourceServer implements ResourceServerStorageInterface
{
    // we do not really have a registration, as there is only one resource 
    // server, the remoteStorage server...
    public function getResourceServer($resourceServerId)
    {
        return new ResourceServer(
            $resourceServerId,
            null,
            null
        );
    }
}
