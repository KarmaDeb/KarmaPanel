<?php 

namespace KarmaDev\Panel;

class Configuration {

    private array $config;

    public function __construct() {
        $this->config = json_decode(file_get_contents('/var/www/panel/vendor/config.json'), true);
    }

    public function getSecretKey() {
        return $this->config['secret'];
    }

    public function getSecretIV() {
        return $this->config['iv'];
    }


    public function getSQLUser() {
        return $this->config['mysql']['username'];
    }

    public function getSQLPassword() {
        return $this->config['mysql']['password'];
    }

    public function getSQLDatabase() {
        return $this->config['mysql']['database'];
    }

    public function getSQLHost() {
        return $this->config['mysql']['host'];
    }

    public function getSQLPort() {
        return $this->config['mysql']['port'];
    }

    public function getSQLSecure() {
        return $this->config['mysql']['ssl'];
    }

    public function getSQLCertificate() {
        return $this->config['mysql']['certificates'];
    }


    
    public function getLocalSQLHost() {
        return $this->config['mysql']['local']['host'];
    }

    public function getLocalSQLPort() {
        return $this->config['mysql']['local']['port'];
    }



    public function getTablePrefix() {
        return $this->config['table']['prefix'];
    }

    public function getUsersTable() {
        return $this->config['table']['prefix'] . $this->config['table']['users'];
    }

    public function getPostsTable() {
        return $this->config['table']['prefix'] . $this->config['table']['posts'];
    }

    public function getTopicsTable() {
        return $this->config['table']['prefix'] . $this->config['table']['topics'];
    }

    public function getCommentsTable() {
        return $this->config['table']['prefix'] . $this->config['table']['comments'];
    }

    public function getLikesTable() {
        return $this->config['table']['prefix'] . $this->config['table']['likes'];
    }

    public function getPermissionsTable() {
        return $this->config['table']['prefix'] . $this->config['table']['permissions'];
    }

    public function getGroupsTable() {
        return $this->config['table']['prefix'] . $this->config['table']['groups'];
    }

    public function getGroupPermissionsTable() {
        return $this->config['table']['prefix'] . $this->config['table']['group_permissions'];
    }

    public function getNotificationsTable() {
        return $this->config['table']['prefix'] . $this->config['table']['notifications'];
    }

    public function getAPIMinecraftTable() {
        return $this->config['table']['prefix'] . $this->config['table']['api_minecraft'];
    }

    public function getAPIRequestsTable() {
        return $this->config['table']['prefix'] . $this->config['table']['api_requests'];
    }

    public function getFriendsTable() {
        return $this->config['table']['prefix'] . $this->config['table']['friends'];
    }


    public function getHomePath() {
        return $this->config['home'];
    }

    public function getWorkingDirectory() {
        return $this->config['workDir'];
    }



    public function getMailerAccount() {
        return $this->config['mailer']['account'];
    }

    public function getDisplayAccount() {
        return $this->config['mailer']['fake'];
    }

    public function getMailerPassword() {
        return $this->config['mailer']['password'];
    }

    public function getMailerHost() {
        return $this->config['mailer']['host'];
    }

    public function getMailerPort() {
        return $this->config['mailer']['port'];
    }


    public function getPatreonId() {
        return $this->config['patreon']['client_id'];
    }

    public function getPatreonSecret() {
        return $this->config['patreon']['client_secret'];
    }
}