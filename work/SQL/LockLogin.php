<?php

namespace KarmaDev\Panel\SQL;

use KarmaDev\Panel\Configuration;
use KarmaDev\Panel\Utilities as Utils;

use PDO;
use PDOException;

class LockLogin {

    private static $connection;

    public function __construct() {
        if (self::$connection == null) {
            try {
                $config = new Configuration();

                $host = $config->getLocalSQLHost();
                $port = $config->getLocalSQLPort();

                $user = $config->getSQLUser();
                $pass = $config->getSQLPassword();

                self::$connection = new PDO("mysql:host={$host};port={$port};dbname=locklogin", $user, $pass);
            } catch (PDOException $error) {
                echo 'Failed to initialize database connection: ' . $error->getMessage();
            }
        }
    }

    public function registerServer(string $secret, string $display) {
        if (ClientData::isAuthenticated()) {
            try {
                $client = unserialize(Utils::get('client'));
    
                $query = self::$connection->prepare("SELECT `id`,`server`,`hooked`,`owner`,`display`,`since` FROM `servers` WHERE `owner` = ?");
                if ($query) {
                    $userId = $client->getIdentifier();
    
                    $query->bindParam(1, $userId);
                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                        foreach ($data as $fId => $info) {
                            if ($info['server'] == $secret) {
                                return false;
                            } else {
                                if (strtolower($info['display']) == $display) {
                                    return false;
                                }
                            }
                        }
    
                        $query = self::$connection->prepare("INSERT INTO `servers` (`server`,`owner`,`display`) VALUES (?,?,?)");
                        if ($query) {
                            $query->bindParam(1, $secret);
                            $query->bindParam(2, $userId);
                            $query->bindParam(3, $display);
    
                            return $query->execute();
                        }
                    }
                }
            } catch (PDOException $error) {}
        }

        return false;
    }

    public function getServer(string $secret) {
        try {
            $query = self::$connection->prepare("SELECT `display`,`owner`,`since` FROM `servers` WHERE `server` = ?");
            
            if ($query) {
                $query->bindParam(1, $secret);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) >= 1) {
                        $data = $data[0];
    
                        $svData = array(
                            'server' => $data['display'],
                            'owner' => $data['owner'],
                            'date' => $data['since']
                        );
    
                        $query = self::$connection->prepare("SELECT `server`,`user` FROM `server_permissions` WHERE `server` = ?");
                        if ($query) {
                            $query->bindParam(1, $secret);
    
                            if ($query->execute()) {
                                $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                                $allowed_users = array($svData['owner']);
    
                                foreach ($data as $i => $in) {
                                    array_push($allowed_users, $in['user']);
                                }
    
                                $svData['allowed'] = $allowed_users;
                            }
                        }
    
                        return $svData;
                    }
                }
            }
        } catch (PDOException $error) {}

        return null;
    }

//Server hook

    public function isHookOwner(string $secret) {
        try {
            $server = $this->getServer($secret);

            if ($server != null) {
                $query = self::$connection->prepare("SELECT `hooked` FROM `servers` WHERE `server` = ?");

                if ($query) {
                    $query->bindParam(1, $secret);

                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);

                        if (count($data) >= 1) {
                            $data = $data[0];

                            $hookedAddress = $data['hooked'];

                            if ($hookedAddress != null) {
                                $currentAddress = Utils::getUserIpAddr();
                                $hookedAddress = base64_decode($hookedAddress);

                                return Utils::auth($currentAddress, $hookedAddress);
                            }
                        }
                    }
                }
            }
        } catch (PDOException $error) {}

        return false;
    }

    public function canHook(string $secret) {
        try {
            $server = $this->getServer($secret);

            if ($server != null) {
                $query = self::$connection->prepare("SELECT `hooked` FROM `servers` WHERE `server` = ?");

                if ($query) {
                    $query->bindParam(1, $secret);

                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);

                        if (count($data) >= 1) {
                            $data = $data[0];

                            $hookedAddress = $data['hooked'];

                            if ($hookedAddress != null) {
                                $currentAddress = Utils::getUserIpAddr();
                                $hookedAddress = base64_decode($hookedAddress);

                                return Utils::auth($currentAddress, $hookedAddress);
                            } else {
                                return true;
                            }
                        }
                    }
                }
            }
        } catch (PDOException $error) {}

        return false;
    }

    public function enableHook(string $secret) {
        try {
            $server = $this->getServer($secret);

            if ($server != null) {
                $query = self::$connection->prepare("UPDATE `servers` SET `hooked` = ? WHERE `server` = ?");

                if ($query) {
                    $address = Utils::encode(Utils::getUserIpAddr());

                    $query->bindParam(1, $address);
                    $query->bindParam(2, $secret);

                    return $query->execute();
                }
            }
        } catch (PDOException $error) {}

        return false;
    }

    public function disableHook(string $secret) {
        if ($this->canHook($secret)) {
            try {
                $server = $this->getServer($secret);

                if ($server != null) {
                    $query = self::$connection->prepare("UPDATE `servers` SET `hooked` = NULL WHERE `server` = ?");

                    if ($query) {
                        $query->bindParam(1, $secret);

                        return $query->execute();
                    }
                }
            } catch (PDOException $error) {}
        }

        return false;
    }

