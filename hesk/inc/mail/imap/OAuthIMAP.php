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

/**
 * Credits: this class is based on VivOAuthIMAP by Vivek Muthal
 */

class OAuthIMAP {

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
    public $connectTimeout = 30;

    /**
     * @var integer $responseTimeout-
     */
    public $responseTimeout = 30;

    /**
     * Print the client/server communication
     * @var boolean
     */
    public $debug = false;

    /**
     * @var FilePointer $sock
     */
    private $fp;

    /**
     * Command tag counter
     * @var string
     */
    private $tagCounter = 1;

    /**
     * If successfull login then set to true else false
     * @var boolean
     */
    private $isLoggedIn = false;

    /**
     * Stores error messages
     * @var Array
     */
    private $errors;

    /**
     * Connects to Host if successful returns true else false
     * @return boolean
     */
    private function connect() {

		if($this->tls && (!function_exists("extension_loaded") || !extension_loaded("openssl")))
		{
            return("establishing TLS connections requires the OpenSSL extension enabled");
		}

        if ($this->ignoreCertificateErrors) {
            $context = stream_context_create(
                array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                )
            );
        } else {
            $context = stream_context_create();
        }

        ob_start();
        $this->fp = stream_socket_client($this->host.':'.$this->port, $errno, $errstr, $this->connectTimeout, STREAM_CLIENT_CONNECT, $context);
        $ob = ob_get_contents();
        ob_end_clean();

        if (strlen($errstr)) {
            $this->errors[] = "($errno) $errstr";
        } elseif (strlen($ob)) {
            $this->errors[] = $ob;
        }

