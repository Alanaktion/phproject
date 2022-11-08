<?php

namespace Model;

/**
 * Class Session
 *
 * @property int $id
 * @property string $token
 * @property string $ip
 * @property int $user_id
 * @property string $created
 */
class Session extends \Model
{
    protected $_table_name = "session";
    public const COOKIE_NAME = "phproj_token";

    /**
     * Create a new session
     * @param int $user_id
     * @param bool $auto_save
     */
    public function __construct($user_id = null, bool $auto_save = true)
    {
        // Run model constructor
        parent::__construct();

        if ($user_id !== null) {
            $this->user_id = $user_id;
            $this->token = \Helper\Security::instance()->salt_sha2();
            $this->ip = \Base::instance()->get("IP");
            $this->created = date("Y-m-d H:i:s");
            if ($auto_save) {
                $this->save();
            }
        }
    }

    /**
     * Load the current session
     * @return Session
     */
    public function loadCurrent(): Session
    {
        $f3 = \Base::instance();
        $token = $f3->get("COOKIE." . self::COOKIE_NAME);
        if ($token) {
            $this->load(["token = ?", $token]);
            $lifetime = $f3->get("session_lifetime");
            $duration = time() - strtotime($this->created);

            // Delete expired sessions
            if ($duration > $lifetime) {
                $this->delete();
                return $this;
            }

            // Update nearly expired sessions
            if ($duration > $lifetime / 2) {
                if ($f3->get("DEBUG")) {
                    $log = new \Log("session.log");
                    $log->write("Updating expiration: " . json_encode($this->cast(), JSON_THROW_ON_ERROR)
                            . "; new date: " . date("Y-m-d H:i:s"));
                }
                $this->created = date("Y-m-d H:i:s");
                $this->ip = $f3->get("IP");
                $this->save();
                $this->setCurrent();
            }
        }
        return $this;
    }

    /**
     * Set the user's cookie to the current session
     * @return Session
     */
    public function setCurrent(): Session
    {
        $f3 = \Base::instance();

        if ($f3->get("DEBUG")) {
            $log = new \Log("session.log");
            $log->write("Setting current session: " . json_encode($this->cast(), JSON_THROW_ON_ERROR));
        }

        $f3->set("COOKIE." . self::COOKIE_NAME, $this->token, $f3->get("session_lifetime"));
        return $this;
    }

    /**
     * Delete the session
     * @return Session
     */
    public function delete(): Session
    {
        if (!$this->id) {
            return $this;
        }

        $f3 = \Base::instance();

        if ($f3->get("DEBUG")) {
            $log = new \Log("session.log");
            $log->write("Deleting session: " . json_encode($this->cast(), JSON_THROW_ON_ERROR));
        }

        // Empty the session cookie if it matches the current token
        if ($this->token == $f3->get("COOKIE." . self::COOKIE_NAME)) {
            $f3->set("COOKIE." . self::COOKIE_NAME, "");
        }

        // Delete the session row
        parent::delete();

        return $this;
    }
}
