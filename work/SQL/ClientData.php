<?php
namespace KarmaDev\Panel\SQL;

use KarmaDev\Panel\Configuration;

use KarmaDev\Panel\Codes\ResultCodes as Code;

use KarmaDev\Panel\Validator\TextValidator as Validator;

use KarmaDev\Panel\Utilities as Utils;

use KarmaDev\Panel\Client\User;

use KarmaDev\Panel\UUID;

use KarmaDev\Panel\SQL\Notifications as Notification;

use PDO;
use PDOException;

use DateTime;
use DateTimeZone;

class ClientData {

    private static $connection;

    public function __construct() {
        if (self::$connection == null) {
            try {
                $config = new Configuration();

                $host = $config->getSQLHost();
                $port = $config->getSQLPort();
                $database = $config->getSQLDatabase();

                $user = $config->getSQLUser();
                $pass = $config->getSQLPassword();

                self::$connection = new PDO("mysql:host={$host};port={$port};dbname={$database}", $user, $pass);
            } catch (PDOException $error) {
                echo 'Failed to initialize database connection: ' . $error->getMessage();
            }
        }
    }

    public function getRegisteredUsers() {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `id` FROM `{$config->getUsersTable()}`");

            $ids = [];
            if ($query && $query->execute()) {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($data as $fId => $uData) {
                    array_push($ids, $uData['id']);
                }
            }

            return $ids;
        } catch (PDOException $error) {}

