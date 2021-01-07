#!/usr/bin/env php
<?php

set_include_path(get_include_path().':'.realpath(dirname(__FILE__).'/madelineproto/'));

if (!file_exists(__DIR__.'/vendor/autoload.php')) {
    echo 'You did not run composer update, using madeline.php'.PHP_EOL;
    if (!file_exists('madeline.php')) {
        copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
    }
    include 'madeline.php';
} else {
    require_once 'vendor/autoload.php';
}

echo 'Deserializing madelineproto from session.madeline...'.PHP_EOL;

use danog\madelineproto\Loop\Impl\ResumableSignalLoop;

class MessageLoop extends ResumableSignalLoop
{
    const INTERVAL = 1;
    private $timeout;
    private $call;

    public function __construct($API, $call, $timeout = self::INTERVAL)
    {
        $this->API = $API;
        $this->call = $call;
        $this->timeout = $timeout;
    }

    public function loop()
    {
        $madelineproto = $this->API;
        $logger = &$madelineproto->logger;

        while (true) {
            $result = yield $this->waitSignal($this->pause($this->timeout));
            if ($result) {
                $logger->logger("Got signal in $this, exiting");

                return;
            }

            try {
                if ($madelineproto->jsonmoseca != $madelineproto->nowPlaying('jsonclear')) { //anti-floodwait

                    yield $madelineproto->messages->editMessage(['id' => $this->call->mId, 'peer' => $this->call->getOtherID(), 'message' => 'Stai ascoltando: <b>'.$madelineproto->nowPlaying()[1].'</b>  '.$madelineproto->nowPlaying()[2].'<br> Tipo: <i>'.$madelineproto->nowPlaying()[0].'</i>', 'parse_mode' => 'html']);
                    //anti-floodwait
                    $madelineproto->jsonmoseca = $madelineproto->nowPlaying('jsonclear');
                }
            } catch (\danog\madelineproto\Exception | \danog\madelineproto\RPCErrorException $e) {
                $logger->logger($e);
            }
        }
    }

    public function __toString(): string
    {
        return 'VoIP message loop '.$this->call->getOtherId();
    }
}
class StatusLoop extends ResumableSignalLoop
{
    const INTERVAL = 2;
    private $timeout;
    private $call;

    public function __construct($API, $call, $timeout = self::INTERVAL)
    {
        $this->API = $API;
        $this->call = $call;
        $this->timeout = $timeout;
    }

    public function loop()
    {
        $madelineproto = $this->API;
        $logger = &$madelineproto->logger;
        $call = $this->call;

        while (true) {
            $result = yield $this->waitSignal($this->pause($this->timeout));
            if ($result) {
                $logger->logger("Got signal in $this, exiting");

                return;
            }

            //  \danog\madelineproto\Logger::log(count(yield $madelineproto->getEventHandler()->calls).' calls running!');

            if ($call->getCallState() === \danog\madelineproto\VoIP::CALL_STATE_ENDED) {
                try {
                    yield $madelineproto->messages->sendMessage(['no_webpage' => true, 'peer' => $call->getOtherID(), 'message' => "مطور الفكرة : @J_69_L", 'parse_mode' => 'html']);
                } catch (\danog\madelineproto\Exception $e) {
                    $logger->logger($e);
                } catch (\danog\madelineproto\RPCErrorException $e) {
                    $logger->logger($e);
                }
                @unlink('/tmp/logs'.$call->getCallID()['id'].'.log');
                @unlink('/tmp/stats'.$call->getCallID()['id'].'.txt');
                $madelineproto->getEventHandler()->cleanUpCall($call->getOtherID());

                return;
            }
        }
    }

    public function __toString(): string
    {
        return 'VoIP status loop '.$this->call->getOtherId();
    }
}

class EventHandler extends \danog\madelineproto\EventHandler
{
    const ADMINS = [996310583]; // @J_69_L
    private $messageLoops = [];
    private $statusLoops = [];
    private $programmed_call;
    private $my_users;
    public $calls = [];
    public $jsonmoseca = '';

    public function nowPlaying($returnvariable = null)
    {
        $url = 'https://icstream.rds.radio/status-json.xsl';  //vekkio http://stream1.rds.it:8000/status-json.xsl
        $jsonroba = file_get_contents($url);
        $jsonclear = json_decode($jsonroba, true);
        $metadata = explode('*', $jsonclear['icestats']['source'][16]['title']);

        if ($returnvariable == 'jsonclear') {
            return $jsonclear['icestats']['source'][16]['title'];
        }

        return $metadata;
    }

