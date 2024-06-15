<?php

namespace App\Services;

class bbgClientApi
{

    const SERVER_URL = 'https://bbgl.ru/b2bapi/1.1/';

    private $_email;
    private $_password;

    public function __construct($email, $password){

        $this->_email = $email;
        $this->_password = $password;
    }

    public function call($method, $params = []){

        $url = self::SERVER_URL;
        $curl = curl_init();

        $query = [];
        $query['method'] = $method;
        $query['email'] = $this->_email;
        $query['password'] = $this->_password;
        $query['params'] = $params;

        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($query));

        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Connection: keep-alive'
        ]);

        $result = curl_exec($curl);
        $result = json_decode($result, true);

        curl_close($curl);

        return $result;
    }
}