        if ($this->fp)
            return true;
        return false;
    }

    /**
     * Closes the file pointer
     */
    private function disconnect() {
        fclose($this->fp);
    }

    /**
     * Login with username password / access_token returns true if successful else false
     * @return boolean
     */
    public function login() {
        if ($this->connect()) {
            $command = NULL;
            if (isset($this->username) && isset($this->password)) {
                $command = "LOGIN " . $this->escapeString($this->username) . " " . $this->escapeString($this->password);
            } else if (isset($this->username) && isset($this->accessToken)) {
                $token = base64_encode("user=$this->username\1auth=Bearer $this->accessToken\1\1");
                $command = "AUTHENTICATE XOAUTH2 $token";
            }

            if ($command != NULL) {
                $this->writeCommannd("A" . $this->tagCounter, $command);
                $response = $this->readResponse("A" . $this->tagCounter, false, true);

                if (isset($response[0][0]) && $response[0][0] == "OK") { //Got Successful response
                    $this->isLoggedIn = true;
                    //$this->selectInbox();
                    return true;
                } elseif (isset($response[0][2])) {
                    $this->errors[] = $response[0][2];
                } else {
                    $response[0][2] = 'Unable to login';
                }
            }

            return false;
        }
        return false;
    }

    /**
     * Logout then disconnects
     */
    public function logout() {
        $this->writeCommannd("A" . $this->tagCounter, "LOGOUT");
        $this->readResponse("A" . $this->tagCounter);
        $this->disconnect();
        $this->isLoggedIn = false;
    }

    /**
     * Returns true if user is authenticated else false
     * @return boolean
     */
    public function isAuthenticated() {
        return $this->isLoggedIn;
    }

    /**
     * Fetch a single mail header and return
     * @param integer $id
     * @return array
     */
    public function getHeader($id) {
        $this->writeCommannd("A" . $this->tagCounter, "FETCH $id RFC822.HEADER");
        $response = $this->readResponse("A" . $this->tagCounter);

        if (isset($response[0][0]) && $response[0][0] == "OK") {
            $modifiedResponse = $response;
            unset($modifiedResponse[0]);
            return $modifiedResponse;
        }

        return $response;
    }

    /**
     * Returns headers array
     * @param integer $from
     * @param integer $to
     * @return Array
     */
    public function getHeaders($from, $to) {
        $this->writeCommannd("A" . $this->tagCounter, "FETCH $from:$to RFC822.HEADER");
        $response = $this->readResponse("A" . $this->tagCounter);
        return $this->modifyResponse($response);
    }

    /**
     * Fetch a single mail and return
     * @param integer $id
     * @return Array
     */
    public function getMessage($id) {
        $this->writeCommannd("A" . $this->tagCounter, "FETCH $id RFC822");
        $response = $this->readResponse("A" . $this->tagCounter, true);
        return $this->modifyResponse($response);
    }


    /**
     * Fetch a single mail and save it to a file
     * @param integer $id
     * @param string $file
     * @return Array or false
     */
    public function saveMessageToFile($id, $file) {
        $fh = @fopen($file, "w");
        if ($fh === false) {
            $this->errors[] = 'Cannot open file '.$file.' for writing';
            return false;
        }

        $this->writeCommannd("A" . $this->tagCounter, "FETCH $id RFC822");

        // The first line sould be: * FETCH (id) RFC822 {(size)}
        $line = fgets($this->fp);
        if (preg_match('/RFC822 \{(\d+)\}/', $line, $matches) && isset($matches[1])) {
            // Copy the message to the file
            stream_copy_to_stream($this->fp, $fh, $matches[1]);
        } else {
            $this->errors[] = 'Unexpected response from the IMAP server:';
            $this->errors[] = hesk_htmlspecialchars($line);
            return false;
        }

        fclose($fh);

        // Read the rest of the response
        $response = $this->readResponse("A" . $this->tagCounter);

        if (isset($response[0][0]) && $response[0][0] == "OK") {
            return true;
        }

        $this->errors[] = 'Unexpected response from the IMAP server:';
        $this->errors[] = hesk_htmlspecialchars(implode('', $response));
        return false;
    }



    /**
     * Returns mails array
     * @param integer $from
     * @param integer $to
     * @retun Array
     */
    public function getMessages($from, $to) {
        $this->writeCommannd("A" . $this->tagCounter, "FETCH $from:$to RFC822");
        $response = $this->readResponse("A" . $this->tagCounter);
        return $this->modifyResponse($response);
    }

    /**
	* Search in FROM ie. Email Address
	* @param string $email
	* @return Array
	*/
	public function searchFrom($email) {
		$this->writeCommannd("A" . $this->tagCounter, "SEARCH FROM " . $this->escapeString($email));
		$response = $this->readResponse("A" . $this->tagCounter);
		//Fetch by ids got in response
		$ids = explode(" ", trim($response[0][1]));
		unset($ids[0]);
		unset($ids[1]);
		$ids = array_values($ids);
		$stringIds = implode(",",$ids);
		$mails = $this->getMessage($stringIds);
		return $mails;
	}
	
    /**
     * Selects inbox for further operations
     * @param boolean $readOnly
     */
    private function selectInbox($readOnly = false) {
        if ($readOnly) {
            $this->writeCommannd("A" . $this->tagCounter, "EXAMINE INBOX");
        } else {
            $this->writeCommannd("A" . $this->tagCounter, "SELECT INBOX");
        }
        $this->readResponse("A" . $this->tagCounter);
    }

    /**
     * List all folders
     * @return Array
     */
    public function listFolders() {
        $this->writeCommannd("A" . $this->tagCounter, "LIST \"\" \"*\"");
        $response = $this->readResponse("A" . $this->tagCounter);
        $line = $response[0][1];
        $statusString = explode("*", $line);

        $totalStrings = count($statusString);

        $statusArray = Array();
        $finalFolders = Array();

        for ($i = 1; $i < $totalStrings; $i++) {

            $statusArray[$i] = explode("\"/\" ", $statusString[$i]);

            if (!strpos($statusArray[$i][0], "\Noselect")) {
                $folder = str_replace("\"", "", $statusArray[$i][1]);
                array_push($finalFolders, $folder);
            }
        }

        return $finalFolders;
    }

    /**
     * Examines the folder
     * @param string $folder
     * @param boolean $readOnly
     * @return boolean
     */
    public function selectFolder($folder = "INBOX", $readOnly = false) {
        if ($readOnly) {
            $this->writeCommannd("A" . $this->tagCounter, "EXAMINE " . $this->escapeString($folder));
        } else {
            $this->writeCommannd("A" . $this->tagCounter, "SELECT " . $this->escapeString($folder));
        }
        $response = $this->readResponse("A" . $this->tagCounter);
        if (isset($response[0][0]) && $response[0][0] == "OK") {
            return true;
        }
        return false;
    }

    /**
     * Returns number of emails in a folder
     * @param string $folder
     * @retun integer
     */
    public function totalMails($folder = "INBOX") {
        $this->writeCommannd("A" . $this->tagCounter, "STATUS " . $this->escapeString($folder) . " (MESSAGES)");
        $response = $this->readResponse("A" . $this->tagCounter);

        $line = $response[0][1];
        $splitMessage = explode("(", $line);
        $splitMessage[1] = str_replace("MESSAGES ", "", $splitMessage[1]);
        $count = str_replace(")", "", $splitMessage[1]);

        return intval(trim($count));
    }

    /**
     * Returns number of unseen emails in a folder
     * @param string $folder
     * @retun integer
     */
    public function totalUnseenMails($folder = "INBOX") {
        $this->writeCommannd("A" . $this->tagCounter, "STATUS " . $this->escapeString($folder) . " (UNSEEN)");
        $response = $this->readResponse("A" . $this->tagCounter);

        $line = $response[0][1];
        $splitMessage = explode("(", $line);
        $splitMessage[1] = str_replace("UNSEEN ", "", $splitMessage[1]);
        $count = str_replace(")", "", $splitMessage[1]);

        return intval(trim($count));
    }

    /**
    * The APPEND command appends the literal argument as a new message
    *  to the end of the specified destination mailbox
    *
    * @param string $mailbox MANDATORY
    * @param string $message MANDATORY
    * @param string $flags OPTIONAL DEFAULT "(\Seen)"
    * @param string $from OPTIONAL
    * @param string $to OPTIONAL
    * @param string $subject OPTIONAL
    * @param string $messageId OPTIONAL DEFAULT uniqid()
    * @param string $mimeVersion OPTIONAL DEFAULT "1.0"
    * @param string $contentType OPTIONAL DEFAULT "TEXT/PLAIN;CHARSET=UTF-8"
    *
    * @return bool false if mandatory params are not set or empty or if command execution fails, otherwise true
    */
    public function appendMessage($mailbox, $message, $from = "", $to = "", $subject = "", $messageId = "", $mimeVersion = "", $contentType = "", $flags = "(\Seen)")
    {
        if (!isset($mailbox) || !strlen($mailbox)) return false;
        if (!isset($message) || !strlen($message)) return false;
        if (!strlen($flags)) return false;

        $date = date('d-M-Y H:i:s O');
        $crlf = "\r\n";

        if (strlen($from)) $from = "From: $from";
        if (strlen($to)) $to = "To: $to";
        if (strlen($subject)) $subject = "Subject: $subject";
        $messageId = (strlen($messageId)) ? "Message-Id: $messageId" : "Message-Id: " . uniqid();
        $mimeVersion = (strlen($mimeVersion)) ? "MIME-Version: $mimeVersion" : "MIME-Version: 1.0";
        $contentType = (strlen($contentType)) ? "Content-Type: $contentType" : "Content-Type: TEXT/PLAIN;CHARSET=UTF-8";

        $composedMessage = $date . $crlf;
        if (strlen($from)) $composedMessage .= $from . $crlf;
        if (strlen($subject)) $composedMessage .= $subject . $crlf;
        if (strlen($to)) $composedMessage .= $to . $crlf;
        $composedMessage .= $messageId . $crlf;
        $composedMessage .= $mimeVersion . $crlf;
        $composedMessage .= $contentType . $crlf . $crlf;
        $composedMessage .= $message . $crlf;

        $size = strlen($composedMessage);

        $command = "APPEND " . $this->escapeString($mailbox) . " $flags {" . $size . "}" . $crlf . $composedMessage;

        $this->writeCommannd("A" . $this->tagCounter, $command);
        $response = $this->readResponse("A" . $this->tagCounter);

        if (isset($response[0][0]) && $response[0][0] == "OK") return true;

        return false;
    }

    /**
     * Write's to file pointer
     * @param string $tag
     * @param string $command
     */
    private function writeCommannd($code, $command) {
        fwrite($this->fp, $code . " " . $command . "\r\n");
        if ($this->debug)
            echo $code . " " . $command . "\r\n";
    }

    /**
     * Reads response from file pointer, parse it and returns response array
     * @param string $code
     * @return Array
     */
    private function readResponse($code, $removeFlags = false, $isLogin = false) {
        $response = Array();

        $i = 1;
        // $i = 1, because 0 will be status of response
        // Position 0 server reply two dimentional
        // Position 1 message

        while ($line = fgets($this->fp)) {
            if ($this->debug)
                echo $line;

            // Did we get an error response after login? We need to send an empty response
            // Ref: https://developers.google.com/gmail/imap/xoauth2-protocol
            if ($isLogin && $line[0] == '+') {
                if (($str = base64_decode(substr($line, 2)))) {
                    $this->errors[] = $str;
                }
                $this->writeCommannd("L1", "");
            }

            // TODO: find a better way to remove FLAGS() from the message
            if ($removeFlags && strncmp($line, " FLAGS (", 8) === 0) {
                if ($this->debug)
                    echo "Removing FLAGS from message\n";

                continue;
            }

            $checkLine = preg_split('/\s+/', $line, 3, PREG_SPLIT_NO_EMPTY);
            if (@$checkLine[0] == $code) {
                $response[0][0] = $checkLine[1];
                if (isset($checkLine[2]))
                    $response[0][2] = trim($checkLine[2]);
                break;
            } else if (@$checkLine[0] != "*") {
                if (isset($response[1][$i]))
                    $response[1][$i] = $response[1][$i] . $line;
                else
                    $response[1][$i] = $line;
            }
            if (@$checkLine[0] == "*") {
                if (isset($response[0][1]))
                    $response[0][1] = $response[0][1] . $line;
                else
                    $response[0][1] = $line;
                if (isset($response[1][$i])) {
                    $i++;
                }
            }
        }
        $this->tagCounter++;
        return $response;
    }

    /**
     * If response is OK then removes server response status messages else returns the original response
     * @param Array $response
     * @return Array
     */
    private function modifyResponse($response) {
        if (isset($response[0][0]) && $response[0][0] == "OK") {
            $modifiedResponse = $response[1];
            return $modifiedResponse;
        }

        return $response;
    }

    /**
     * Escape string for the request
     * @param  string $string 
     * @return string escape literals
     */
    public function escapeString($string)
    {
        if (strpos($string, "\n") !== false) {
            return ['{' . strlen($string) . '}', $string];
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $string) . '"'; //'
    }

    /**
     * Sends an IMAP command
     * @param string $command
     * @return Array
     */
    public function manualCommand($command, $returnResponse = true) {
		$this->writeCommannd("A" . $this->tagCounter, $command);
		$response = $this->readResponse("A" . $this->tagCounter);

        if ($returnResponse) {
            return $response;
        }
    }

    /**
     * Deletes emails marked with the \Deleted flag
     */
    public function expunge() {
		$this->writeCommannd("A" . $this->tagCounter, "EXPUNGE");
		$response = $this->readResponse("A" . $this->tagCounter);

        if (isset($response[0][0]) && $response[0][0] == "OK") { //Got Successful response
            return true;
        }

        return false;
    }

    /**
     * Adds \Seen flag to a message
     * @param integer $id
     */
    public function setSeen($id) {
		$this->writeCommannd("A" . $this->tagCounter, "STORE {$id} +FLAGS (\\Seen)");
		$response = $this->readResponse("A" . $this->tagCounter);
    }

    /**
     * Removes \Seen flag from a message
     * @param integer $id
     */
    public function setUnseen($id) {
		$this->writeCommannd("A" . $this->tagCounter, "STORE {$id} -FLAGS (\\Seen)");
		$response = $this->readResponse("A" . $this->tagCounter);
    }

    /**
     * Deletes a message (actually we're adding a \Deleted flag to it)
     * @param integer $id
     */
    public function delete($id) {
		$this->writeCommannd("A" . $this->tagCounter, "STORE {$id} +FLAGS (\\Deleted)");
		$response = $this->readResponse("A" . $this->tagCounter);
    }

    /**
     * Gets a list of message IDs of unseen messages
     * @return Array
     */
	public function getUnseenMessageIDs() {
        $this->writeCommannd("A" . $this->tagCounter, "SEARCH UNSEEN");
        $response = $this->readResponse("A" . $this->tagCounter);

        //Fetch by ids got in response
        $ids = explode(" ", trim($response[0][1]));
        unset($ids[0]);
        unset($ids[1]);
        $ids = array_values($ids);
        return $ids;
	}

    /**
     * Return IMAP errors
     * @return Array
     */
    public function getErrors() {
        return $this->errors;
    }
}