//Server commands

    public function getCommands(string $secret) {
        $commands = [];

        try {
            $server = $this->getServer($secret);

            if ($server != null) {
                $query = self::$connection->prepare("SELECT `message`,`creation` FROM `server_messages` WHERE `server` = ? ORDER BY `creation` DESC");

                if ($query) {
                    $query->bindParam(1, $secret);

                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($data as $fId => $data) {
                            $commands[$fId] = array(
                                'command' => $data['message'],
                                'creation' => $data['creation']
                            );
                        }
                    }
                }
            }
        } catch (PDOException $error) {}

        return $commands;
    }

    public function addCommand(string $secret, string $message) {
        try {
            $server = $this->getServer($secret);

            if ($server != null) {
                $query = self::$connection->prepare("INSERT INTO `server_messages` (`server`,`message`) VALUES (?,?)");

                if ($query) {
                    $query->bindParam(1, $secret);
                    $query->bindParam(2, $message);

                    return $query->execute();
                }
            }
        } catch (PDOException $error) {}

        return false;
    }

    public function readCommand(string $secret, int $message) {
        try {
            $server = $this->getServer($secret);

            if ($server != null) {
                $query = self::$connection->prepare("SELECT `message`,`creation` FROM `server_messages` WHERE `id` = ?");

                if ($query) {
                    $query->bindParam(1, $message);

                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);

                        if (count($data) >= 1) {
                            $data = $data[0];

                            $command = $data['message'];
                            $creation = $data['creation'];

                            $query = self::$connection->prepare("DELETE FROM `server_messages` WHERE `id` = ?");
                            if ($query) {
                                $query->bindParam(1, $message);
                                $query->execute();
                            }

                            return array(
                                'command' => $command,
                                'creation' => $creation
                            );
                        }
                    }
                }
            }
        } catch (PDOException $error) {}

        return null;
    }

