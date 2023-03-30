<?php

namespace KarmaDev\Panel\Client;

use KarmaDev\Panel\Configuration;

use KarmaDev\Panel\SQL\Notifications as Notification;

use PDO;
use PDOException;

use DateTime;
use DateTimeZone;

class User {

    private int $id;
    private string $uuid;
    private string $name;
    private string $email;
    private array $settings;
    
    public function __construct(int $id, string $uuid, string $name, string $email, array $settings) {
        $this->id = $id;
        $this->uuid = $uuid;
        $this->name = $name;
        $this->email = $email;
        $this->settings = $settings;
    }

    public function getIdentifier() {
        return $this->id;
    }

    public function getUniqueIdentifier() {
        return $this->uuid;
    }

    public function getName() {
        return $this->name;
    }

    public function getEmail() {
        return $this->email;
    }

    public function getSetting(string $key) {
        return $this->settings[$key];
    }

    public function getGroup() {
        try {
            $config = new Configuration();

            $host = $config->getSQLHost();
            $port = $config->getSQLPort();
            $database = $config->getSQLDatabase();

            $user = $config->getSQLUser();
            $pass = $config->getSQLPassword();

            $connection = new PDO("mysql:host={$host};port={$port};dbname={$database}", $user, $pass);

            $id = $this->id;
            $query = $connection->prepare("SELECT `group` FROM `{$config->getUsersTable()}` WHERE `id` = {$id}");
            
            if ($query && $query->execute()) {
                $group_id = $query->fetchAll(PDO::FETCH_ASSOC)[0]['group'];

                $query = $connection->prepare("SELECT `display` FROM `{$config->getGroupsTable()}` WHERE `id` = {$group_id}");

                if ($query && $query->execute()) {
                    return $query->fetchAll(PDO::FETCH_ASSOC)[0]['display'];
                }
            }
        } catch (PDOException $error) {
            echo 'Failed to check for permission name: ' . $error->getMessage();
        }

        return "Unknown";
    }

    public function getGroupPriority() {
        try {
            $config = new Configuration();

            $host = $config->getSQLHost();
            $port = $config->getSQLPort();
            $database = $config->getSQLDatabase();

            $user = $config->getSQLUser();
            $pass = $config->getSQLPassword();

            $connection = new PDO("mysql:host={$host};port={$port};dbname={$database}", $user, $pass);

            $id = $this->id;
            $query = $connection->prepare("SELECT `group` FROM `{$config->getUsersTable()}` WHERE `id` = {$id}");

            if ($query && $query->execute()) {
                $group_id = $query->fetchAll(PDO::FETCH_ASSOC)[0]['group'];

                $query = $connection->prepare("SELECT `priority` FROM `{$config->getGroupsTable()}` WHERE `id` = {$group_id}");

                if ($query && $query->execute()) {
                    return $query->fetchAll(PDO::FETCH_ASSOC)[0]['priority'];
                }
            }
        } catch (PDOException $error) {
            echo 'Failed to check for permission priority: ' . $error->getMessage();
        }

        return 0;
    }

    public function isStaff() {
        try {
            $config = new Configuration();

            $host = $config->getSQLHost();
            $port = $config->getSQLPort();
            $database = $config->getSQLDatabase();

            $user = $config->getSQLUser();
            $pass = $config->getSQLPassword();

            $connection = new PDO("mysql:host={$host};port={$port};dbname={$database}", $user, $pass);

            $id = $this->id;
            $query = $connection->prepare("SELECT `group` FROM `{$config->getUsersTable()}` WHERE `id` = {$id}");
            
            if ($query && $query->execute()) {
                $group_id = $query->fetchAll(PDO::FETCH_ASSOC)[0]['group'];

                $query = $connection->prepare("SELECT `staff` FROM `{$config->getGroupsTable()}` WHERE `id` = {$group_id}");

                if ($query && $query->execute()) {
                    return $query->fetchAll(PDO::FETCH_ASSOC)[0]['staff'];
                }
            }
        } catch (PDOException $error) {
            echo 'Failed to check for permission staff status: ' . $error->getMessage();
        }

        return false;
    }