    public function configureCall($call)
    {
        $icsd = date('U');

        shell_exec('mkdir streams');

        file_put_contents('omg.sh', "#!/bin/bash \n mkfifo streams/$icsd.raw");

        file_put_contents('figo.sh', '#!/bin/bash'." \n".'ffmpeg -i https://manifest.googlevideo.com/api/manifest/hls_variant/expire/1610050848/ei/wBj3X4eOB8nX1wbL6IHADg/ip/5.0.68.143/id/36YnV9STBqc.1/source/yt_live_broadcast/requiressl/yes/tx/23975649/txs/23975649%2C23975650%2C23975651%2C23975652%2C23975653%2C23975654/hfr/1/playlist_duration/30/manifest_duration/30/maudio/1/vprv/1/go/1/nvgoi/1/keepalive/yes/dover/11/itag/0/playlist_type/DVR/sparams/expire%2Cei%2Cip%2Cid%2Csource%2Crequiressl%2Ctx%2Ctxs%2Chfr%2Cplaylist_duration%2Cmanifest_duration%2Cmaudio%2Cvprv%2Cgo%2Citag%2Cplaylist_type/sig/AOq0QJ8wRQIhANlFa4rNrdtPGKWlwFFLbDz1w37fpY_Ni8s0Oi8XryCVAiAM1xMlOmuEgRba-c7YzovhPdGWlzH_MW6BGHfMsXDHsQ%3D%3D/file/index.m3u8 -vn -f s16le -ac 1 -ar 48000 -acodec pcm_s16le pipe:1 > streams/'."$icsd.raw"); //https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=2606803

        shell_exec('chmod -R 0777 figo.sh omg.sh');

        shell_exec('./omg.sh');

        shell_exec("screen -S RDSstream$icsd -dm ./figo.sh");

        $call->configuration['enable_NS'] = false;
        $call->configuration['enable_AGC'] = false;
        $call->configuration['enable_AEC'] = false;
        $call->configuration['log_file_path'] = '/tmp/logs'.$call->getCallID()['id'].'.log'; // Default is /dev/null
        //$call->configuration["stats_dump_file_path"] = "/tmp/stats".$call->getCallID()['id'].".txt"; // Default is /dev/null
        $call->parseConfig();
        $call->playOnHold(["streams/$icsd.raw"]);
        if ($call->getCallState() === \danog\madelineproto\VoIP::CALL_STATE_INCOMING) {
            if (!$res = yield $call->accept()) { //$call->accept() === false
                $this->logger('DID NOT ACCEPT A CALL');
            }

            //trying to get the encryption emojis 5 times...
            $b00l = 0;
            while ($b00l < 5) {
                try {
                    $this->messages->sendMessage(['peer' => $call->getOtherID(), 'message' => 'Emojis: '.implode('', $call->getVisualization())]);
                    $b00l = 5;
                } catch (\danog\madelineproto\Exception $e) {
                    $this->logger($e);
                    $b00l++;
                }
            }
        }
        if ($call->getCallState() !== \danog\madelineproto\VoIP::CALL_STATE_ENDED) {
            $this->calls[$call->getOtherID()] = $call;

            try {
                $call->mId = yield $this->messages->sendMessage(['no_webpage' => true, 'peer' => $call->getOtherID(), 'message' => 'Stai ascoltando: <b>'.$this->nowPlaying()[1].'</b>  '.$this->nowPlaying()[2].'<br> Tipo: <i>'.$this->nowPlaying()[0].'</i>', 'parse_mode' => 'html'])['id'];
                $this->jsonmoseca = $this->nowPlaying('jsonclear');
            } catch (\Throwable $e) {
                $this->logger($e);
            }
            $this->messageLoops[$call->getOtherID()] = new MessageLoop($this, $call);
            $this->statusLoops[$call->getOtherID()] = new StatusLoop($this, $call);
            $this->messageLoops[$call->getOtherID()]->start();
            $this->statusLoops[$call->getOtherID()]->start();
        }
        //yield $this->messages->sendMessage(['message' => var_export($call->configuration, true), 'peer' => $call->getOtherID()]);
    }

    public function cleanUpCall($user)
    {
        if (isset($this->calls[$user])) {
            unset($this->calls[$user]);
        }
        if (isset($this->messageLoops[$user])) {
            $this->messageLoops[$user]->signal(true);
            unset($this->messageLoops[$user]);
        }
        if (isset($this->statusLoops[$user])) {
            $this->statusLoops[$user]->signal(true);
            unset($this->statusLoops[$user]);
        }
    }