//LockLogin modules

    public function getModules() {
        $modules = [];

        try {
            $query = self::$connection->prepare("SELECT `id`,`name`,`internal`,`version`,`release_date`,`description`,`minimal_version`,`max_version`,`file` FROM `modules` ORDER BY `release_date`");

            if ($query && $query->execute()) {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                foreach ($data as $fId => $info) {
                    if (!isset($modules[$info['name']])) {
                        $modules[$info['name']] = $info['id'];
                    }
                }
            }
        } catch (PDOException $error) {}

        return $modules;
    }

    public function addModule(string $name, string $internal, string $version, string $min_ver, string $max_ver, string $description, string $file) {
        try {
            $query = self::$connection->prepare("INSERT INTO `modules` (`name`,`internal`,`version`,`description`,`minimal_version`,`max_version`,`file`) VALUES (?,?,?,?,?,?,?)");
            
            if ($query) {
                $query->bindParam(1, $name);
                $query->bindParam(2, $internal);
                $query->bindParam(3, $version);
                $query->bindParam(4, $description);
                $query->bindParam(5, $min_ver);
                $query->bindParam(6, $max_ver);
                $query->bindParam(7, $file);
    
                return $query->execute();
            }
        } catch (PDOException $error) {}

        return false;
    }

    public function getModule(int|string $paramIoN, bool $exact = false) {
        try {
            if (is_numeric($paramIoN)) {
                $query = self::$connection->prepare("SELECT `id`,`name`,`version`,`internal`,`minimal_version`,`max_version`,`description`,`file` FROM `modules` WHERE id = ?");
            } else {
                $query = self::$connection->prepare("SELECT `id`,`name`,`version`,`internal`,`minimal_version`,`max_version`,`description`,`file` FROM `modules` WHERE `internal` = ? ORDER BY `release_date` DESC");
            }

            if ($query) {
                $query->bindParam(1, $paramIoN);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) >= 1) {
                        $data = $data[0];
          
                        if ($exact) {
                            $module = array(
                                'version_id' => $data['id'],
                                'name' => $data['name'],
                                'version' => $data['version'],
                                'internal_name' => $data['internal'],
                                'min_version' => $data['minimal_version'],
                                'max_version' => $data['max_version'],
                                'info' => $data['description'],
                                'download' => $data['file']
                            );
                        } else {
                            $name = $data['name'];
                            $module = array(
                                'id' => $data['id'],
                                'name' => $name,
                                'internal_name' => $data['internal'],
                                'description' => $data['description'],
                                'versions' => array()
                            );

                            $query = self::$connection->prepare("SELECT `id`,`name`,`version`,`internal`,`minimal_version`,`max_version`,`description`,`file` FROM `modules` WHERE `name` = '{$name}' ORDER BY `release_date` DESC");

                            if ($query && $query->execute()) {
                                $data = $query->fetchAll(PDO::FETCH_ASSOC);
        
                                foreach ($data as $fId => $info) {
                                    $version = $info['version'];
                                    
                                    $module['versions'][$version] = array(
                                        'version_id' => $info['id'],
                                        'name' => $info['name'],
                                        'internal_name' => $info['internal'],
                                        'min_version' => $info['minimal_version'],
                                        'max_version' => $info['max_version'],
                                        'info' => $info['description'],
                                        'download' => $info['file']
                                    );
                                }
                            }
                        }
    
                        return $module;
                    }
                }
            }
        } catch (PDOException $error) {}

        return null;
    }