    public function getFriend($target_user) {
        try {
            $config = new Configuration();

            $host = $config->getSQLHost();
            $port = $config->getSQLPort();
            $database = $config->getSQLDatabase();

            $user = $config->getSQLUser();
            $pass = $config->getSQLPassword();

            $connection = new PDO("mysql:host={$host};port={$port};dbname={$database}", $user, $pass);

            $id = $this->id;
            $query = $connection->prepare("SELECT * FROM `{$config->getFriendsTable()}` WHERE `origin` = {$id}");
            
            if ($query && $query->execute()) {
                foreach ($query as $queryId => $queryData) {
                    if ($queryData['target'] == $target_user) {
                        $c_date = $queryData['since'];

                        if ($c_date != null) {
                            $newTimezone = new DateTime($c_date);
                            $newTimezone->setTimezone(new DateTimeZone('UTC'));

                            $c_date = $newTimezone->format('U');
                        }

                        return array(
                            'source' => $id,
                            'target' => $target_user,
                            'following' => $queryData['following'],
                            'since' => ($c_date != null ? date('d/m/Y H:i:s', $c_date) : null)
                        );
                    }
                }
            }
        } catch (PDOException $error) {
            echo 'Failed to retrieve friend: ' . $error->getMessage();
        }

        return array(
            'source' => $id,
            'target' => $target_user,
            'following' => 0,
            'since' => null
        );
    }

    public function hasPermission(string $permission) {
        try {
            $config = new Configuration();

            $host = $config->getSQLHost();
            $port = $config->getSQLPort();
            $database = $config->getSQLDatabase();

            $user = $config->getSQLUser();
            $pass = $config->getSQLPassword();

            $connection = new PDO("mysql:host={$host};port={$port};dbname={$database}", $user, $pass);

            $id = $this->id;
            $query = $connection->prepare("SELECT `group` FROM `{$config->getUsersTable()}` WHERE `id` = {$id}");

            if ($query && $query->execute()) {
                $group_id = $query->fetchAll(PDO::FETCH_ASSOC)[0]['group'];
                $query = $connection->prepare("SELECT * FROM `{$config->getGroupsTable()}` WHERE `id` = {$group_id}");
                if ($query && $query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC)[0];

                    $priority = max(0, $data['priority']);
                    $allPermissions = [];
                    while ($priority >= 0) {
                        $tmpQuery = $connection->prepare("SELECT * FROM `{$config->getGroupsTable()}` WHERE `priority` = {$priority}");
                        if ($tmpQuery && $tmpQuery->execute()) {
                            $tmpData = $tmpQuery->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($tmpData as $key => $value) {
                                $tmpId = $value['id'];

                                $pQuery = $connection->prepare("SELECT * FROM `{$config->getGroupPermissionsTable()}` WHERE `user_group` = {$tmpId}");
                                if ($pQuery && $pQuery->execute()) {
                                    $pData = $pQuery->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($pData as $key => $value) {
                                        $pri = $value['privilege'];
                                        array_push($allPermissions, $pri);
                                    }
                                }
                            }
                        }

                        $priority--;
                    }

                    foreach ($allPermissions as $key) {
                        $query = $connection->prepare("SELECT `name` FROM `{$config->getPermissionsTable()}` WHERE `id` = {$key}");

                        if ($query && $query->execute()) {
                            $nData = $query->fetchAll(PDO::FETCH_ASSOC)[0];
                            if ($nData['name'] == $permission) {
                                return true;
                            }
                        }
                    }
                }
            }
        } catch (PDOException $error) {
            echo 'Failed to check for permissions: ' . $error->getMessage();
        }

        return false;
    }

    public function notify(string $title, string $content) {
        $notification = new Notification();
        $notification->writeNotification($this->id, $title, $content);
    }

    public function read(int $nId) {
        $notification = new Notification();
        $notification->readNotification($nId);
    }

    public function unread(int $nId) {
        $notification = new Notification();
        $notification->unreadNotification($nId);
    }

    public function remove(int $nId) {
        $notification = new Notification();
        $notification->removeNotification($nId);
    }

    public function getUnread() {
        $unread = [];
        $notification = new Notification();
        $all = $notification->getNotifications($this->id);

        foreach ($all as $nId => $data) {
            if (!$data['read']) {
                $unread[$nId] = $data;
            }
        }

        return $unread;
    }

    public function getRead() {
        $read = [];

        $notification = new Notification();
        $all = $notification->getNotifications($this->id);

        foreach ($all as $nId => $data) {
            if ($data['read']) {
                $read[$nId] = $data;
            }
        }

        return $read;
    }

    public function getAll() {
        $notification = new Notification();
        return $notification->getNotifications($this->id);
    }
}