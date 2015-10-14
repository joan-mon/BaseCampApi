<?php

/*
 * This is the component fot Yii 1 that allows to interact with the BaseCamp API easily
 */

class BaseCampAPI extends CApplicationComponent
{

    const PRODUCTION_URL = "http://api.bcamp.es/v1/";
    const SANDBOX_URL = "http://api.basecamp.localhost/v1/";

    var $app_key;
    var $app_secret;
    var $environment;

    public function init()
    {
        parent::init();
        $this->app_key = Yii::app()->params['basecampConfig']['app_key'];
        $this->app_secret = Yii::app()->params['basecampConfig']['app_secret'];
        $this->environment = Yii::app()->params['basecampConfig']['environment'];
    }

    private function getUrl() {
        switch ( $this->environment ) {
            case "sandbox":
                return self::SANDBOX_URL;
                break;
            case "production":
                return self::PRODUCTION_URL;
                break;
        }
    }

    /******* USER MANAGEMENT SECTION *******/

    function doLogin($email, $password, $session_duration_in_seconds = 3600)
    {

        // Set the endpoint
        $url = self::getUrl()."users/login";

        //set POST variables
        $fields = array(
            'email' => $email,
            'password' => $password,
            'duration_in_seconds' => $session_duration_in_seconds
        );

        // Do the request
        $result = self::_doRequest($url, $fields);

        // Return true if we have a token
        if ( $result['sucess'] && isset ($result) && $result ) {
            return $result['result']->sessionId;
        } else {
            return false;
        }

    }

    function doLogout($sessionId = null)
    {
        // Set the endpoint
        $url = self::getUrl()."users/logout";
        $fields = array();

        // Prepare the info for the submit to the API
        $fields_string = json_encode($fields);

        //open connection
        $ch = curl_init();

        // Create the headers array
        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($fields_string);
        $headers[] = 'AppKey: '.$this->app_key;

        if ( $sessionId == null && isset( $_SESSION['sessionId']))
            $headers[] = 'sessionId: '.$_SESSION['sessionId'];
        else
            $headers[] = 'sessionId: '.$sessionId;

        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        //execute post
        curl_exec($ch);

        $prefix=Yii::app()->user->getStateKeyPrefix();
        unset($_SESSION[$prefix."__userInfo"]);
        unset($_SESSION[$prefix."__id"]);
        unset($_SESSION[$prefix."__name"]);

    }

    function getUserInfo($sessionId)
    {

        // Set the endpoint
        $url = self::getUrl()."users/info";

        //set POST variables
        $fields = array();

        // Do the request and return the result
        return self::_doRequest($url, $fields, $sessionId);

    }

    function registerUser($email, $password, $phone, $name, $role = 10, $params=null)
    {

        // Set the endpoint
        $url = self::getUrl()."users/register";

        //set POST variables
        $fields = array(
            'email' => $email,
            'password' => $password,
            'phone' => $phone,
            'first_name' => $name,
            'role' => $role
        );
        if ( $params != null )
            $fields['extra_params'] = json_encode($params);

        // Do the request and return the result
        return self::_doRequest($url, $fields);

    }

    function sendTemplate($template_name, $fields)
    {

        // Set the endpoint
        $url = self::getUrl()."users/sendTemplate";

        $fields['template_name'] = $template_name;

        // Do the request and return the result
        return self::_doAdminRequest($url, $fields);

    }

    function updateUser($sessionId, $values)
    {

        // Set the endpoint
        $url = self::getUrl()."users/update";

        // Do the request and return the result
        return self::_doRequest($url, $values, $sessionId);

    }

    /******* FILE MANAGEMENT SECTION *******/

    private function getFileType($file) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        // First of all, check if is an image
        return explode("/",finfo_file($finfo,$file))[1];
    }

    /**
     * Send a file to BaseCamp Service API
     * @objectId string Is the unique id that codifies the object
     * @objectId string The local path where is the file to be sent
     * @values array Not mandatory values passed into the array
     */
    function sendFile($objectId, $file_local_path, $values = array())
    {

        // $values contains:
        // name, alt, src and collection

        ini_set('memory_limit', '512M');

        // Check the file exists
        if ( ! file_exists($file_local_path))
            return false;

        // Check file size > 0
        if ( filesize($file_local_path) == 0 )
            return false;

        $path = $file_local_path;
        $data = file_get_contents($path, true);
        $base64 = base64_encode($data);

        $values["file"] = $base64;
        $values["type"] = self::getFileType($file_local_path);

        $values["objectId"] = $objectId;

        // Set the endpoint
        $url = self::getUrl()."files/saveimage";

        // Do the request and return the result
        return self::_doAdminRequest($url, $values);

    }

    /**
     * Gather a file from BaseCamp Service API
     * @objectId is the objectId of the object you are requesting
     */
    function getFiles($objectId)
    {

        ini_set('memory_limit', '512M');

        // Set the endpoint
        $url = self::getUrl()."files/get";

        // Do the request and return the result
        return self::_doAdminRequest($url, array("objectId" => $objectId));

    }

    /**
     * Delete a file from BaseCamp Service API using the SRC
     * @src is the source of the file that you are deleting
     */
    function deleteFile($src)
    {

        ini_set('memory_limit', '512M');

        // Set the endpoint
        $url = self::getUrl()."files/deletefile";

        // Do the request and return the result
        return self::_doAdminRequest($url,["src" => $src]);

    }

    /******* DO NOT EDIT * PRIVATE FUNCTIONS SECTION * DO NOT EDIT *******/

    private function _doRequest($url, $fields, $sessionId = null)
    {
        // Prepare the info for the submit to the API
        $fields_string = json_encode($fields);

        //open connection
        $ch = curl_init();

        // Create the headers array
        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($fields_string);
        $headers[] = 'AppKey: '.$this->app_key;
        if ($sessionId == null && isset( $_SESSION['sessionId']))
            $headers[] = 'sessionId: '.$_SESSION['sessionId'];
        else
            $headers[] = 'sessionId: '.$sessionId;

        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        //execute post
        $result = curl_exec($ch);

        if(!curl_errno($ch))
        {
            $info = curl_getinfo($ch);

            curl_close($ch);

            if (empty($info['http_code'])) {
                return false;
            } else {
              if ( intval($info['http_code']) < 300 ) {
                  return ["success" => true, "result" => json_decode($result)];
              } else {
                  return ["success" => false, "result" => json_decode($result)];
              }
            }

        } else {
            curl_close($ch);
            return false;
        }

    }

    private function _doAdminRequest($url, $fields)
    {
        // Prepare the info for the submit to the API
        $fields_string = json_encode($fields);

        //open connection
        $ch = curl_init();

        // Create the headers array
        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($fields_string);
        $headers[] = 'AppKey: '.$this->app_key;
        $headers[] = 'AppSecret: '.$this->app_secret;

        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        //execute post
        $result = curl_exec($ch);

        if(!curl_errno($ch))
        {
            $info = curl_getinfo($ch);

            curl_close($ch);

            if (empty($info['http_code'])) {
                return false;
            } else {
                if ( intval($info['http_code']) < 300 ) {
                    return ["success" => true, "result" => json_decode($result)];
                } else {
                    return ["success" => false, "result" => json_decode($result)];
                }
            }

        } else {
            curl_close($ch);
            return false;
        }

    }

}