        return null;
    }

    public function register(string $email, string $name, string $password, bool $remember, bool $auto_complete = false) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `id` FROM `{$config->getUsersTable()}` WHERE `origin` = ? OR `email` = ? OR `name` = ?");
            if ($query) {
                $query->bindParam(1, $email);
                $query->bindParam(2, $email);
                $query->bindParam(3, $name);
                
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (sizeof($data) >= 1) {
                        return Code::err_register_exists();
                    } else {
                        $token = Utils::generate();
                        $store = true;
                        if (isset($_POST['noSession'])) {
                            $store = !$_POST['noSession'];
                            unset($_POST['noSession']);
                        }
    
                        if ($store) {
                            if (str_ends_with($email, 'karmadev.es')) {
                                $query = self::$connection->prepare("INSERT INTO `{$config->getUsersTable()}` (`id`,`email`,`origin`,`name`,`photo`,`password`,`address`,`token`,`remember`) VALUES (". rand() .",?,?,?,?,?,?,?,?)");
                            } else {
                                $query = self::$connection->prepare("INSERT INTO `{$config->getUsersTable()}` (`email`,`origin`,`name`,`photo`,`password`,`address`,`token`,`remember`) VALUES (?,?,?,?,?,?,?,?)");
                            }
                            
                            if ($query) {
                                Utils::setGlobal($email, $token);
                                Utils::setGlobal($name, $token);
                                
                                $password = Utils::encode($password);
                                $address = Utils::encode(Utils::getUserIpAddr());
                                $token = Utils::encode($token);
                                $r_token = Utils::generate();
    
                                Utils::setGlobal('r_' . $email, $r_token);
                                Utils::setGlobal('r_' . $name, $r_token);
    
                                if ($remember) {
                                    Utils::set('remember', $r_token);
                                }
                                $r_token = Utils::encode($r_token);
                                $photo = Utils::default_photo();
    
                                $query->bindParam(1, $email);
                                $query->bindParam(2, $email);
                                $query->bindParam(3, $name);
                                $query->bindParam(4, $photo);
                                $query->bindParam(5, $password);
                                $query->bindParam(6, $address);
                                $query->bindParam(7, $token);
                                $query->bindParam(8, $r_token);
        
                                if ($query->execute()) {
                                    if ($auto_complete) {
                                        $query = self::$connection->prepare("UPDATE `{$config->getUsersTable()}` SET `token` = NULL WHERE `email` = ?");
                                        if ($query) {
                                            $query->bindParam(1, $email);
                                            $query->execute();
                                        }
                                    }

                                    return Code::success();
                                } else {
                                    return Code::err_register_sql();
                                }
                            } else {
                                return Code::err_register_sql();
                            }
                        } else {
                            Utils::set('tmp_token', $token);
                        }
                    }
                } else {
                    return Code::err_register_sql();
                }
            }
        } catch (PDOException $error) {}

        return Code::err_register_unknown();
    }

    public function authenticate(string $paramNoE, string $password, bool $remember) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `token`,`password`,`remember` FROM `{$config->getUsersTable()}` WHERE `origin` = ? OR `email` = ? OR `name` = ?");

            if ($query) {
                $query->bindParam(1, $paramNoE);
                $query->bindParam(2, $paramNoE);
                $query->bindParam(3, $paramNoE);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (sizeof($data) >= 1) {
                        $data = $data[0];
    
                        $token = $data['token'];
                        if ($token == null) {
                            $token = base64_decode($data['password']);
    
                            if (Utils::auth($password, $token)) {
                                if ($remember) {
                                    $n_token = Utils::get('remember');
                                    $token = $data['remember'];
                                    
                                    if ($n_token == null || !Utils::auth($n_token, $token)) {
                                        Utils::set('remember', Utils::getGlobal('r_' . $paramNoE));
                                    }
                                }
                                
                                if (isset($_POST['noSession'])) {
                                    if (!$_POST['noSession']) {
                                        unset($_POST['noSession']);
                                        Utils::set('last_action', time());
                                        Utils::set('client', serialize(self::getUser($paramNoE, $password)));
                                    }
                                } else {
                                    Utils::set('last_action', time());
                                    Utils::set('client', serialize(self::getUser($paramNoE, $password)));
                                }
                                
                                return Code::success();
                            } else {
                                return Code::err_login_invalid();
                            }
                        } else {
                            return Code::err_login_unverified();
                        }
                    } else {
                        return Code::err_login_exists();
                    }
                } else {
                    return Code::err_login_sql();
                }
            }
        } catch (PDOException $error) {}

        return Code::err_login_unknown();
    }

    public function ch4ng3acc3ske7(int $clientId, string $password) {
        /*
        Code responses:
        1200 = SUCCESS
        1400 = FAILED
        1000 = UNKNOWN SQL ERROR
        2000 = UNEXPECTED SQL ERROR
        1500 = UNKNOWN
        */
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("UPDATE `{$config->getUsersTable()}` SET `password` = ? WHERE `id` = ?");

            if ($query) {
                $target = $clientId;
                $new = Utils::encode($password);

                $query->bindParam(1, $new);
                $query->bindParam(2, $target);

                return ($query->execute() ? 1200 : 1400);
            } else {
                return 1000;
            }
        } catch (PDOException $error) {
            return 2000;
        }

        return 1500;
    }

    public function assignPatreon(string $paramNoE, string $token, string $refresh) {
        return Code::err_patreon_unknown();
    }

    public function loadPatreon(string $paramNoE) {
        return null;
    }

    public function generateRemember(string $paramNoE) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `id`,`email`,`name` FROM `{$config->getUsersTable()}` WHERE `origin` = ? OR `email` = ? OR `name` = ?");

            if ($query) {
                $query->bindParam(1, $paramNoE);
                $query->bindParam(2, $paramNoE);
                $query->bindParam(3, $paramNoE);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (sizeof($data) >= 1) {
                        $data = $data[0];
                        $id = $data['id'];
    
                        $email = $data['email'];
                        $name = $data['name'];
    
                        $query = self::$connection->prepare("UPDATE `{$config->getUsersTable()}` SET `remember` = ? WHERE `id` = ?");
                        if ($query) {
                            $token = Utils::generate();
    
                            Utils::setGlobal('r_' . $email, $r_token);
                            Utils::setGlobal('r_' . $name, $r_token);
    
                            Utils::set('remember', $token);
    
                            $token = Utils::encode($token);
    
                            $query->bindParam(1, $token);
                            $query->bindParam(2, $id);
                        }
                    } else {
                        return Code::err_remember_exists();
                    }
                } else {
                    return Code::err_remember_sql();
                }
            }
        } catch (PDOException $error) {}

        return Code::err_remember_unknown();
    }

    public function verify(string $paramNoE, string $token) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `token` FROM `{$config->getUsersTable()}` WHERE `origin` = ? OR `email` = ? OR `name` = ?");

            if ($query) {
                $query->bindParam(1, $paramNoE);
                $query->bindParam(2, $paramNoE);
                $query->bindParam(3, $paramNoE);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (sizeof($data) >= 1) {
                        $data = $data[0];
    
                        $validation = $data['token'];
                        if ($validation != null) {
                            $validation = base64_decode($validation);
    
                            if (Utils::auth($token, $validation)) {
                                $validation = base64_encode($validation);
                                $query = self::$connection->prepare("UPDATE `{$config->getUsersTable()}` SET `token` = NULL WHERE `token` = ?");
                                if ($query) {
                                    $query->bindParam(1, $validation);

                                    if ($query->execute()) {
                                        return Code::success();
                                    } else {
                                        return Code::err_verify_sql();
                                    }
                                } else {
                                    return Code::err_verify_sql();
                                }
                            } else {
                                return Code::err_verify_invalid();
                            }
                        } else {
                            return Code::err_verify_already();
                        }
                    } else {
                        return Code::err_verify_exists();
                    }
                } else {
                    return Code::err_verify_sql();
                }
            }
        } catch (PDOException $error) {}

        return Code::err_verify_unknown();
    }

    public function validateSession(string $paramNoE) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `address` FROM `{$config->getUsersTable()}` WHERE `origin` = ? OR `email` = ? OR `name` = ?");

            if ($query) {
                $query->bindParam(1, $paramNoE);
                $query->bindParam(2, $paramNoE);
                $query->bindParam(3, $paramNoE);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (sizeof($data) >= 1) {
                        $data = $data[0];
    
                        $address = Utils::getUserIpAddr();
                        $token = $data['address'];
                
                        if (Utils::auth($address, $token)) {
                            return Code::success();
                        } else {
                            return Code::err_login_invalid();
                        }
                    } else {
                        return Code::err_login_exists();
                    }
                } else {
                    return Code::err_login_sql();
                }
            }
        } catch (PDOException $error) {}

        return Code::err_login_unknown();
    }

    public function validateRememberToken(string $paramNoE) {
        try {
            $config = new Configuration();
            $token = Utils::get('remember');

            if ($token != null) {
                $query = self::$connection->prepare("SELECT `remember` FROM `{$config->getUsersTable()}` WHERE `origin` = ? OR `email` = ? OR `name` = ?");
    
                if ($query) {
                    $query->bindParam(1, $paramNoE);
                    $query->bindParam(2, $paramNoE);
                    $query->bindParam(3, $paramNoE);
    
                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                        if (sizeof($data) >= 1) {
                            $data = $data[0];
    
                            $remember = base64_decode($data['remember']);
                            if (Utils::auth($token, $remember)) {
                                if (isset($_POST['noSession'])) {
                                    if (!$_POST['noSession']) {
                                        unset($_POST['noSession']);
                                        Utils::set('last_action', time());
                                        Utils::set('client', serialize(self::getUser($paramNoE, null, true)));
                                    }
                                } else {
                                    Utils::set('last_action', time());
                                    Utils::set('client', serialize(self::getUser($paramNoE, null, true)));
                                }
    
                                return Code::success();
                            } else {
                                return Code::err_remember_invalid();
                            }
                        } else {
                            return Code::err_remember_exists();
                        }
                    } else {
                        return Code::err_remember_sql();
                    }
                }
            } else {
                return Code::err_remember_invalid();
            }
        } catch (PDOException $error) {}

        return Code::err_remember_unknown();
    }

    public function getUser(string $paramNoE) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `id`,`name`,`origin`,`email`,`uuid`,`setting` FROM `{$config->getUsersTable()}` WHERE `origin` = ? OR `email` = ? or `name` = ?");

            if ($query) {
                $query->bindParam(1, $paramNoE);
                $query->bindParam(2, $paramNoE);
                $query->bindParam(3, $paramNoE);
    
                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
    
                    if (sizeof($data) >= 1) {
                        $data = $data[0];
    
                        $id = $data['id'];
                        $name = $data['name'];
                        $origin_email = $data['origin'];
                        $email = $data['email'];
                        $uuid = $data['uuid'];
                        $settings = json_decode($data['setting'], true);

                        if ($uuid == null) {
                            $uuid = UUID::retrieveId($name);
                            if (empty($uuid)) {
                                $uuid = UUID::offlineId($origin_email); //Always using the email to generate UUIDs
                            }

                            $query = self::$connection->prepare("UPDATE `{$config->getUsersTable()}` SET `uuid` = ? WHERE `id` = ?");

                            if ($query) {
                                $query->bindParam(1, $uuid);
                                $query->bindParam(2, $id);

                                $query->execute();
                            }
                        }

                        return new User($id, $uuid, $name, $email, $settings);
                    }
                }
            }
        } catch (PDOException $error) {}

        return null;
    }

    public function updateProfilePhoto(int $id, string $image) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("UPDATE `{$config->getUsersTable()}` SET `photo` = ? WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $image);
                $query->bindParam(2, $id);

                $query->execute();
            }
        } catch (PDOException $error) {}
    }

    public function updateDescription(int $id, string $description) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("UPDATE `{$config->getUsersTable()}` SET `description` = ? WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $description);
                $query->bindParam(2, $id);

                $query->execute();
            }
        } catch (PDOException $error) {}
    }

    public function loadProfileData($profile) {
        try {
            $config = new Configuration();
            if (is_numeric($profile)) {
                $query = self::$connection->prepare("SELECT `id`,`uuid`,`email`,`name`,`photo`,`description`,`password`,`address`,`token`,`remember`,`registered_at`,`group`,`setting` FROM `{$config->getUsersTable()}` WHERE `id` = ?");
            } else {
                $query = self::$connection->prepare("SELECT `id`,`uuid`,`email`,`name`,`photo`,`description`,`password`,`address`,`token`,`remember`,`registered_at`,`group`,`setting` FROM `{$config->getUsersTable()}` WHERE `name` = ? OR `email` = ? OR `origin` = ?");
            }
            
            if ($query) {
                if (is_numeric($profile)) {
                    $profileId = intval($profile);
                    $query->bindParam(1, $profileId);
                } else {
                    $query->bindParam(1, $profile);
                    $query->bindParam(2, $profile);
                    $query->bindParam(3, $profile);
                }
                

                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);

                    if (sizeof($data) >= 1) {
                        $data = $data[0];

                        $response = array();
                        $response['id'] = $data['id'];
                        $response['uuid'] = $data['uuid'];
                        $response['email'] = $data['email'];
                        $response['name'] = $data['name'];
                        $response['photo'] = $data['photo'];
                        $response['description'] = $data['description'];
                        $response['password'] = $data['password'];
                        $response['patreon'] = null;
                        $response['address'] = $data['address'];
                        $response['token'] = $data['token'];
                        $response['remember'] = $data['remember'];
                        $response['registered'] = $data['registered_at'];
                        $response['group'] = $data['group'];
                        $response['setting'] = $data['setting'];

                        return $response;
                    }
                }
            }
        } catch (PDOException $error) {}

        return [];
    }

    public function saveSettings(int $id, array $settings) {
        try {
            $config = new Configuration();
            $tmpSettings = json_encode($settings);

            $query = self::$connection->prepare("UPDATE `{$config->getUsersTable()}` SET `setting` = ? WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $tmpSettings);
                $query->bindParam(2, $id);

                $query->execute();
            }
        } catch (PDOException $error) {}
    }

    public function generateAPIKey() {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            $id = $cl->getIdentifier();
            try {
                $config = new Configuration();
                
                $query = self::$connection->prepare("SELECT `api_key` FROM `{$config->getUsersTable()}` WHERE `id` = ?");
                if ($query) {
                    $query->bindParam(1, $id);

                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);

                        if (sizeof($data) >= 1) {
                            $data = $data[0];

                            if ($data['api_key'] == null) {
                                $api_key = Utils::generateAPIKey();

                                $private = $api_key['private'];

                                $query = self::$connection->prepare("UPDATE `{$config->getUsersTable()}` SET `api_key` = ? WHERE `id` = ?");
                                if ($query) {
                                    $query->bindParam(1, $private);
                                    $query->bindParam(2, $id);

                                    if ($query->execute()) {
                                        return rtrim(base64_encode($api_key['public']), '=');
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (PDOException $error) {}
        }

        return array(
            'private' => '',
            'public' => ''
        );
    }

    public function revokeAPIKey() {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            $id = $cl->getIdentifier();
            try {
                $config = new Configuration();
                
                $query = self::$connection->prepare("SELECT `api_key` FROM `{$config->getUsersTable()}` WHERE `id` = ?");
                if ($query) {
                    $query->bindParam(1, $id);

                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);

                        if (sizeof($data) >= 1) {
                            $data = $data[0];

                            if ($data['api_key'] != null) {
                                $query = self::$connection->prepare("UPDATE `{$config->getUsersTable()}` SET `api_key` = NULL WHERE `id` = ?");

                                if ($query) {
                                    $query->bindParam(1, $id);
                                    return $query->execute();
                                }
                            }
                        }
                    }
                }
            } catch (PDOException $error) {}
        }

        return false;
    }

    public function loadAPIKey(string $api_key) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `id`,`email`,`name`,`photo`,`description`,`password`,`address`,`token`,`api_key`,`remember`,`registered_at`,`group`,`setting` FROM `{$config->getUsersTable()}` WHERE `api_key` IS NOT NULL");
            if ($query && $query->execute()) {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);

                foreach ($data as $dataId => $user) {
                    $raw_password = $user['api_key'];
                    if (password_verify($raw_password, $api_key)) {
                        $response = array();
                        $response['id'] = $user['id'];
                        $response['email'] = $user['email'];
                        $response['name'] = $user['name'];
                        $response['photo'] = $user['photo'];
                        $response['description'] = $user['description'];
                        $response['password'] = $user['password'];
                        $response['patreon'] = null;
                        $response['address'] = $user['address'];
                        $response['token'] = $user['token'];
                        $response['remember'] = $user['remember'];
                        $response['registered'] = $user['registered_at'];
                        $response['group'] = $user['group'];
                        $response['setting'] = $user['setting'];

                        return $response;
                    }
                }
            }
        } catch (PDOException $error) {}

        return [];
    }

    public function updateFriendStatus(int $target_user, bool|null $new_follow_status, bool|null $new_friend_status) {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            $id = $cl->getIdentifier();
            try {
                $config = new Configuration();
                
                $query = self::$connection->prepare("SELECT `origin`,`target`,`since`,`following` FROM `{$config->getFriendsTable()}` WHERE `origin` = ? AND `target` = ? AND `origin` = ? AND `target` = ?");
                if ($query) {
                    $query->bindParam(1, $id);
                    $query->bindParam(2, $target_user);

                    $query->bindParam(3, $target_user);
                    $query->bindParam(4, $id);

                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);

                        $isNewFollow = Utils::getGlobal('follow_' . $id . '_' . $target_user);
                        if ($isNewFollow == null) {
                            $isNewFollow = true;
                        }

                        if (!isset($data[0])) {
                            $query = self::$connection->prepare("INSERT INTO `{$config->getFriendsTable()}` (`origin`,`target`,`following`) VALUES (?,?,?)");
                            if ($query) {
                                $query->bindParam(1, $id);
                                $query->bindParam(2, $target_user);
                                if ($new_follow_status == null) {
                                    $new_follow_status = 0;
                                }

                                $query->bindParam(3, $new_follow_status);

                                $query->execute();
                            }

                            $data[0] = array(
                                'origin' => $id,
                                'target' => $target_user,
                                'since' => null,
                                'following' => ($new_follow_status != null ? $new_follow_status : false)
                            );
                        }

                        $source = $data[0];
                        if (isset($data[1])) {
                            $target = $data[1];
                        }

                        if ($new_follow_status != null) {
                            if ($source['following'] != $new_follow_status) {
                                $query = self::$connection->prepare("UPDATE `{$config->getFriendsTable()}` SET `following` = ? WHERE `origin` = ? AND `target` = ?");

                                if ($query) {
                                    $query->bindParam(1, $new_follow_status);
                                    $query->bindParam(2, $id);
                                    $query->bindParam(3, $target_user);

                                    $query->execute();
                                }

                                if ($new_follow_status && $isNewFollow) {
                                    Utils::setGlobal('follow_' . $id . '_' . $target_user, 0);
                                    
                                    //TODO: Send new follow notification to target

                                    $notification = new Notification();
                                    $notification->writeNotification($target_user, 'New follower', $cl->getName() . ' is now following you!');
                                }
                            }
                        }

                        if ($new_friend_status != null) {
                            if (!isset($target)) {
                                //Basically, this is a new friend request. So we must prepare the notification and all the stuff

                                $query = self::$connection->prepare("UPDATE `{$config->getFriendsTable()}` SET `since` = current_timestamp() WHERE `origin` = ? AND `target` = ?");
                                if ($query) {
                                    $query->bindParam(1, $id);
                                    $query->bindParam(2, $target_user);

                                    if ($query->execute()) {
                                        //TODO: Send friend request notification

                                        $notification = new Notification();
                                        $notification->writeNotification($target_user, 'Want to be friend?', $cl->getName() . ' wants to friend you!');
                                    }
                                }
                            } else {
                                if ($new_friend_status) {
                                    //Accept friend request

                                    $query = self::$connection->prepare("UPDATE `{$config->getFriendsTable()}` SET `since` = current_timestamp() WHERE `origin` = ? AND `target` = ? AND `origin` = ? AND `target` = ?");
                                
                                    if ($query) {
                                        $query->bindParam(1, $id);
                                        $query->bindParam(2, $target_user);

                                        $query->bindParam(3, $target_user);
                                        $query->bindParam(4, $id);

                                        $query->execute();

                                        //TODO: Send new friend notification

                                        $notification = new Notification();
                                        $notification->writeNotification($target_user, 'You have a new friend!', $cl->getName() . ' and you are now friends!');
                                    }
                                } else {
                                    //Decline friend request
                                    $query = self::$connection->prepare("UPDATE `{$config->getFriendsTable()}` SET `since` = NULL WHERE `origin` = ? AND `target` = ? AND `origin` = ? AND `target` = ?");

                                    if ($query) {
                                        $query->bindParam(1, $id);
                                        $query->bindParam(2, $target_user);

                                        $query->bindParam(3, $target_user);
                                        $query->bindParam(4, $id);

                                        $query->execute();
                                        
                                        //TODO: Send friend declined notification

                                        $notification = new Notification();
                                        $notification->writeNotification($target_user, 'Oopsie!', $cl->getName() . ' declined your friend request.');
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (PDOException $error) {
                echo $error;
            }
        }
    }

    public function performRequest(int $user, string|null $method, string|null $action, array|null $data) {
        $method = ($method != null ? $method : 'undefined');
        $action = ($action != null ? $action : 'undefined');

        $data = ($data != null ? json_encode($data, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_LINE_TERMINATORS) : '');

        try {
            $config = new Configuration();
            $query = self::$connection->prepare("INSERT INTO `{$config->getAPIRequestsTable()}` (`id`,`method`,`action`,`data`) VALUES (?,?,?,?)");
            if ($query) {
                $query->bindParam(1, $user);
                $query->bindParam(2, $method);
                $query->bindParam(3, $action);
                $query->bindParam(4, $data);

                $query->execute();
            }
        } catch (PDOException $error) {}
    }

    public function fetchRequests(int $user) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `id`,`request_id`,`method`,`action`,`data`,`issued` FROM `{$config->getAPIRequestsTable()}` WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $user);

                if ($query->execute()) {
                    $requests = array();

                    $data = $query->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($data as $keyId => $requestData) {
                        $reqData = [];

                        if ($requestData['method'] != 'undefined') {
                            $reqData['method'] = $requestData['method'];
                        }
                        if ($requestData['action'] != 'undefined') {
                            $reqData['action'] = $requestData['action'];
                        }
                        if ($requestData['data'] != '') {
                            $reqData['data'] = Utils::parseFullyJson($requestData['data']);
                        }
                        
                        $c_date = $requestData['issued'];

                        $newTimezone = new DateTime($c_date, new DateTimeZone('UTC'));
                        $newTimezone->setTimezone(new DateTimeZone(Utils::get('timezone')));

                        $c_date = $newTimezone->format('U');

                        $reqData['modification'] = date("d/m/y H:i:s", $c_date);
                        
                        if (sizeof($reqData) > 0) {
                            $requests[$requestData['request_id']] = $reqData;
                        }
                    }

                    return $requests;
                }
            }
        } catch (PDOException $error) {}

        return array();
    }

    public function hasAPIKey(int $user) {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            $id = $cl->getIdentifier();
            try {
                $config = new Configuration();
                
                $query = self::$connection->prepare("SELECT `api_key` FROM `{$config->getUsersTable()}` WHERE `id` = ?");
                if ($query) {
                    $query->bindParam(1, $id);

                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);

                        if (sizeof($data) >= 1) {
                            $data = $data[0];

                            return $data['api_key'] != null;
                        }
                    }
                }
            } catch (PDOException $error) {}
        }

        return false;
    }

    public static function isAuthenticated() {
        $last_action = Utils::get('last_action');

        if ($last_action != null) {
            $max = $last_action + 1800;

            if ($max > time()) {
                return true;
            } else {
                Utils::set('client', null);
            }
        }

        return false;
    }

    public static function performAction(string $doing) {
        if (self::isAuthenticated()) {
            Utils::set('last_action', time());
            $cl = unserialize(Utils::get('client'));

            Utils::addOnlineUser($cl->getIdentifier(), $cl->getName(), $doing);
        }
    }

    public static function close() {
        if (self::isAuthenticated()) {
            Utils::set('last_action', null);
            Utils::set('client', null);
        }
    }
}