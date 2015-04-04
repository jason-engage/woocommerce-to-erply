<?php

class EAPI {

    public $url;
    public $clientCode;
    public $username;
    public $password;

    // Sends POST request to API
    public function sendRequest($request, $parameters = array()){

        // Check if all nessecary parameters are set up
        if(!$this->url OR !$this->clientCode OR !$this->username OR !$this->password)
            return false;

        // Include clientcode and request name to POST parameters
        $parameters['request'] = $request;
        $parameters['version'] = "1.0";
        $parameters['clientCode'] = $this->clientCode;

        // Get session KEY
        if($request != "verifyUser"){

            $parameters['sessionKey'] = $this->getSessionKey($keyRequestResult);

            // Instead of a KEY we got an array which contains error code, let's return in
            if(!$parameters['sessionKey'])
                return $keyRequestResult;
        }

        // Prepare POST request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_HEADER,1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST,true);
        curl_setopt($ch, CURLOPT_SSLVERSION, 3);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);

        // call POST request
        if(curl_exec($ch) === false)
            return false;

        // get response content
        $content = curl_multi_getcontent($ch);
        curl_close($ch);

        // remove heders
        list($header1,$header2,$body) = explode("\r\n\r\n",$content,3);

        // return response body
        return $body;

    }

    private function getSessionKey(&$result) {

        // Session KEY is active, return active KEY
        if(isset($_SESSION['EAPISessionKey'][$this->username]) AND $_SESSION['EAPISessionKey'][$this->username] AND $_SESSION['EAPISessionKeyExpires'][$this->username] > time())
            return $_SESSION['EAPISessionKey'][$this->username];

        // New session KEY must be obtained
        else {

            // Perform API request to get session KEY
            $result = $this->sendRequest("verifyUser", array("username" => $this->username, "password" => $this->password) );

            // JSON response into PHP array
            $response = json_decode($result, true);

			// If user has been changed
            if (!isset($response['records'][0]['sessionKey'])) {
                unset($_SESSION['EAPISessionKey'][$this->username]);
                return false;
            }

            // Session KEY was successfully received
            if($response['records'][0]['sessionKey']) {

                // Set session KEY in client session and set KEY expiration time
                $_SESSION['EAPISessionKey'][$this->username] =
                        $response['records'][0]['sessionKey'];
                $_SESSION['EAPISessionKeyExpires'][$this->username] =
                        time() + $response['records'][0]['sessionLength'] - 30;

                // Return obtained new session KEY
                return $_SESSION['EAPISessionKey'][$this->username];
            }

            // Session KEY was not received
            else {

                // Return API response which includes error code
                unset($_SESSION['EAPISessionKey'][$this->username]);
                return false;
            }
        }
    }
}

?>