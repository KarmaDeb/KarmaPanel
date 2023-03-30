<?php

namespace KarmaDev\Panel\SQL;

use KarmaDev\Panel\Configuration;

use PDO;
use PDOException;

class Minecraft {

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

    public function store(string $name, string $online, string $offline) {
        try {
            $config = new Configuration();
            $online_short = str_replace('-', '', $online);
            $offline_short = str_replace('-', '', $offline);

            $query = self::$connection->prepare("INSERT INTO `{$config->getAPIMinecraftTable()}` (`nick`,`online`,`offline`,`online_short`,`offline_short`) VALUES (?,?,?,?,?)");
            if ($query) {
                $query->bindParam(1, $name);
                $query->bindParam(2, $online);
                $query->bindParam(3, $offline);
                $query->bindParam(4, $online_short);
                $query->bindParam(5, $offline_short);

                return $query->execute();
            }
        } catch (PDOException $error) {}

        return false;
    }

    public function update(string $name, string $offline, string $online) {
        try {
            $config = new Configuration();
            $online_short = str_replace('-', '', $online);
            $offline_short = str_replace('-', '', $offline);

            $query = self::$connection->prepare("DELETE FROM `{$config->getAPIMinecraftTable()}` WHERE `nick` = '{$name}'");
            if ($query && $query->execute()) {
                $query = self::$connection->prepare("INSERT INTO `{$config->getAPIMinecraftTable()}` (`nick`,`online`,`offline`,`online_short`,`offline_short`) VALUES (?,?,?,?,?)");
                if ($query) {
                    $query->bindParam(1, $name);
                    $query->bindParam(2, $online);
                    $query->bindParam(3, $offline);
                    $query->bindParam(4, $online_short);
                    $query->bindParam(5, $offline_short);

                    $query->execute();
                }
            }
        } catch (PDOException $error) {}
    }

    public function fetch(string $param) {
        try {
            $config = new Configuration();
            if ($param == '*') {
                $query = self::$connection->prepare("SELECT `nick`,`online`,`offline` FROM `{$config->getAPIMinecraftTable()}`");
            } else {
                $query = self::$connection->prepare("SELECT `nick`,`online`,`offline` FROM `{$config->getAPIMinecraftTable()}` WHERE `nick` LIKE ? OR `online` = ? OR `offline` = ? OR `online_short` = ? OR `offline_short` = ?");
            
                if ($query) {
                    $nameParam = '%' . $param . '%';

                    $query->bindParam(1, $nameParam);
                    $query->bindParam(2, $param);
                    $query->bindParam(3, $param);
                    $query->bindParam(4, $param);
                    $query->bindParam(5, $param);
                }
            }
            
            if ($query && $query->execute()) {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);
                $info = [];

                foreach ($data as $key => $values) {
                    $nick = $values['nick'];
                    
                    $online = $values['online'];
                    $offline = $values['offline'];

                    $online_short = ($online != null ? str_replace('-', '', $online) : null);
                    $offline_short = ($offline != null ? str_replace('-', '', $offline) : null);
                    
                    $info[$nick] = array(
                        'online' => $online,
                        'offline' => $offline,
                        'online_short' => $online_short,
                        'offline_short' => $offline_short
                    );
                }
                
                if (sizeof($info) >= 1) {
                    return $info;
                }
            }
        } catch (PDOException $error) {
            echo $error;
        }

        return null;
    }
}