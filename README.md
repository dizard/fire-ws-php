# FireWs-API-PHP

composer require dizard/fire-ws-php

**Example**
```php
use FireWSClient\FireWSClient;
$nameSpace = 'nameYouNamePace';
$userId = 5;
$ttl = 60;

try{
    $Client = new FireWSClient('tcp://127.0.0.1:8085');
    $sKeyForNameSpace = $Client->registerNameSpace($nameSpace,'secretKeyForServer');

    $Client->auth($nameSpace, $sKeyForNameSpace);
    $Client->send('channel', ['message' => 'hello world']);

    // Send Message for user with id 5
    $Client->send('@channel', ['message' => 'hello world'], $userId);

    // Send base state for channel
    // the state send user after subscribe there channel
    $Client->setAndSend('channel', ['message' => 'hello world']);
    // the state send user after subscribe there channel
    $Client->setAndSend('@channel', ['message' => 'hello world'], $userId);
    // the state send user after subscribe there channel with lifetime $ttl
    $Client->setAndSend('@channel', ['message' => 'hello world'], $userId, $ttl);

    // get base state channel
    $stateChannel = $Client->get('channel');

    // get base state channel for user with $userId
    $stateChannel = $Client->get('@channel', $userId);

    $channelInf = $Client->channelInfo('channel');
}catch(\FireWSClient\Exceptions\FireWsException $e) {

}

```
