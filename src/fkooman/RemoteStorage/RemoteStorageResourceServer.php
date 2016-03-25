<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

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
