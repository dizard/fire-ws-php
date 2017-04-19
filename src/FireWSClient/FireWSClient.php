<?php

namespace FireWSClient;

use \FireWSClient\Exceptions\NameSpaceException;
use \FireWSClient\Exceptions\AuthException;
use \FireWSClient\Exceptions\ConnectException;

class FireWSClient
{
    /**
     * @var resource
     */
    protected $_socket;

    /**
     * Chunk read max size bytes
     * @var int
     */
    protected $_chunkReadLimit = 1024;

    protected $_sKey;

    /**
     * WS constructor.
     * For example TCP socket tcp://127.0.0.1:8085 or unix socket
     *
     * @param $remote_socket
     * @throws ConnectException
     */
    public function __construct($remote_socket)
    {
        if (!$this->_socket = $socket = stream_socket_client($remote_socket, $errno, $errstr, 4)) {
            throw new ConnectException(iconv('windows-1251', 'UTF-8', $errstr), $errno);
        }
        register_shutdown_function(function () use($socket) {
            fclose($socket);
        });
    }

    /**
     * Generate auth string for auth on WS server
     *
     * @param $userId
     * @return string
     * @throws AuthException
     */
    public static function generateAuthString($userId, $sKey) {
        if ($userId===null || $sKey===null) {
            throw new AuthException('Need user id');
        }
        return JWT::encode($userId, $sKey);
    }

    /**
     * Register NameSpace and return secretKey
     * $key - secret key for control LaWS
     *
     * @param $name
     * @return string
     * @throws NameSpaceException
     */
    public function registerNameSpace($name, $key) {
        $res = $this->query([
            'action' => 'registerNameSpace',
            'name' => $name,
            'key' => $key
        ]);
        if ($res['success']) {
            return $res['secretKey'];
        }
        throw new NameSpaceException($res['reason'], $res['code']);
    }

    /**
     * @param $nameSpace
     * @param $sKey
     * @return $this
     * @throws NameSpaceException
     */
    public function auth($nameSpace, $sKey) {
        $this->_sKey = $sKey;

        $res = $this->query([
            'action' => 'auth',
            'name'  => $nameSpace,
            'sKey' => $sKey
        ]);
        if (!$res['success']) {
            throw new NameSpaceException($res['reason'], $res['code']);
        }

        return $this;
    }

    /**
     * Send message to channel
     *
     * @param $channel
     * @param $data
     * @param null $user_id
     * @return mixed
     */
    public function send($channel, $data, $user_id=null) {
        return $this->query([
            'action' => 'emit',
            'channel'  => $channel,
            'data' => $data,
            'params' => [
                'userId' => $user_id
            ]
        ]);
    }

    /**
     * Set base state for channel
     *
     * @param $channel
     * @param $data
     * @param null $user_id
     * @param null $ttl
     * @return mixed
     */
    public function set($channel, $data, $user_id=null, $ttl=null) {
        return $this->query([
            'action' => 'set',
            'channel'  => $channel,
            'data' => $data,
            'params' => [
                'userId' => $user_id,
                'emit' => false,
                'ttl' => $ttl
            ]
        ]);
    }

    /**
     * Set base state and send message
     *
     * @param $channel
     * @param $data
     * @param null $user_id
     * @param null $ttl
     * @return mixed
     */
    public function setAndSend($channel, $data, $user_id=null, $ttl=null) {
        $res = $this->query([
            'action' => 'set',
            'channel'  => $channel,
            'data' => $data,
            'params' => [
                'userId' => $user_id,
                'emit' => true,
                'ttl' => $ttl
            ]
        ]);
        return $res['success'];
    }

    /**
     * Push to channel
     *
     * @param $channel
     * @param $data
     * @param null $user_id
     * @param null $ttl
     * @return mixed
     */
    public function push($channel, $data, $user_id=null, $ttl=null) {
        return $this->query([
            'action' => 'push',
            'channel'  => $channel,
            'data' => $data,
            'params' => [
                'userId' => $user_id,
                'emit' => false,
                'ttl' => $ttl
            ]
        ]);
    }

    /**
     * Push base state and send message
     *
     * @param $channel
     * @param $data
     * @param null $user_id
     * @param null $ttl
     * @return mixed
     */
    public function pushAndSend($channel, $data, $user_id=null, $ttl=null) {
        $res = $this->query([
            'action' => 'push',
            'channel'  => $channel,
            'data' => $data,
            'params' => [
                'userId' => $user_id,
                'emit' => true,
                'ttl' => $ttl
            ]
        ]);
        return $res['success'];
    }

    /**
     * Subscribe to private channel
     * Private channel start of symbol #
     *
     * Example #privatechannel
     *
     * @param string $channel
     * @param string $userId
     * @return bool
     */
    public function subscribe($channel, $userId) {
        if ($channel[0]!=='#') {
            return false;
        }

        $res =  $this->query([
            'action' => 'subscribe',
            'channel'  => $channel,
            'params' => [
                'userId' => $userId
            ]
        ]);
        return $res['success'];
    }

    /**
     * Unsubscribe from private channel
     * Private channel start of symbol #
     *
     * Example #privatechannel
     *
     * @param $channel
     * @param $userId
     * @return bool
     */
    public function unsubscribe($channel, $userId) {
        if ($channel[0]!=='#') {
            return false;
        }

        $res = $this->query([
            'action' => 'unsubscribe',
            'channel'  => $channel,
            'params' => [
                'userId' => $userId
            ]
        ]);
        return $res['success'];
    }

    /**
     * Return base state
     *
     * @param $channel
     * @param null $user_id
     * @return mixed
     */
    public function get($channel, $user_id=null) {
        return $this->query([
            'action' => 'get',
            'channel'  => $channel,
            'params' => [
                'userId' => $user_id
            ]
        ]);
    }

    /**
     * Return channel Info
     *
     * @param $channel
     * @return array
     */
    public function channelInfo($channel) {
        return $this->query([
            'action' => 'channelInfo',
            'channel'  => $channel
        ]);
    }




    protected function query($data)
    {
        $data = json_encode($data);
        $finalPacket = pack('V', strlen($data)) . $data . "\x00";
        fwrite($this->_socket, $finalPacket);

        // Читаем ответ
        $size_data = fread($this->_socket, 4);
        $size_pack = unpack("V1size", $size_data);
        $needData = $size_pack['size'];

        $answer = '';
        $chunkLimit = 1024;
        if ($needData > $chunkLimit) {
            $chunkSize = $this->_chunkReadLimit;
            while ($packet_data = fread($this->_socket, $chunkSize)) {
                $needData -= $chunkSize;
                $chunkSize = $needData;
                if ($chunkSize > $this->_chunkReadLimit) $chunkSize = $this->_chunkReadLimit;


                $answer .= $packet_data;
                if ($needData <= 0) break;
            }
        } else {
            $answer .= fread($this->_socket, $needData);
        }
        fread($this->_socket, 1);
        return json_decode($answer, true);
    }

}
