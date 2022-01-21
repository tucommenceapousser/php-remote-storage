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

use fkooman\RemoteStorage\Http\HtmlResponse;
use fkooman\RemoteStorage\Http\RedirectResponse;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\OAuth\TokenStorage;

class UiModule
{
    private $remoteStorage;

    /** @var TplInterface */
    private $tpl;

    /** @var \fkooman\RemoteStorage\OAuth\TokenStorage */
    private $tokenStorage;

    public function __construct(RemoteStorage $remoteStorage, TplInterface $tpl, TokenStorage $tokenStorage)
    {
        $this->remoteStorage = $remoteStorage;
        $this->tpl = $tpl;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @param string $userId
     */
    public function getHome(Request $request, $userId)
    {
        $approvalList = $this->tokenStorage->getAuthorizedClients($userId);

        return new HtmlResponse(
            $this->tpl->render(
                'home',
                [
                    'approval_list' => $approvalList,
                    'host' => $request->getServerName(),
                    'user_id' => $userId,
                    'disk_usage' => $this->remoteStorage->getFolderSize(new Path(sprintf('/%s/', $userId))),
                    'request_url' => $request->getUri(),
                    'show_account_icon' => true,
                ]
            )
        );
    }

    /**
     * @param string $userId
     */
    public function postHome(Request $request, $userId)
    {
        // XXX InputValidation
        $clientId = $request->getPostParameter('client_id');
        $this->tokenStorage->removeClientTokens($userId, $clientId);

        return new RedirectResponse($request->getUri(), 302);
    }
}