    public function makeCall($user)
    {
        try {
            if (isset($this->calls[$user])) {
                if ($this->calls[$user]->getCallState() === \danog\madelineproto\VoIP::CALL_STATE_ENDED) {
                    yield $this->cleanUpCall($user);
                } else {
                    yield $this->messages->sendMessage(['peer' => $user, 'message' => 'Sono già in chiamata con te!']);

                    return;
                }
            }
            yield $this->configureCall(yield $this->requestCall($user));
        } catch (\danog\madelineproto\RPCErrorException $e) {
            try {
                if ($e->rpc === 'USER_PRIVACY_RESTRICTED') {
                    $e = 'Disattiva la privacy delle chiamate nelle impostazioni!';
                } elseif (strpos($e->rpc, 'FLOOD_WAIT_') === 0) {
                    $t = str_replace('FLOOD_WAIT_', '', $e->rpc);
                    $this->programmed_call[] = [$user, time() + 1 + $t];
                    $e = "Ti potrò chiamare tra $t secondi.\nSe vuoi puoi anche chiamarmi direttamente senza aspettare.";
                }
                yield $this->messages->sendMessage(['peer' => $user, 'message' => (string) $e]);
            } catch (\danog\madelineproto\RPCErrorException $e) {
            }
        } catch (\Throwable $e) {
            yield $this->messages->sendMessage(['peer' => $user, 'message' => (string) $e]);
        }
    }

    public function handleMessage($chat_id, $from_id, $message)
    {
        try {
            if (!isset($this->my_users[$from_id]) || $message === '/start') {
                $this->my_users[$from_id] = true;
                yield $this->messages->sendMessage(['no_webpage'                    => true, 'peer' => $chat_id, 'message' => "اهلا صديقي. الان صاحب الحساب غير موجود عندما يحضر سوف اخبره بأنك قمت بتكليمه @J_69_L '' @modzxdev.", 'parse_mode' => 'Markdown']);
            }

            if (!isset($this->calls[$from_id]) && $message === '/call') {
                yield $this->makeCall($from_id);
            }

            if (!isset($this->my_users[$from_id]) || $message === '/nowplaying') {
                $this->my_users[$from_id] = true;
                yield $this->messages->sendMessage(['no_webpage' => true, 'peer' => $chat_id, 'message' => '🔴Now: <b>'.$this->nowPlaying()[1].'</b>  '.$this->nowPlaying()[2].'<br> Tipo: <i>'.$this->nowPlaying()[0].'</i>', 'parse_mode' => 'html']);
            }

            if (!isset($this->my_users[$from_id]) || $message === 'gg') {
                $this->my_users[$from_id] = true;
                if(isset($this->calls[$from_id])){
                  $icsd2 = date('U');

                  shell_exec('mkdir streams');

                  file_put_contents('omg.sh', "#!/bin/bash \n mkfifo streams/$icsd2.raw");

                  file_put_contents('figo.sh', '#!/bin/bash'." \n".'ffmpeg -i https://radiom2o-lh.akamaihd.net/i/RadioM2o_Live_1@42518/master.m3u8 -vn -f s16le -ac 1 -ar 48000 -acodec pcm_s16le pipe:1 > streams/'."$icsd2.raw");

                  shell_exec('chmod -R 0777 figo.sh omg.sh');

                  shell_exec('./omg.sh');

                  shell_exec("screen -S M2Ostream$icsd2 -dm ./figo.sh");

                  $this->calls[$from_id]->playOnHold(["streams/$icsd2.raw"]);
                }else{
                  yield $this->messages->sendMessage(['no_webpage' => true, 'peer' => $chat_id, 'message' => "wats", 'parse_mode' => 'Markdown']);
                }
              }

            if (strpos($message, '/program') === 0) {
                $time = strtotime(str_replace('/program ', '', $message));
                if ($time === false) {
                    yield $this->messages->sendMessage(['peer' => $chat_id, 'message' => 'غير صالح الوقت المحدد']);
                } elseif ($time - time() <= 0) {
                    yield $this->messages->sendMessage(['peer' => $chat_id, 'message' => 'غير صالح الوقت المحدد']);
                } else {
                    yield $this->messages->sendMessage(['peer' => $chat_id, 'message' => 'OK']);
                    $this->programmed_call[] = [$from_id, $time];
                    $key = count($this->programmed_call) - 1;
                    yield \danog\madelineproto\Tools::sleep($time - time());
                    yield $this->makeCall($from_id);
                    unset($this->programmed_call[$key]);
                }
            }
            if ($message === '/broadcast' && in_array(self::ADMINS, $from_id)) {
                $time = time() + 100;
                $message = explode(' ', $message, 2);
                unset($message[0]);
                $message = implode(' ', $message);
                $params = ['multiple' => true];
                foreach (yield $this->getDialogs() as $peer) {
                    $params[] = ['peer' => $peer, 'message' => $message];
                }
                yield $this->messages->sendMessage($params);
            }
        } catch (\danog\madelineproto\RPCErrorException $e) {
            try {
                if ($e->rpc === 'USER_PRIVACY_RESTRICTED') {
                    $e = 'Disattiva la privacy delle chiamate nelle impostazioni!';
                } /*elseif (strpos($e->rpc, 'FLOOD_WAIT_') === 0) {
                    $t = str_replace('FLOOD_WAIT_', '', $e->rpc);
                    $e = "Too many people used the /call function. I'll be able to call you in $t seconds.\nYou can also call me right now";
                }*/
                yield $this->messages->sendMessage(['peer' => $chat_id, 'message' => (string) $e]);
            } catch (\danog\madelineproto\RPCErrorException $e) {
            }
            $this->logger($e);
        } catch (\danog\madelineproto\Exception $e) {
            $this->logger($e);
        }
    }

