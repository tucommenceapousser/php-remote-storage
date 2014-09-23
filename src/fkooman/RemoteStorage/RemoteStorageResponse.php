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

use fkooman\Http\Request;
use fkooman\Http\Response;

class RemoteStorageResponse extends Response
{
    public function __construct(Request $request, $statusCode = 200, $entityVersion = null, $contentType = "application/ld+json")
    {
        parent::__construct($statusCode);
        if ("GET" === $request->getRequestMethod()) {
            $this->setHeader("Expires", 0);
        }
        if (null !== $request->getHeader("Origin")) {
            $this->setHeader("Access-Control-Allow-Origin", $request->getHeader("Origin"));
        }
        if (null !== $entityVersion) {
            $this->setHeader("ETag", sprintf('"%s"', $entityVersion));
        }
        if (null !== $contentType) {
            $this->setContentType($contentType);
        }
    }
}
