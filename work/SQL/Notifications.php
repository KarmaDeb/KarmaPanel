<?php

namespace KarmaDev\Panel\SQL;

use KarmaDev\Panel\SQL\ClientData;

use KarmaDev\Panel\Configuration;

use KarmaDev\Panel\Codes\ResultCodes as Code;

use KarmaDev\Panel\Client\User;
use KarmaDev\Panel\Client\Mailer;
use KarmaDev\Panel\Client\UserLess;

use KarmaDev\Panel\Utilities as Utils;

use PDO;
use PDOException;
use DateTime;

class Notifications {

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

    public function writeNotification(int $target, string $title, string $content) {
        try {
            $config = new Configuration();

            $query = self::$connection->prepare("INSERT INTO `{$config->getNotificationsTable()}` (`title`,`content`,`date`,`user`) VALUES (?,?,?,?)");
            if ($query) {
                $date = time();

                $query->bindParam(1, $title);
                $query->bindParam(2, $content);
                $query->bindParam(3, $date);
                $query->bindParam(4, $target);

                if ($query->execute()) {
                    $clientConnection = new ClientData();

                    $data = $clientConnection->loadProfileData($target);
                    $settings = json_decode($data['setting'], true);
                    if ($settings['email_notifications']) {
                        $mailer = new Mailer($data['email']);

                        $host = $_SERVER['SERVER_NAME'];
                        $notifications_url = "https://{$host}{$config->getHomePath()}profile/notifications";
                        $settings_url = "https://{$host}{$config->getHomePath()}profile/?view=settings";

                        $mailer->send('You have unread notifications!', 'new_notification', array(
                            'username' => $data['name'],
                            'title' => $title,
                            'content' => str_split($content, 6)[0] . ' ...',
                            'not_url' => $notifications_url,
                            'account_settings' => $settings_url
                        ));
                    }
                }
            }
        } catch (PDOException $error) {
            echo $error->getMessage();
        }
    }

    public function readNotification(int $id) {
        try {
            $config = new Configuration();

            $query = self::$connection->prepare("UPDATE `{$config->getNotificationsTable()}` SET `is_read` = 1 WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $id);

                $query->execute();
            }
        } catch (PDOException $error) {}
    }

    public function unreadNotification(int $id) {
        try {
            $config = new Configuration();

            $query = self::$connection->prepare("UPDATE `{$config->getNotificationsTable()}` SET `is_read` = 0 WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $id);

                $query->execute();
            }
        } catch (PDOException $error) {}
    }

    public function removeNotification(int $id) {
        try {
            $config = new Configuration();

            $query = self::$connection->prepare("DELETE FROM `{$config->getNotificationsTable()}` WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $id);

                $query->execute();
            }
        } catch (PDOException $error) {}
    }

    public function getNotifications(int $owner) {
        $notifications = [];

        try {
            $config = new Configuration();

            $query = self::$connection->prepare("SELECT `id`,`date`,`title`,`is_read`,`content` FROM `{$config->getNotificationsTable()}` WHERE `user` = ?");
            if ($query) {
                $query->bindParam(1, $owner);

                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($data as $row => $info) {
                        $notifications[$info['id']] = array(
                            'date' => $info['date'],
                            'title' => $info['title'],
                            'read' => boolval($info['is_read']),
                            'info' => $info['content']
                        );
                    }
                }
            }
        } catch (PDOException $error) {}

        return $notifications;
    }
}