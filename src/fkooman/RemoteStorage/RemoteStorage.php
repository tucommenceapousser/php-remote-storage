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

use fkooman\RemoteStorage\Exception\RemoteStorageException;
use fkooman\OAuth\ResourceServer\TokenIntrospection;
use fkooman\Http\Request;
use fkooman\Http\JsonResponse;

class RemoteStorage
{
    /** @var fkooman\RemoteStorage\MetadataStorage */
    private $md;

    /** @var fkooman\RemoteStorage\DocumentStorage */
    private $d;

    /** @var fkooman\OAuth\ResourceServer\TokenIntrospection */
    private $i;

    public function __construct(MetadataStorage $md, DocumentStorage $d, TokenIntrospection $i)
    {
        $this->md = $md;
        $this->d = $d;
        $this->i = $i;
    }

    public function putDocument(Path $p, $contentType, $documentData, $ifMatch = null)
    {
        if ($p->getUserId() !== $this->i->getSub()) {
            throw new RemoteStorageException("not allowed");
        }

        $updatedEntities = $this->d->putDocument($p, $documentData);
        $this->md->updateDocument($p, $contentType);
        foreach ($updatedEntities as $u) {
            $this->md->updateFolder(new Path($u));
        }
    }

    public function deleteDocument(Path $p, $ifMatch = null)
    {
        if ($p->getUserId() !== $this->i->getSub()) {
            throw new RemoteStorageException("not allowed");
        }

        $deletedEntities = $this->d->deleteDocument($p);
        foreach ($deletedEntities as $d) {
            $this->md->deleteEntry(new Path($d));
        }
        // FIXME: increment the version of the folder containing the last
        // deleted folder and up to the user root
    }

    public function getVersion(Path $p)
    {
        if (!$p->getIsPublic()) {
            if ($p->getUserId() !== $this->i->getSub()) {
                throw new RemoteStorageException("not allowed");
            }
        }

        return $this->md->getVersion($p);
    }

    public function getDocument(Path $p, $ifMatch = null)
    {
        if (!$p->getIsPublic()) {
            if ($p->getUserId() !== $this->i->getSub()) {
                throw new RemoteStorageException("not allowed");
            }
        }

       return $this->d->getDocument($p);
   }

    public function getFolder(Path $p, $ifMatch = null)
    {
        if ($p->getUserId() !== $this->i->getSub()) {
            throw new RemoteStorageException("not allowed");
        }

        $folder = $this->d->getFolder($p);
        foreach ($folder as $name => $meta) {
            $folder[$name]["ETag"] = $this->md->getVersion(new Path($p->getFolderPath()->getPath() . $name));
        }

        return $folder;
    }

    public function handleRequest(Request $request)
    {
        $response = new JsonResponse();
        $response->setContent(array("foo" => "bar"));

        return $response;
    }
}
