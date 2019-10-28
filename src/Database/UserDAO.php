<?php

namespace WEEEOpen\Tarallo\Database;

use WEEEOpen\Tarallo\NotFoundException;
use WEEEOpen\Tarallo\User;
use WEEEOpen\Tarallo\SessionSSO;

final class UserDAO extends DAO {
	/**
	 * Get user data from a session, or null
	 *
	 * @param string $sessionId
	 *
	 * @return SessionSSO
	 */
	public function getSession(string $sessionId): ?SessionSSO {
		try {
			$s = $this->getPDO()->prepare('SELECT `Data` FROM Session WHERE `Session` = ?');
			$result = $s->execute([$sessionId]);
			assert($result !== false, 'get session');
			$rows = $s->rowCount();
			if($rows === 0) {
				return null;
			}
			return unserialize($s->fetch(\PDO::FETCH_NUM)[0]);
		} finally {
			$s->closeCursor();
		}
	}

	public function getRedirect(string $sessionId): ?SessionSSO {
		try {
			$s = $this->getPDO()->prepare('SELECT `Redirect` FROM Session WHERE `Session` = ?');
			$result = $s->execute([$sessionId]);
			assert($result !== false, 'get session redirect');
			$rows = $s->rowCount();
			if($rows === 0) {
				return null;
			}
			return $s->fetch(\PDO::FETCH_NUM)[0];
		} finally {
			$s->closeCursor();
		}
	}

	public function deleteSession(string $sessionId) {
		try {
			$s = $this->getPDO()->prepare('DELETE FROM Session WHERE `Session` = ?');
			$result = $s->execute([$sessionId]);
			assert($result !== false, 'delete sessions');
		} finally {
			$s->closeCursor();
		}
	}


	/**
	 * Check if a session exists. If it does, lock the row for update so it won't disappear or change in the meantime.
	 *
	 * @param string $sessionID
	 *
	 * @return bool Does it exist or not?
	 */
	public function sessionExists(string $sessionID): bool {
		try {
			$s = $this->getPDO()->prepare('SELECT `Data`, `Redirect` FROM Session WHERE `Session` = :s FOR UPDATE');
			$s->bindValue(':s', $sessionID, \PDO::PARAM_STR);
			$result = $s->execute();
			assert($result !== false, 'session exists');
			$rows = $s->rowCount();
			return $rows > 0;
		} finally {
			$s->closeCursor();
		}
	}

	/**
	 * Set or delete the redirect field for a session, create if it does not exist
	 *
	 * @param string $sessionID
	 * @param string|null $redirect
	 *
	 * @throws DatabaseException if session does not exist
	 */
	public function setRedirectForSession(string $sessionID, ?string $redirect = null) {
		if(!$this->sessionExists($sessionID)) {
			$this->createSession($sessionID);
			if($redirect === null) {
				// It's already null by default
				return;
			}
		}

		try{
			$s = $this->getPDO()->prepare('UPDATE Session SET Redirect = :r WHERE `Session` = :s');
			$s->bindValue(':s', $sessionID, \PDO::PARAM_STR);
			$s->bindValue(':r', $redirect, $redirect === null ? \PDO::PARAM_BOOL : \PDO::PARAM_STR);
			$result = $s->execute();
			assert($result !== false, 'session exists');
		} finally {
			$s->closeCursor();
		}
	}

	/**
	 * Set or delete data for a session, if it exists
	 *
	 * @param string $sessionID
	 * @param SessionSSO $data
	 */
	public function setDataForSession(string $sessionID, ?SessionSSO $data) {
		if(!$this->sessionExists($sessionID)) {
			$this->createSession($sessionID);
			if($data === null) {
				// It's already null by default
				return;
			}
		}

		try {
			$s = $this->getPDO()->prepare('UPDATE Session SET Data = :d WHERE `Session` = :s');
			$s->bindValue(':s', $sessionID, \PDO::PARAM_STR);
			if($data === null) {
				$s->bindValue(':d', null, \PDO::PARAM_NULL);
			} else {
				$s->bindValue(':d', serialize($data), \PDO::PARAM_STR);
			}
			$result = $s->execute();
			assert($result !== false, 'session exists');
		} finally {
			$s->closeCursor();
		}
	}

	private function createSession(string $sessionID) {
		try {
			$s = $this->getPDO()->prepare('INSERT INTO Session(Session, Data, Redirect) VALUES (:s, NULL, NULL)');
			$s->bindValue(':s', $sessionID, \PDO::PARAM_STR);
			$result = $s->execute();
			assert($result !== false, 'create session');
		} finally {
			$s->closeCursor();
		}
	}

	/**
	 * Set the MySQL global variable taralloAuditUsername.
	 *
	 * @param $username
	 */
	private function setAuditUsername($username) {
		try {
			$s = $this->getPDO()->prepare(
			/** @lang MySQL */
				'CALL SetUser(?)'
			);
			$result = $s->execute([$username]);
			assert($result !== false, 'set audit username');
		} finally {
			$s->closeCursor();
		}
	}

}