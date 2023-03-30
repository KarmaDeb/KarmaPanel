<?php

namespace KarmaDev\Panel;

use GifFrameExtractor\GifFrameExtractor;
use GifCreator\GifCreator;
use PHPImageWorkshop\ImageWorkshop;

use KarmaDev\Panel\SQL\ClientData;
use KarmaDev\Panel\SQL\Notifications as Notification;

use KarmaDev\Panel\Configuration;

use PDO;
use PDOException;
use DateTime;
use DateTimeZone;

class Utilities {

    public static function default_photo() {
        return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4CAIAAAC2BqGFAAAACXBIWXMAAA7EAAAOxAGVKw4bAAACyElEQVR4nO3bS27DMAwEUKn2AbzPbZIbBL6MjxzAn3VW6oKo0aZJWifUkJLnbYuK6ogV/JFDICIiIiIiIiIiIiIiIiIiItokWk/gX1JKj34UYxl/gutZPsn3N+eJf1hP4I6maVJKm1IOIcivNE2TaVZvctcFW/O9y2F3O+rocRxVUg4hpJTGcVQZSouXldeK+Iaf1nYxj0wpCydZ208ia8rCQ9bGezRmJ/WwXxsvNaCdhXlTW5aHpSxsszbbOtq2BVe0vZcxW2RwOwvDpnZ0w1I3mxU2aWdh1dTsaBAGDWLwf2S4bwiT3YMdDcKgQRg0CIMGYdAgDBqEQYMwaBA+6wBhR4MwaBA++AdhR4Ps6J0hvuJ3fAuOqm5YO+zpXIfxHj1NUzVVnrM/lLaTs3f2Mwg8TYpU/floL9fRMUbdnXSaJj8pBz8dveI3LCAxxnfuLNq2dZhycNjR39X0naHrya0q+HKWiIiIiH64XC7pp+v1aj2pDZxehL58I+72strRLfgwDGu3vjzIOsIwDIpze5+L9d/D82jjjn6zf/9f4nA4ZK3yJx6gQZXGl+RXWdnN82yecgghpTTPM7gobm09RHwD2dqgSg5TFrCsEWXcpiwwWefdo7uuc55yCCGl1HVd7ioZgz6dTsuy5Btf0bIsx+PRehavSqU5n8/50si1PSX3O8Zd+fbrLOMWmrLIlLX+oEWnLHJkrTxiBSkL9awdPY+um+a6VdPOQrep1caqLGWhmDW3DhCdFauynYVWU7OjQRSWq+J2FipNzY4G4R79B609mpd3zyhe3vGG5SGnNyyimqy9P+uIMQJeC2XVdV0BT+9WhbZ2YQ/+RXFZZ30dnv1NexFxA04c8ABNRQdoVq7iBh915GlSVFF8SWES977OR98AJO7h6wr7p3fxS9/3isP2fb+OrDjsy1xM4pFNze4kUCIiIiLahU+VUr5mnVc6sAAAAABJRU5ErkJggg==";
    }

    public static function parseFullyJson(string $jsonData) {
        $json = json_decode($jsonData, true);
        if ($json != null && gettype($json) == 'array') {
            foreach ($json as $key => $value) {
                if (gettype($value) == 'string') {
                    $json[$key] = self::parseFullyJson(str_replace("\\\"", '"', $value));
                }
            }

            return $json;
        } else {
            if (strlen($jsonData) > 512) {
                $jsonData = trim($jsonData, 512)[0] . '...';
            }

            return $jsonData;
        }
    }

    public static function hexToRGB($htmlCode) {
        if($htmlCode[0] == '#')
            $htmlCode = substr($htmlCode, 1);

        if (strlen($htmlCode) == 3) {
            $htmlCode = $htmlCode[0] . $htmlCode[0] . $htmlCode[1] . $htmlCode[1] . $htmlCode[2] . $htmlCode[2];
        }

        $r = hexdec($htmlCode[0] . $htmlCode[1]);
        $g = hexdec($htmlCode[2] . $htmlCode[3]);
        $b = hexdec($htmlCode[4] . $htmlCode[5]);

        return $b + ($g << 0x8) + ($r << 0x10);
    }

