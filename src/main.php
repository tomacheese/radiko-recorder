<?php
function DiscordSend($channel, $message, $embed = null)
{
    $Token = $_ENV["DISCORD_TOKEN"];
    $data = array(
        "content" => $message,
        "embed" => $embed
    );
    $header = array(
        "Content-Type: application/json",
        "Content-Length: " . strlen(json_encode($data)),
        "Authorization: Bot " . $Token,
        "User-Agent: DiscordBot (https://tomacheese.com, v0.0.1)"
    );

    $context = array(
        "http" => array(
            "method" => "POST",
            "header" => implode("\r\n", $header),
            "content" => json_encode($data),
            "ignore_errors" => true
        )
    );
    $context = stream_context_create($context);
    $contents = file_get_contents("https://discord.com/api/channels/" . $channel . "/messages", false, $context);
    preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
    $status_code = $matches[1];
    if ($status_code != 200) {
        echo $http_response_header[0] . "\n" . $contents;
    }
    sleep(1);
    $json = json_decode($contents, true);
    if (isset($json["id"])) {
        return $json["id"];
    } else {
        $associative_array = debug_backtrace();
        $callfrompath = $associative_array[0]["file"];
        $callfromline = $associative_array[0]["line"];
        echo "メッセージの送信失敗。\nFile/Line: `" . $callfrompath . " : " . $callfromline . "`\n:WordCount `" . mb_strlen($message) . "`\nResult:```" . $contents . "```";
        if (strpos($callfrompath, "Command") !== false) {
            DiscordSend($channel, "メッセージの送信失敗。\nFile/Line: `" . $callfrompath . " : " . $callfromline . "`\n:WordCount `" . mb_strlen($message) . "`\nResult:```" . $contents . "```");
        }
        return 0;
    }
}
function formatBytes($size, $precision = 2)
{
    $base = log($size, 1024);
    $suffixes = array('', 'K', 'M', 'G', 'T');

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

ini_set("memory_limit", -1);
if (!isset($argv[1]) || !isset($argv[2])) {
    echo "StationID(ex. TBS): ";
    $sid = trim(fgets(STDIN));
    echo "From(ex. 2020/01/01 00:00:00): ";
    $from = trim(fgets(STDIN));
} else {
    $sid = $argv[1];
    $from = $argv[2];
}

function record($sid, $from)
{
    $DISCORD_CHANNEL_ID = $_ENV["DISCORD_CHANNEL_ID"];
    $PROXY_URL = $_ENV["PROXY_URL"];
    $PROXT_AUTH = $_ENV["PROXT_AUTH"];

    if (is_numeric($from)) {
        $DATE = substr($from, 0, 8);
    } else {
        $from = strtotime($from);
        $BEFORE_DATE = date("Ymd", $from - 86400);
        $DATE = date("Ymd", $from);
    }

    $title = null;
    $ft = null;
    $to = null;


    $xml = file_get_contents("http://radiko.jp/v3/program/date/$BEFORE_DATE/JP13.xml");
    $obj = simplexml_load_string($xml);
    $json = json_encode($obj);
    $json = json_decode($json, true);
    foreach ($json["stations"]["station"] as $one) {
        if ($one["@attributes"]["id"] != $sid) {
            continue;
        }
        foreach ($one["progs"]["prog"] as $prog) {
            $_ft = $prog["@attributes"]["ft"];
            $_to = $prog["@attributes"]["to"];
            $_title = $prog["title"];

            if ($_ft == date("YmdHis", $from)) {
                $ft = $_ft;
                $to = $_to;
                $title = $_title;
                break 2;
            }
        }
    }

    if ($ft == null) {
        $xml = file_get_contents("http://radiko.jp/v3/program/date/$DATE/JP13.xml");
        $obj = simplexml_load_string($xml);
        $json = json_encode($obj);
        $json = json_decode($json, true);

        foreach ($json["stations"]["station"] as $one) {
            if ($one["@attributes"]["id"] != $sid) {
                continue;
            }
            foreach ($one["progs"]["prog"] as $prog) {
                $_ft = $prog["@attributes"]["ft"];
                $_to = $prog["@attributes"]["to"];
                $_title = $prog["title"];

                if ($_ft == date("YmdHis", $from)) {
                    $ft = $_ft;
                    $to = $_to;
                    $title = $_title;
                    break 2;
                }
            }
            foreach ($one["progs"]["prog"] as $prog) {
                $_ft = $prog["@attributes"]["ft"];
                $_to = $prog["@attributes"]["to"];
                $_title = $prog["title"];

                echo "[Selector] Title: $_title\n";
                echo "[Selector] FT: $_ft\n";
                echo "[Selector] TO: $_to\n";
                echo "[Selector] yes(y) or no: ";

                $stdin = trim(fgets(STDIN));
                if ($stdin == "y" || $stdin == "yes") {
                    $ft = $_ft;
                    $to = $_to;
                    $title = $_title;
                    break 2;
                }
            }
        }
    }
    if ($ft == null || $to == null || $title == null) {
        echo "No program found.\n";
        return;
    }

    $unixtime = strtotime(substr($ft, 0, 4) . "/" . substr($ft, 4, 2) . "/" . substr($ft, 6, 2) . " " . substr($ft, 8, 2) . ":" . substr($ft, 10, 2) . ":" . substr($ft, 12, 2));

    if ($unixtime >= time()) {
        echo "It has not been broadcast yet.";
        exit;
    }

    echo "Title: $title\n";
    echo "FT: $ft\n";
    echo "TO: $to\n";

    $OUTPUT_DIR = __DIR__ . "/temp/" . $title . "/";
    if (!file_exists($OUTPUT_DIR)) {
        mkdir($OUTPUT_DIR, 0777, true);
    }
    $LAST_OUTPUT_DIR = "/data/" . $title . "/";
    if (!file_exists($LAST_OUTPUT_DIR)) {
        mkdir($LAST_OUTPUT_DIR, 0777, true);
    }
    $FILE = date("Ymd_D", $unixtime) . "_$title.ts";
    $OUTPUT_PATH = $OUTPUT_DIR . $FILE;

    $downloaded = file_exists("/data/downloaded.json") ? json_decode(file_get_contents("/data/downloaded.json"), true) : [];
    if (in_array(date("Ymd_D", $unixtime) . "_$title", $downloaded)) {
        echo "Downloaded. skip\n";
        exit;
    }

    $FILE = date("Ymd_D", $unixtime) . "_$title.mp3";
    $LAST_OUTPUT_PATH = $LAST_OUTPUT_DIR . $FILE;

    $BASE_PLAYLIST_URL = "https://radiko.jp/v2/api/ts/playlist.m3u8";

    $stream_xml = file_get_contents("https://radiko.jp/v3/station/stream/pc_html5/" . $sid . ".xml");
    $stream_json = json_decode(json_encode(simplexml_load_string($stream_xml)), true);
    $streams = $stream_json["url"];

    foreach ($streams as $stream_url) {
        if ($stream_url["@attributes"]["areafree"] != 0) {
            continue;
        }
        if ($stream_url["@attributes"]["timefree"] != 1) {
            continue;
        }
        $BASE_PLAYLIST_URL = $stream_url["playlist_create_url"];
        echo "Selected stream: $BASE_PLAYLIST_URL\n";
        break;
    }


    $PLAYLIST_URL = $BASE_PLAYLIST_URL . "?station_id=#{sid}&l=15&lsid=#{lsid}&ft=#{ft}&to=#{to}&type=b";

    $PLAYLIST_URL = "https://radiko.jp/v2/api/ts/playlist.m3u8?station_id=#{sid}&l=15&lsid=#{lsid}&start_at=#{start_at}&end_at=#{end_at}&ft=#{ft}&to=#{to}&type=b";
    $PLAYLIST_URL = "https://radiko.jp/v2/api/ts/playlist.m3u8?station_id=#{sid}&l=15&lsid=#{lsid}&ft=#{ft}&to=#{to}&type=b";

    $headers = [
        "Content-Type: application/x-www-form-urlencoded",
        "Referer: https://radiko.jp/",
        "Pragma: no-cache",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36",
        "X-Radiko-Device: pc",
        "X-Radiko-App-Version: 0.0.1",
        "X-Radiko-User: dummy_user",
        "X-Radiko-App: pc_html5",
        "DNT: 1",
        "X-Radiko-AreaId: JP13",
        "Proxy-Authorization: Basic " . base64_encode($PROXT_AUTH),
        "Proxy-Connection: close"
    ];
    $context = [
        "http" => [
            "method" => "GET",
            "header" => implode("\r\n", $headers),
            "ignore_errors" => true,
            "proxy" => "tcp://" . $PROXY_URL,
            "request_fulluri" => true,
        ]
    ];


    // get Auth

    $auth_token = null;
    $keyOffset = null;
    $keyLength = null;

    $result = file_get_contents("https://radiko.jp/v2/api/auth1", false, stream_context_create($context));
    print_r($http_response_header);
    foreach ($http_response_header as $i => $header) {
        if ($i == 0) {
            continue;
        }
        list($key, $value) = explode(": ", $header);
        if ($key == "X-Radiko-AuthToken") {
            $auth_token = $value;
        } elseif ($key == "X-Radiko-Authtoken") {
            $auth_token = $value;
        } elseif ($key == "X-RADIKO-AUTHTOKEN") {
            $auth_token = $value;
        } elseif ($key == "X-Radiko-KeyOffset") {
            $keyOffset = $value;
        } elseif ($key == "X-Radiko-KeyLength") {
            $keyLength = $value;
        }
    }

    echo $http_response_header[0] . "\n";
    echo $result . "\n";

    if ($auth_token == null) {
        echo "auth_token == null\n";
        exit;
    }
    echo "auth_token = $auth_token\n";
    echo "keyOffset = $keyOffset\n";
    echo "keyLength = $keyLength\n";

    $authkey = "bcd151073c03b352e1ef2fd66c32209da9ca0afa";
    $authkey = substr($authkey, $keyOffset, $keyLength);
    $authkey = base64_encode($authkey);
    echo "authkey = $authkey\n";

    $headers[] = "X-Radiko-Authtoken: $auth_token";
    $headers[] = "X-Radiko-Partialkey: $authkey";

    $context["http"]["header"] = implode("\r\n", $headers);

    $result = file_get_contents("https://radiko.jp/v2/api/auth2", false, stream_context_create($context));
    echo $http_response_header[0] . "\n";
    echo $result . "\n";

    $lsid = "11111111111111111111111111111111111111";

    $PLAYLIST_URL = str_replace("#{sid}", $sid, $PLAYLIST_URL);
    $PLAYLIST_URL = str_replace("#{ft}", $ft, $PLAYLIST_URL);
    $PLAYLIST_URL = str_replace("#{to}", $to, $PLAYLIST_URL);
    $PLAYLIST_URL = str_replace("#{lsid}", $lsid, $PLAYLIST_URL);
    $PLAYLIST_URL = str_replace("#{start_at}", $ft, $PLAYLIST_URL);
    $PLAYLIST_URL = str_replace("#{end_at}", $to, $PLAYLIST_URL);

    $cmd = "export http_proxy=http://" . $PROXT_AUTH . "@" . $PROXY_URL . " && ffmpeg -y -headers \"X-Radiko-Authtoken: $auth_token\" -i \"$PLAYLIST_URL\" -acodec copy \"$OUTPUT_PATH\"";
    echo "cmd = $cmd\n";
    system($cmd, $ret);
    if ($ret != 0) {
        echo "ffmpeg error\n";
        DiscordSend($DISCORD_CHANNEL_ID, ":x:録音に失敗しました(ffmpeg error): `$title` (`$ft` - `$to`)");
        exit;
    }

    $PATH_META = $OUTPUT_DIR . date("Ymd_D", $unixtime) . "_$title.meta.txt";
    $date = date("Y/m/d", $unixtime);
    $metadata = <<<METADATA
    ;FFMETADATA1
    title=$title {$date}
    artist=$title
    METADATA;
    file_put_contents($PATH_META, $metadata);

    $cmd = "ffmpeg -y -i \"$OUTPUT_PATH\" -i \"$PATH_META\" -map_metadata 1 \"$LAST_OUTPUT_PATH\"";
    echo "cmd = $cmd\n";
    system($cmd);

    chmod($LAST_OUTPUT_PATH, 0777);

    if (file_exists($LAST_OUTPUT_PATH)) {
        chmod($LAST_OUTPUT_PATH, 0755);
        echo "Download completed!\n";
        $downloaded = json_decode(file_get_contents("/data/downloaded.json"), true);
        $downloaded[] = date("Ymd_D", $unixtime) . "_$title";
        file_put_contents("/data/downloaded.json", json_encode($downloaded));

        DiscordSend($DISCORD_CHANNEL_ID, ":white_check_mark:録音が完了しました: `$title` (`$ft` - `$to`) - TS:`" . formatBytes(filesize($OUTPUT_PATH)). "` / MP3:`" . formatBytes(filesize($LAST_OUTPUT_PATH)). "`");
        unlink($OUTPUT_PATH);
    } else {
        echo "Download failed!";
        DiscordSend($DISCORD_CHANNEL_ID, ":x:録音に失敗しました(output file not found): `$title` (`$ft` - `$to`)");
    }
}

// Main
$schedules = json_decode(file_get_contents("/data/schedules.json"), true);
foreach ($schedules as $schedule) {
    $title = $schedule["title"];
    $SID = $schedule["SID"];
    $dayOfWeeks = $schedule["dayOfWeek"];
    $time = $schedule["time"];

    echo "Title: $title\n";
    echo "SID: $SID\n";
    echo "DayOfWeeks: $dayOfWeeks\n";
    echo "Time: $time\n";

    $dayOfWeeks = explode(",", $dayOfWeeks);
    foreach ($dayOfWeeks as $dayOfWeek) {
        echo "DayOfWeek: $dayOfWeek\n";
        $unixtime = strtotime("next $dayOfWeek $time", strtotime("-7 days"));

        record($SID, date("Y/m/d H:i:s", $unixtime));
    }
}
