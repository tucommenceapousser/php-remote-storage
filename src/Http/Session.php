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

class Session implements SessionInterface
{
    /** @var bool */
    private $secureOnly = true;

    public function setSecureOnly($secureOnly)
    {
        $this->secureOnly = (bool) $secureOnly;
    }

    public function startSession()
    {
        if ('' === session_id()) {
            session_start(
                [
                    'use_cookies' => true,
                    'cookie_secure' => $this->secureOnly,
                    'cookie_httponly' => true,
                    'use_only_cookies' => true,
                ]
            );
        }

        // https://paragonie.com/blog/2015/04/fast-track-safe-and-secure-php-sessions
        // Make sure we have a canary set
        if (!isset($_SESSION['canary'])) {
            session_regenerate_id(true);
            $_SESSION['canary'] = time();
        }
        // Regenerate session ID every five minutes:
        if ($_SESSION['canary'] < time() - 300) {
            session_regenerate_id(true);
            $_SESSION['canary'] = time();
        }
    }

    public function set($key, $value)
    {
        $this->startSession();
        $_SESSION[$key] = $value;
    }

    public function delete($key)
    {
        $this->startSession();
        if ($this->has($key)) {
            unset($_SESSION[$key]);
        }
    }

    public function has($key)
    {
        $this->startSession();

        return array_key_exists($key, $_SESSION);
    }

    public function get($key)
    {
        $this->startSession();
        if ($this->has($key)) {
            return $_SESSION[$key];
        }

        return;
    }

    public function destroy()
    {
        $this->startSession();
        session_destroy();
    }
}