    public function onUpdateNewMessage($update)
    {
        if ($update['message']['out'] || $update['message']['to_id']['_'] !== 'peerUser' || !isset($update['message']['from_id'])) {
            return;
        }
        $this->logger->logger($update);
        $chat_id = $from_id = yield $this->getInfo($update)['bot_api_id'];
        $message = $update['message']['message'] ?? '';
        yield $this->handleMessage($chat_id, $from_id, $message);
    }

    public function onUpdateNewEncryptedMessage($update)
    {
        return;
        $chat_id = yield $this->getInfo($update)['InputEncryptedChat'];
        $from_id = yield $this->getSecretChat($chat_id)['user_id'];
        $message = isset($update['message']['decrypted_message']['message']) ? $update['message']['decrypted_message']['message'] : '';
        yield $this->handleMessage($chat_id, $from_id, $message);
    }

    public function onUpdateEncryption($update)
    {
        return;

        try {
            if ($update['chat']['_'] !== 'encryptedChat') {
                return;
            }
            $chat_id = yield $this->getInfo($update)['InputEncryptedChat'];
            $from_id = yield $this->getSecretChat($chat_id)['user_id'];
            $message = '';
        } catch (\danog\madelineproto\Exception $e) {
            return;
        }
        yield $this->handleMessage($chat_id, $from_id, $message);
    }

    public function onUpdatePhoneCall($update)
    {
        if (is_object($update['phone_call']) && isset($update['phone_call']->madeline) && $update['phone_call']->getCallState() === \danog\madelineproto\VoIP::CALL_STATE_INCOMING) {
            yield $this->configureCall($update['phone_call']);
        }
    }

    /*public function onAny($update)
    {
        $this->logger->logger($update);
    }*/

    public function __construct($API)
    {
        parent::__construct($API);
        $this->programmed_call = [];
        foreach ($this->programmed_call as $key => list($user, $time)) {
            continue;
            $sleepTime = $time <= time() ? 0 : $time - time();
            \danog\madelineproto\Tools::callFork((function () use ($sleepTime, $key, $user) {
                yield \danog\madelineproto\Tools::sleep($sleepTime);
                yield $this->makeCall($user);
                unset($this->programmed_call[$key]);
            })());
        }
    }

    public function __sleep()
    {
        return ['programmed_call', 'my_users'];
    }
}

if (!class_exists('\\danog\\madelineproto\\VoIPServerConfig')) {
    die("Installa l'estensione libtgvoip: https://voip.madelineproto.xyz".PHP_EOL);
}

\danog\madelineproto\VoIPServerConfig::update(
    [
        'audio_init_bitrate'      => 100 * 1000,
        'audio_max_bitrate'       => 100 * 1000,
        'audio_min_bitrate'       => 10 * 1000,
        'audio_congestion_window' => 4 * 1024,
    ]
);
$madelineproto = new \danog\madelineproto\API('session.madeline', ['secret_chats' => ['accept_chats' => false], 'logger' => ['logger' => 3, 'logger_level' => 5, 'logger_param' => getcwd().'/madelineproto.log'], 'updates' => ['getdifference_interval' => 10], 'serialization' => ['serialization_interval' => 30, 'cleanup_before_serialization' => true], 'flood_timeout' => ['wait_if_lt' => 86400]]);
foreach (['calls', 'programmed_call', 'my_users'] as $key) {
    if (isset($madelineproto->API->storage[$key])) {
        unset($madelineproto->API->storage[$key]);
    }
}

$madelineproto->async(true);
$madelineproto->loop(function () use ($madelineproto) {
    yield $madelineproto->start();
    yield $madelineproto->setEventHandler('\EventHandler');
});
$madelineproto->loop();