    public static function rgbToHSL($RGB) {
        $r = 0xFF & ($RGB >> 0x10);
        $g = 0xFF & ($RGB >> 0x8);
        $b = 0xFF & $RGB;

        $r = ((float)$r) / 255.0;
        $g = ((float)$g) / 255.0;
        $b = ((float)$b) / 255.0;

        $maxC = max($r, $g, $b);
        $minC = min($r, $g, $b);

        $l = ($maxC + $minC) / 2.0;

        if($maxC == $minC) {
            $s = 0;
            $h = 0;
        } else {
            if($l < .5) {
                $s = ($maxC - $minC) / ($maxC + $minC);
            } else {
                $s = ($maxC - $minC) / (2.0 - $maxC - $minC);
            }
            if($r == $maxC)
                $h = ($g - $b) / ($maxC - $minC);
            if($g == $maxC)
                $h = 2.0 + ($b - $r) / ($maxC - $minC);
            if($b == $maxC)
                $h = 4.0 + ($r - $g) / ($maxC - $minC);

            $h = $h / 6.0; 
        }

        $h = (int)round(255.0 * $h);
        $s = (int)round(255.0 * $s);
        $l = (int)round(255.0 * $l);

        return (object) Array('hue' => $h, 'saturation' => $s, 'lightness' => $l);
    }

    public static function resizeImage(string $target_file, int $w, int $h, $isString = false) {
        if ($isString) {
            $data = explode(",", $target_file);
            $target_file = base64_decode(str_replace($data[0] . ',', "", $target_file));

            list($width, $height, $type) = getimagesizefromstring($target_file);
        } else {
            list($width, $height, $type) = getimagesize($target_file);
        }

        $thumb = imagecreatetruecolor($w, $h);

        switch ($type) {
            case IMAGETYPE_JPEG:
                ob_start();
                if ($isString) {
                    $image = imagecreatefromstring($target_file);
                } else {
                    $image = imagecreatefromjpeg($target_file);
                }
                $background = imagecolorallocate($thumb, 0, 0, 0);
                imagecolortransparent($thumb, $background);
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                
                imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $width, $height);
                imagejpeg($thumb);

                $image = 'data:image/jpeg;base64,' . base64_encode(ob_get_contents());
                ob_end_clean();
                break;
            case IMAGETYPE_PNG:
                ob_start();
                if ($isString) {
                    $image = imagecreatefromstring($target_file);
                } else {
                    $image = imagecreatefrompng($target_file);
                }
                $background = imagecolorallocate($thumb, 0, 0, 0);
                imagecolortransparent($thumb, $background);
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);

                imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $width, $height);
                imagepng($thumb);

                $image = 'data:image/png;base64,' . base64_encode(ob_get_contents());
                ob_end_clean();
                break;
            case IMAGETYPE_GIF:
                ob_start();
                if (GifFrameExtractor::isAnimatedGif($target_file)) {
                    $gfe = new GifFrameExtractor();
                    $frames = $gfe->extract($target_file);

                    $retouchedFrames = array();
                    foreach ($frames as $frame) {
                        $frameLayer = ImageWorkshop::initFromResourceVar($frame['image']);

                        $frameLayer->resizeInPixel(120, 120, false);
                        array_push($retouchedFrames, $frameLayer->getResult());
                    }

                    $gc = new GifCreator();
                    $gc->create($retouchedFrames, $gfe->getFrameDurations(), 0);

                    $config = new Configuration();

                    $fl = $config->getWorkingDirectory() . 'vendor/gifs/' . self::generate() . '.gif';
                    file_put_contents($fl, $gc->getGif());
                    $image = 'data:image/gif;base64,' . base64_encode(file_get_contents($fl));

                    unlink($fl);
                } else {
                    $image = imagecreatefromgif($target_file);
                    $background = imagecolorallocate($thumb, 0, 0, 0);
                    imagecolortransparent($thumb, $background);
                    imagealphablending($thumb, false);
                    imagesavealpha($thumb, true);
    
                    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $width, $height);
                    imagegif($thumb);
    
                    $image = 'data:image/gif;base64,' . base64_encode(ob_get_contents());
                }

                ob_end_clean();
                break;
        }

        if (strpos($target_file, 'http://') != 0 && strpos($target_file, 'https://') != 0) {
            unlink($target_file);
        }
        return $image;
    }

    public static function getUserIpAddr() {
        $ipaddress = '';
        $svAdress = '129.152.17.67';

        foreach ($_SERVER as $key => $value) {
            if ($value != $svAdress) {
                switch ($key) {
                    case 'HTTP_X_FORWARDED_FOR':
                    case 'HTTP_X_FORWARDED':
                    case 'HTTP_FORWARDED_FOR':
                    case 'HTTP_FORWARDED':
                    case 'HTTP_CF_CONNECTING_IP':
                    case 'HTTP_CLIENT_IP':
                        $ipaddress = $value;
                        break;
                }
            }
        }

        return $ipaddress;
    }

    public static function encode(string $param) {
        $virtual = self::getGlobal('v_id');
        if ($virtual == null) {
            $virtual = rand();
            self::setGlobal('v_id', $virtual);
        }

        return base64_encode(password_hash($param . $virtual, PASSWORD_ARGON2ID));
    }

    public static function auth(string $pass, string $token) {
        $virtual = self::getGlobal('v_id');
        if ($virtual != null) {
            return password_verify($pass . $virtual, $token);
        }

        return false;
    }

    public static function generate(int $length = 32) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle($characters), 0, $length);
    }

    public static function generateAPIKey() {
        $password = self::generate(12);
        $hash = password_hash($password, PASSWORD_BCRYPT, array(
            'cost' => 9
        ));

        return array(
            'private' => $password,
            'public' => $hash 
        );
    }

    public static function generateTmpAPIKey() {
        $password = self::generate(12);
        $hash = password_hash($password, PASSWORD_BCRYPT, array(
            'cost' => 9
        ));

        self::set('tmp_private_key', $password);
        self::set('tmp_private_expire', time() + 5);

        return $hash;
    }

    public static function consumeTempApiKey($key) {
        $stored_key = self::get('tmp_private_key');

        if (password_verify($stored_key, $key)) {
            $expire_time = self::get('tmp_private_expire');

            if (time() <= $expire_time) {
                self::set('tmp_private_key', null);
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public static function build($default, ... $postRequired) {
        $result = [];

        foreach ($postRequired as $key) {
            if (isset($_GET[$key])) {
                $result[$key] = $_GET[$key];
            } else {
                if (isset($_POST[$key])) {
                    if (empty($_POST[$key])) {
                        $result[$key] = $default;
                    } else {
                        $result[$key] = $_POST[$key];
                    }
                } else {
                    $result[$key] = $default;
                }
            }
        }

        return $result;
    }

    public static function buildPost($default, ... $postRequired) {
        $result = [];

        foreach ($postRequired as $key) {
            if (isset($_POST[$key])) {
                if (empty($_POST[$key])) {
                    $result[$key] = $default;
                } else {
                    $result[$key] = $_POST[$key];
                }
            } else {
                $result[$key] = $default;
            }
        }

        return $result;
    }

    public static function findValid(... $params) {
        foreach ($params as $key) {
            if ($key != null) {
                return $key;
            }
        }

        return "";
    }

    public static function notifyMultiple(array|object $users = null, string $title, string $content) {
        $notifications = new Notification();

        if ($users != null) {
            foreach ($users as $id) {
                $notifications->writeNotification($id, $title, $content);
            }
        } else {
            $connection = new ClientData();
            $allUsers = $connection->getRegisteredUsers();

            foreach ($allUsers as $id) {
                $notifications->writeNotification($id, $title, $content);
            }
        }
    }

    public static function getTimeAgo(int $time) {
        $time_difference = time() - $time;

        if ($time_difference < 1) { return 'less than 1 second ago'; }
        $condition = array(12 * 30 * 24 * 60 * 60 =>  'year',
                    30 * 24 * 60 * 60       =>  'month',
                    24 * 60 * 60            =>  'day',
                    60 * 60                 =>  'hour',
                    60                      =>  'minute',
                    1                       =>  'second'
        );

        foreach($condition as $secs => $str) {
            $d = $time_difference / $secs;

            if ($d >= 1) {
                $t = round($d);
                return 'about ' . $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
            }
        }
    }

    public static function checkVersions(string $current, string $check) {
        $currentParts = explode('.', $current);
        $checkParts = explode('.', $check);

        $length = max(count($currentParts), count($checkParts));

        for ($i = 0; $i < $length; $i++) {
            $currentPart = ($i < count($currentParts) ? intval($currentParts[$i]) : 0);
            $checkPart = ($i < count($checkParts) ? intval($checkParts[$i]) : 0);

            if ($currentPart < $checkPart) {
                return -1;
            } else {
                if ($currentPart > $checkPart) {
                    return 1;
                }
            }
        }

        return 0;
    }

    public static function addOnlineUser(int $id, string $name, string $doing) {
        $config = new Configuration();
        try {
            $host = $config->getLocalSQLHost();
            $port = $config->getLocalSQLPort();

            $connection = new PDO('mysql:host='. $host .';port='. $port .';dbname=k3rm@p4n3l', $config->getSQLUser(), $config->getSQLPassword());

            $query = $connection->prepare("SELECT * FROM `online_users` WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $id);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) >= 1) {
                        $query = $connection->prepare("DELETE FROM `online_users` WHERE `id` = ? AND `name` = ?");
                        if ($query) {
                            $query->bindParam(1, $id);
                            $query->bindParam(2, $name);
    
                            $query->execute();
                        }
                    }
    
                    $query = $connection->prepare("INSERT INTO `online_users` (`id`,`name`,`doing`) VALUES (?,?,?)");
                    if ($query) {
                        $query->bindParam(1, $id);
                        $query->bindParam(2, $name);
                        $query->bindParam(3, $doing);
    
                        $query->execute();
                    }
                }
            }
        } catch (PDOException $error) {}
    }

    public static function getOnlineUsers() {
        $online = [];
        $config = new Configuration();
        try {
            $host = $config->getLocalSQLHost();
            $port = $config->getLocalSQLPort();

            $connection = new PDO('mysql:host='. $host .';port='. $port .';dbname=k3rm@p4n3l', $config->getSQLUser(), $config->getSQLPassword());

            $query = $connection->prepare("SELECT * FROM `online_users`");
            if ($query && $query->execute()) {
                $allOnline = $query->fetchAll(PDO::FETCH_ASSOC);
    
                foreach ($allOnline as $kId => $data) {
                    if (isset($data['id']) && isset($data['last_iteraction']) && isset($data['name'])) {
                        $id = $data['id'];
                        $name = $data['name'];

                        $last_action = $data['last_iteraction'];

                        $newTimezone = new DateTime($last_action, new DateTimeZone('UTC'));
                        $newTimezone->setTimezone(new DateTimeZone(self::get('timezone')));

                        $last_action = $newTimezone->format('U');
        
                        $now = time();
                        $expire = $last_action + 120;
                        if ($expire >= $now) {
                            $connection = new ClientData();
                            $profile = $connection->loadProfileData($id);
        
                            if (isset($profile['setting'])) {
                                $st = $profile['setting'];
        
                                $settings = json_decode($st, true);
                                if ($settings['broadcast_status']) {
                                    array_push($online, array(
                                        'name' => $name,
                                        'id' => $id
                                    ));
                                }
                            }
                        }
                    }
                }
            }
        } catch (PDOException $error) {}
        
        return $online;
    }

    public static function getLastOnline(int $user) {
        $config = new Configuration();
        try {
            $host = $config->getLocalSQLHost();
            $port = $config->getLocalSQLPort();

            $connection = new PDO('mysql:host='. $host .';port='. $port .';dbname=k3rm@p4n3l', $config->getSQLUser(), $config->getSQLPassword());

            $query = $connection->prepare("SELECT * FROM `online_users` WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $user);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) >= 1) {
                        $data = $data[0];
    
                        if (isset($data['last_iteraction'])) {
                            return $data['last_iteraction'];
                        }
                    }
                }
            }
        } catch (PDOException $error) {}

        $cData = new ClientData();
        $profileData = $cData->loadProfileData($user);
        if ($profileData != null) {
            return $profileData['registered'];
        } else {
            return "1999/01/01 00:00:00";
        }
    }

    public static function getLastDoing(int $user) {
        $config = new Configuration();
        try {
            $host = $config->getLocalSQLHost();
            $port = $config->getLocalSQLPort();

            $connection = new PDO('mysql:host='. $host .';port='. $port .';dbname=k3rm@p4n3l', $config->getSQLUser(), $config->getSQLPassword());

            $query = $connection->prepare("SELECT * FROM `online_users` WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $user);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) >= 1) {
                        $data = $data[0];
    
                        if (isset($data['doing'])) {
                            return $data['doing'];
                        } else {
                            return 'No data';
                        }
                    } else {
                        return 'No exist';
                    }
                } else {
                    return 'No execute';
                }
            } else {
                return 'SQL fail';
            }
        } catch (PDOException $error) {
            return $error->getMessage();
        }

        return 'navigating through the panel';
    }

    public static function get(string $key, $default = null) {
        $config = new Configuration();
        try {
            $host = $config->getLocalSQLHost();
            $port = $config->getLocalSQLPort();

            $connection = new PDO('mysql:host='. $host .';port='. $port .';dbname=k3rm@p4n3l', $config->getSQLUser(), $config->getSQLPassword());

            $query = $connection->prepare("SELECT `value` FROM `panel_data` WHERE `path` = ?");
            if ($query) {
                $address = self::getUserIpAddr();
    
                $query->bindParam(1, $address);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) >= 1) {
                        $data = $data[0];
    
                        $value = $data['value'];
                        $json = json_decode($value, true);
                        if (isset($json[$key])) {
                            return $json[$key];
                        } else {
                            return $default;
                        }
                    }
                }
            }
        } catch (PDOException $error) {}

        return $default;
    }

    public static function getGlobal(string $key, $default = null) {
        $config = new Configuration();
        try {
            $host = $config->getLocalSQLHost();
            $port = $config->getLocalSQLPort();

            $connection = new PDO('mysql:host='. $host .';port='. $port .';dbname=k3rm@p4n3l', $config->getSQLUser(), $config->getSQLPassword());

            $query = $connection->prepare("SELECT `value` FROM `global_panel_data` WHERE `path` = ?");
            if ($query) {
                $address = self::getUserIpAddr();
    
                $query->bindParam(1, $key);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) >= 1) {
                        $data = $data[0];
    
                        $value = $data['value'];
    
                        if (is_numeric($value)) {
                            return intval($value);
                        } else {
                            if ($value == 'true') {
                                return true;
                            } else {
                                if ($value == 'false') {
                                    return false;
                                } else {
                                    json_decode($value);
                                    if (json_last_error() == JSON_ERROR_NONE) {
                                        $json = json_encode($value, true);
                                        return $json;
                                    } else {
                                        return $value;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (PDOException $error) {}

        return $default;
    }

    public static function set(string $key, $value, string $force_address = null) {
        $config = new Configuration();
        try {
            $host = $config->getLocalSQLHost();
            $port = $config->getLocalSQLPort();

            $connection = new PDO('mysql:host='. $host .';port='. $port .';dbname=k3rm@p4n3l', $config->getSQLUser(), $config->getSQLPassword());

            $query = $connection->prepare("SELECT * FROM `panel_data` WHERE `path` = ?");
            if ($query) {
                if ($force_address == null) {
                    $address = self::getUserIpAddr();
                } else {
                    $address = $force_address;
                }
                
    
                $query->bindParam(1, $address);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if ($value != null) {
                        if (count($data) >= 1) {
                            $data = $data[0];
                            $current = $data['value'];
                            $json = json_decode($current, true);
                            $json[$key] = $value;
                            $json = json_encode($json, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_LINE_TERMINATORS);
    
                            $query = $connection->prepare("UPDATE `panel_data` SET `value` = ? WHERE `path` = ?");
                            if ($query) {
                                $query->bindParam(1, $json);
                                $query->bindParam(2, $address);
    
                                $query->execute();
                            }
                        } else {
                            $newData = [];
                            $newData[$key] = $value;
                            $json = json_encode($newData);
    
                            $query = $connection->prepare("INSERT INTO `panel_data` (`path`,`value`) VALUES (?,?)");
                            if ($query) {
                                $query->bindParam(1, $address);
                                $query->bindParam(2, $json);
    
                                $query->execute();
                            }
                        }
                    } else {
                        if (count($data) >= 1) {
                            $data = $data[0];
                            $current = $data['value'];
                            $json = json_decode($current, true);
                            unset($json[$key]);
                            $json = json_encode($json, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_LINE_TERMINATORS);
    
                            $query = $connection->prepare("UPDATE `panel_data` SET `value` = ? WHERE `path` = ?");
                            if ($query) {
                                $query->bindParam(1, $json);
                                $query->bindParam(2, $address);
    
                                $query->execute();
                            }
                        }
                    }
                }
            }
        } catch (PDOException $error) {}
    }

    public static function setGlobal(string $key, $value) {
        $config = new Configuration();
        try {
            $host = $config->getLocalSQLHost();
            $port = $config->getLocalSQLPort();

            $connection = new PDO('mysql:host='. $host .';port='. $port .';dbname=k3rm@p4n3l', $config->getSQLUser(), $config->getSQLPassword());

            $query = $connection->prepare("SELECT `value` FROM `global_panel_data` WHERE `path` = ?");
            if ($query) {
                $address = self::getUserIpAddr();
    
                $query->bindParam(1, $key);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if ($value != null) {
                        if (count($data) >= 1) {
                            $query = $connection->prepare("UPDATE `global_panel_data` SET `value` = ? WHERE `path` = ?");
                            if ($query) {
                                $query->bindParam(1, $value);
                                $query->bindParam(2, $key);
    
                                $query->execute();
                            }
                        } else {
                            $query = $connection->prepare("INSERT INTO `global_panel_data` (`path`,`value`) VALUES (?,?)");
                            if ($query) {
                                $query->bindParam(1, $key);
                                $query->bindParam(2, $value);
    
                                $query->execute();
                            }
                        }
                    } else {
                        $query = $connection->prepare("DELETE FROM `global_panel_data` WHERE `path` = ?");
                        if ($query) {
                            $query->bindParam(1, $address);
                            $query->bindParam(2, $key);
    
                            $query->execute();
                        }
                    }
                }
            }
        } catch (PDOException $error) {}
    }
}