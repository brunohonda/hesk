<?php
/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 */

class HeskIMAP {

    /**
     * @var string $host
     */
    public $host;

    /**
     * @var integer $port
     */
    public $port;

    /**
     * @var string $username
     */
    public $username;

    /**
     * @var string $password
     */
    public $password;

    /**
     * @var string $accessToken
     */
    public $accessToken;

    /**
     * @var string $folder
     */
    public $folder = 'INBOX';

    /**
     * @var boolean
     */
    public $ssl = false;

    /**
     * @var boolean
     */
    public $tls = false;

    /**
     * @var boolean
     */
    public $ignoreCertificateErrors = false;

    /**
     * @var integer $connectTimeout
     */
    public $connectTimeout = 20;

    /**
     * @var integer $responseTimeout
     */
    public $responseTimeout = 20;

    /**
     * Will we be connecting using OAuth?
     * @var boolean
     */
    public $useOAuth = false;

    /**
     * Connect to IMAP in read only mode
     * @var boolean
     */
    public $readOnly = false;

    // Resource storage
    private $imap;

    // Mailbox path for imap_open
    private $mailbox;

    // Storage for imap_search results
    private $emails;

    // Storage for error messages
    private $errors;


    /**
     * Connects to Host if successful returns true else false
     * @return boolean
     */
    public function login() {
        if ($this->useOAuth) {
            //echo "using OAuth class<br>\n";
            require 'OAuthIMAP.php';
            $this->imap = new OAuthIMAP();

            if ($this->ssl) {
                $this->imap->host = 'ssl://' . $this->host;
            } elseif ($this->tls) {
                $this->imap->host = 'tls://' . $this->host;
            } else {
                $this->imap->host = $this->host;
            }

            $this->imap->port = $this->port;
            $this->imap->username = $this->username;
            if ($this->password)
                $this->imap->password = $this->password;
            if ($this->accessToken)
                $this->imap->accessToken = $this->accessToken;
            $this->imap->ignoreCertificateErrors = $this->ignoreCertificateErrors;
            $this->imap->tls = $this->tls;
            $this->imap->connectTimeout = $this->connectTimeout;
            $this->imap->responseTimeout = $this->responseTimeout;

            return $this->imap->login();
        } else {
            //echo "using PHP IMAP extension<br>\n";
            // We need the PHP IMAP extension for this to work
            if ( ! function_exists('imap_open')) {
                global $hesklang;
                die($hesklang['iei']);
            }

            // IMAP mailbox based on required encryption
            if ($this->ssl) {
                $this->mailbox = '{'.$this->host.':'.$this->port.'/imap/ssl'.($this->ignoreCertificateErrors ? '/novalidate-cert' : '').'}';
            } elseif ($this->tls) {
                $this->mailbox = '{'.$this->host.':'.$this->port.'/imap/tls'.($this->ignoreCertificateErrors ? '/novalidate-cert' : '').'}';
            } else {
                $this->mailbox = '{'.$this->host.':'.$this->port.'}';
            }

            imap_timeout(IMAP_OPENTIMEOUT, $this->connectTimeout);
            imap_timeout(IMAP_READTIMEOUT, $this->responseTimeout);
            imap_timeout(IMAP_WRITETIMEOUT, $this->responseTimeout);
            imap_timeout(IMAP_CLOSETIMEOUT, $this->responseTimeout);

            // Connect to IMAP
            if ($this->readOnly) {
                $this->imap = @imap_open($this->mailbox, $this->username, $this->password, OP_READONLY);
            } else {
                $this->imap = @imap_open($this->mailbox, $this->username, $this->password);
            }

            return $this->imap;
        }
    }

    /**
     * Disconnects from Host
     */
    public function logout() {
        if ($this->useOAuth) {
            $this->imap->logout();
        } else {
            imap_close($this->imap);
        }
    }

    /**
     * Returns number of unseen emails in a folder
     * @retun integer
     */
    public function hasUnseenMessages() {
        if ($this->useOAuth) {
            return $this->imap->totalUnseenMails($this->folder);
        } else {
            $emails = imap_search($this->imap, 'UNSEEN');

            if (is_array($emails)) {
                $this->emails = $emails;
                return count($emails);
            } else {
                return 0;
            }
        }
    }

    /**
     * Gets a list of message IDs of unseen messages
     * @return Array
     */
	public function getUnseenMessageIDs() {
        if ($this->useOAuth) {
            $this->imap->selectFolder($this->folder, $this->readOnly);
            return $this->imap->getUnseenMessageIDs();
        }

        if (is_array($this->emails)) {
            return $this->emails;
        }

        return array();
	}

    /**
     * Fetch a single mail and return
     * @param integer $id
     * @return mixed
     */
    public function getMessage($id) {
        if ($this->useOAuth) {
            return $this->imap->getMessage($id);
        } else {
            return imap_fetchbody($this->imap, $id, "");
        }
    }

    /**
     * Fetch a single mail and save it to a file
     * @param integer $id
     * @param string $file
     * @return boolean
     */
    public function saveMessageToFile($id, $file) {
        if ($this->useOAuth) {
            return $this->imap->saveMessageToFile($id, $file);
        } else {
            return imap_savebody($this->imap, $file, $id, "");
        }
    }

    /**
     * Marks a message for deletion
     * @param integer $id
     */
    public function delete($id) {
        if ($this->useOAuth) {
            $this->imap->delete($id);
        } else {
            imap_delete($this->imap, $id);
        }
    }

    /**
     * Expunges mailbox (deletes messages makred with \Deleted flag)
     */
    public function expunge() {
        if ($this->useOAuth) {
            $this->imap->expunge();
        } else {
            imap_expunge($this->imap);
        }
    }

    /**
     * Return IMAP errors
     * @return mixed
     */
    public function getErrors() {
        if ($this->useOAuth) {
            return $this->imap->getErrors();
        } else {
            return imap_errors();
        }
    }

}
