<?php

use Phalcon\Arr as Arr;

abstract class ControllerBase extends \Phalcon\Mvc\Controller {

	public $checkLogin = TRUE;
	public $user;
	private $_realm = 'myleft';

	public function beforeExecuteRoute($dispatcher) {

		if ($this->checkLogin && !$this->digestAuth()) {
			return FALSE;
		}

		return TRUE;
	}

	public function digestAuth() {
		$auth = $this->request->getDigestAuth();
		if (empty($auth) || !is_array($auth)) {
			$this->setUnauthorized(FALSE, 'Incorrect auth digest');
			return FALSE;
		}

		// Check for stale nonce
		$nonce = Arr::get($auth, 'nonce');
		if ($nonce && $this->isStaleNonce((int) $nonce)) {
			$this->setUnauthorized(TRUE, 'Incorrect auth nonce');
			return FALSE;
		}

		$method = $this->request->getMethod();
		$requesturi = $this->request->getServer('REQUEST_URI');
		$fullurl = $this->request->getScheme() . '://' . $this->request->getHttpHost() . $requesturi;

		$uri = Arr::get($auth, 'uri');
		if ($uri != $requesturi && $uri != $fullurl) {
			$this->setBadRequest('Digest auth URI != request URI uri:' . $uri . ' - ' . $fullurl);
			return FALSE;
		}

		// Check opaque is correct
		$opaque = Arr::get($auth, 'opaque');
		if ($opaque != md5($this->_realm . $nonce)) {
			$this->setBadRequest('Incorrect auth opaque:' . $opaque);
			return FALSE;
		}

		// Check username is correct
		$username = Arr::get($auth, 'username');
		if (empty($username)) {
			$this->setBadRequest('Incorrect auth username');
			return FALSE;
		}

		$this->user = Users::findFirst(array(
				"username = :username:",
				"bind" => array('username' => $username)
		));

		if (!$this->user) {
			$this->setUnauthorized(FALSE, 'Incorrect auth username');
			return FALSE;
		}

		$a1 = $username . ':' . $this->_realm . ':' . $this->user->password;
		$ha1 = md5($a1);

		$qop = Arr::get($auth, 'qop');
		if ($qop == 'auth-int') {
			$a2 = $method . ':' . $uri . ':' . $this->request->getRawBody();
		} else {
			$a2 = $method . ':' . $uri;
		}

		$ha2 = md5($a2);

		$nc = Arr::get($auth, 'nc');
		$cnonce = Arr::get($auth, 'cnonce');

		if ($qop == 'auth' || $qop == 'auth-int') {
			$response = $ha1 . ':' . $nonce . ':' . $nc . ':' . $cnonce . ':' . $qop . ':' . $ha2;
		} else {
			$response = $ha1 . ':' . $nonce . ':' . $ha2;
		}

		$auth_response = Arr::get($auth, 'response');

		if ($auth_response != md5($response)) {
			$this->setBadRequest('Incorrect auth response');
			return FALSE;
		}

		return TRUE;
	}

	private function setUnauthorized($stale = false, $reason = 'Forbidden') {
		$result = ['status' => 'error', 'message' => $reason];

		$nonce = time();
		$opaque = md5($this->_realm + $nonce);

		$authheder = 'Digest realm="' . $this->_realm . '", qop="auth-int,auth", algorithm="MD5", nonce="' . $nonce . '", opaque="' . $opaque . '"';
		if ($stale) {
			$authheder .= ', stale=TRUE';
		}
		$this->response->setHeader('WWW-Authenticate', $authheder);
		$this->sendAuto($result, 401, 'Unauthorized');
	}

	private function setBadRequest($reason = '') {
		$result = ['status' => 'error', 'message' => $reason];
		$this->sendAuto($result, 400, 'Bad Request');
	}

	private function isStaleNonce($nonce) {
		$now = time();
		if ($nonce - $now > 120  || $now - $nonce > 3600) {
			return TRUE;
		}
		return FALSE;
	}

	public function sendAuto($content = NULL, $status = NULL, $message = NULL) {
		if ($content) {
			$this->response->setJsonContent($content);
		}
		if ($status) {
			$this->response->setStatusCode($status, $message);
		}
		$this->response->send();
		exit;
	}

}

