<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\RemoteStorage\Http;

use fkooman\RemoteStorage\TplInterface;

class FormAuthenticationHook implements BeforeHookInterface
{
    /** @var SessionInterface */
    private $session;

    /** @var \fkooman\RemoteStorage\TplInterface */
    private $tpl;

    /** @var array */
    private $notForList;

    public function __construct(SessionInterface $session, TplInterface $tpl)
    {
        $this->session = $session;
        $this->tpl = $tpl;
        $this->notForList = [
            'GET' => ['/.well-known/webfinger', '/'],
            'POST' => ['/_form/auth/verify'],
        ];
    }

    public function executeBefore(Request $request, array $hookData)
    {
        if ($this->session->has('_form_auth_user')) {
            return $this->session->get('_form_auth_user');
        }

        if (array_key_exists($request->getRequestMethod(), $this->notForList)) {
            if (in_array($request->getPathInfo(), $this->notForList[$request->getRequestMethod()])) {
                return;
            }
        }

        // any other URL, enforce authentication
        $response = new Response(200, 'text/html');
        $response->setBody(
            $this->tpl->render(
                'formAuthentication',
                [
                    '_form_auth_invalid_credentials' => false,
                    '_form_auth_redirect_to' => $request->getUri(),
                    '_form_auth_login_page' => true,
                ]
            )
        );

        return $response;
    }
}