//LockLogin updater

    public function getUpdates(string|null $channel = 'release') {
        $updates = [];

        if ($channel = null)
            $channel = 'release';

        try {
            switch (strtolower($channel)) {
                case 'release':
                    $query = self::$connection->prepare("SELECT `id`,`version`,`channel`,`changelog`,`release_date`,`file` FROM `versions` WHERE `channel` = 'release' ORDER BY `release_date` DESC");
                    break;
                case 'candidate':
                    $query = self::$connection->prepare("SELECT `id`,`version`,`channel`,`changelog`,`release_date`,`file` FROM `versions` WHERE `channel` = 'candidate' OR `channel` = 'release' ORDER BY `release_date` DESC");
                    break;
                case 'snapshot':
                default:
                    $query = self::$connection->prepare("SELECT `id`,`version`,`channel`,`changelog`,`release_date`,`file` FROM `versions` ORDER BY `release_date` DESC");
                    break;
            }
            
            if ($query && $query->execute()) {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                if (count($data) >= 1) {
                    foreach ($data as $fId => $vInfo) {
                        $updates[$vInfo['version']] = array(
                            'id' => $vInfo['id'],
                            'channel' => $vInfo['channel'],
                            'changelog' => $vInfo['changelog'],
                            'download' => $vInfo['file'],
                            'release' => $vInfo['release_date']
                        );
                    }
                }
            }
    
        } catch (PDOException $error) {}

        return $updates;
    }

    public function update(string $version, string $updateName, string $channel, string $changelog, string $file) {
        try {
            $query = self::$connection->prepare("INSERT INTO `versions` (`version`,`channel`,`changelog`,`file`) VALUES (?,?,?,?)");
            
            if ($query) {
                $vData = explode('.', $version);
                $build = $vData[0];
                $number = $vData[1];
                $release = $vData[2];
    
                $sum = intval($build) + intval($number) + intval($release);
    
                $build = strval(abs(intval($build) + $sum));
                $number = strval(abs(intval($number) + $sum));
                $release = strval(abs(intval($release) + $sum));
    
                $name = $build . '/' . $number . '/' . $release;
    
                $matches = [];
                $vText = preg_replace('~[^A-Z]~', '', $updateName);
                $versionName = $vText . '-' . $name;
    
                $query->bindParam(1, $versionName);
                $query->bindParam(2, $channel);
                $query->bindParam(3, $changelog);
                $query->bindParam(4, $file);
    
                return $query->execute();
            }
        } catch (PDOException $error) {}

        return false;
    }

    public function getUpdate(bool $latest = true, string|null $paramVoC = "release") {
        $typeChannel = strtolower($paramVoC);

        if ($latest || $typeChannel == 'release' || $typeChannel == 'candidate' || $typeChannel == 'snapshot' || $paramVoC == null) {
            $query = self::$connection->prepare("SELECT `id`,`version`,`channel`,`changelog`,`release_date`,`file` FROM `versions` WHERE `channel` = ? ORDER BY `release_date`");

            switch ($typeChannel) {
                case 'snapshot':
                    $paramVoC = 'snapshot';
                    break;
                case 'candidate':
                    $paramVoC = 'candidate'; //Making sure it's always lowercase
                    break;
                case 'release':
                default:
                    $paramVoC = 'release';
            }

            if ($query) {
                $query->bindParam(1, $paramVoC);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) >= 1) {
                        $data = $data[0];
    
                        return array(
                            'id' => $data['id'],
                            'channel' => $data['channel'],
                            'version' => $data['version'],
                            'changelog' => $data['changelog'],
                            'download' => $data['file'],
                            'release' => $data['release_date']
                        );
                    }
                }
            }
        } else {
            $query = self::$connection->prepare("SELECT * FROM `versions` WHERE `version` = ?");

            if ($query) {
                $query->bindParam(1, $paramVoC);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) >= 1) {
                        $data = $data[0];
    
                        return array(
                            'id' => $data['id'],
                            'channel' => $data['channel'],
                            'version' => $data['version'],
                            'changelog' => $data['changelog'],
                            'download' => $data['file'],
                            'release' => $data['release_date']
                        );
                    }
                }
            }
        }

        return null;
    }

    public function getOldness(string|int $current_version, string|null|int $max_version = null) {
        if (!is_numeric($current_version)) {
            $v = $this->getUpdate(false, $current_version);

            $current_version = $v['id'];
        }
        if (!is_numeric($max_version) || $max_version == null) {
            if ($max_version == null) {
                $v = $this->getUpdate(true);
            } else {
                $v = $this->getUpdate(false, $max_version);
            }

            $max_version = $v['id'];
        }

        try {
            $query = self::$connection->prepare("SELECT COUNT(*) FROM `versions` WHERE `id` BETWEEN ? AND ?");

            if ($query) {
                $query->bindParam(1, $current_version);
                $query->bindParam(2, $max_version);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    return $data[0]['COUNT(*)'] - 1;
                }
            }
        } catch (PDOException $error) {}

        return 0;
    }

    //LockLogin accounts

    public function createProfile(int $userId) {
        try {
            $query = self::$connection->prepare("SELECT `owner`,`pin`,`2fa`,`token`,`panic` FROM `user_account_data` WHERE `owner` = ?");
            
            if ($query) {
                $query->bindParam(1, $user);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) <= 0) {
                        $query = self::$connection->prepare("INSERT INTO `user_account_data` (`owner`) VALUES (?)");
                        if ($query) {
                            $query->bindParam(1, $user);
                            
                            return $query->execute();
                        }
                    } else {
                        return true;
                    }
                }
            }
        } catch (PDOException $error) {}

        return false;
    }

    public function findProfile(int $userId) {
        try {
            $query = self::$connection->prepare("SELECT `pin`,`2fa`,`token`,`panic` FROM `user_account_data` WHERE `owner` = ?");
            
            if ($query) {
                $query->bindParam(1, $user);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (count($data) >= 0) {
                        $data = $data[0];
    
                        return array(
                            'pin' => $data['pin'],
                            '2fa' => $data['2fa'],
                            'token' => $data['token'],
                            'panic' => $data['panic']
                        );
                    }
                }
            }
        } catch (PDOException $error) {}

        return null;
    }

    public function saveProfile(int $userId, string $key, string|null $value) {
        try {
            if ($value != null) {
                $query = self::$connection->prepare("UPDATE `user_account_data` SET `{$key}` = ? WHERE `owner` = ?");
            } else {
                $query = self::$connection->prepare("UPDATE `user_account_data` SET `{$key}` = NULL WHERE `owner` = ?");
            }
            
            if ($query) {
                if ($value != null) {
                    $query->bindParam(1, $value);
                    $query->bindParam(2, $user);
                } else {
                    $query->bindParam(1, $user);
                }
                
                return $query->execute();
            }
        } catch (PDOException $error) {}

        return false;
    }
}