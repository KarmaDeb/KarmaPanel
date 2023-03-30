<?php

namespace KarmaDev\Panel\SQL;

use KarmaDev\Panel\Configuration;

use KarmaDev\Panel\Codes\ResultCodes as Code;
use KarmaDev\Panel\Codes\PostStatus as Status;

use KarmaDev\Panel\Client\User;
use KarmaDev\Panel\Client\Mailer;
use KarmaDev\Panel\Client\UserLess;

use KarmaDev\Panel\Utilities as Utils;

use PDO;
use PDOException;
use DateTime;
use DateTimeZone;

class PostData {

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

    public function createPost(string $title, string $content, string $topic, array $tags) {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            $tags = array_chunk($tags, 10, true)[0];

            $config = new Configuration();
            try {
                $topicData = $this->getTopic($topic);

                if ($topicData != null) {
                    $topicId = $topicData['id'];

                    $query = self::$connection->prepare("INSERT INTO `{$config->getPostsTable()}` (`id`,`title`,`content`,`topic`,`tags`,`owner`,`status`,`modified`) VALUES (?,?,?,{$topicId},?,?,?,?)");
                    if ($query) {                    
                        $id = $cl->getIdentifier() . '.' . bin2hex(random_bytes(4));
    
                        $tagVal = '';
                        $index = 0;
                        foreach ($tags as $tag) {
                            $tag = preg_replace('/^[^a-zA-Z]$/', '', $tag);
    
                            if (!empty($tag)) {
                                if ($index == 0) {
                                    $tagVal = $tag;
                                } else {
                                    $tagVal = $tagVal . ',' . $tag;
                                }
    
                                $index++;
                            }
                        }
    
                        if (empty($tagVal)) {
                            $tagVal = 'im,karmapanel';
                        }
                        
                        $owner = $cl->getIdentifier();
                        $status = Status::post_pending_approval();
    
                        $day = date("w");
                        switch ($day) {
                            case 0:
                                $day = "Sunday";
                                break;
                            case 1:
                                $day = "Monday";
                                break;
                            case 2:
                                $day = "Tuesday";
                                break;
                            case 3:
                                $day = "Wednesday";
                                break;
                            case 4:
                                $day = "Thursday";
                                break;
                            case 5:
                                $day = "Friday";
                                break;
                            case 6:
                                $day = "Saturday";
                                break;
                        }
    
                        $date = $day . " " . date("d/m/Y H:i:s");
                        $title = str_split($title, 48)[0];
    
                        $query->bindParam(1, $id);
                        $query->bindParam(2, $title);
                        $query->bindParam(3, $content);
                        $query->bindParam(4, $tagVal);
                        $query->bindParam(5, $owner);
                        $query->bindParam(6, $status);
                        $query->bindParam(7, $date);
    
                        if ($query->execute()) {
                            return $id;
                        }
                    } else {
                        return Code::err_post_sql();
                    }
                } else {
                    return Code::err_post_unknown_topic();
                }
            } catch (PDOException $error) {
                echo "Failed to create post ( " . $error->getMessage() . " )\r\n";
            }

            return Code::err_post_unknown();
        } else {
            return Code::err_post_auth();
        }
    }

    public function setPostStatus(string $post, int $issuer, int $status) {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            $proceed = false;
            $p = $this->getPost($post);

            $config = new Configuration();
            if (sizeof($p) >= 1) {
                $isOwner = $p['owner'] == $cl->getIdentifier();

                switch ($status) {
                    case Status::post_pending_approval():
                        $proceed = $cl->hasPermission('manage_post');
                        break;
                    case Status::post_active():
                        $proceed = $cl->hasPermission('approve_post') || ($isOwner && $p['status']['issuer'] == $cl->getIdentifier());
                        break;
                    case Status::post_private():
                        $proceed = $cl->hasPermission('disable_post') || $isOwner;
                        break;
                    case Status::post_removed():
                        $proceed = $cl->hasPermission('remove_other_post') || ($isOwner && $cl->hasPermission('remove_post') );
                        break;
                }

                if ($proceed) {
                    try {
                        $query = self::$connection->prepare("UPDATE `{$config->getPostsTable()}` SET `status` = ? WHERE `id` = ?");
                        if ($query) {
                            $posts = self::getPostsMade();
                            if ($posts == -1) {
                                $posts = 1;
                            }
                        
                            $query->bindParam(1, $status);
                            $query->bindParam(2, $post);
        
                            if ($query->execute()) {
                                $query = self::$connection->prepare("UPDATE `{$config->getPostsTable()}` SET `status_issuer` = ? WHERE `id` = ?");

                                if ($query) {
                                    $query->bindParam(1, $issuer);
                                    $query->bindParam(2, $post);

                                    if ($query->execute()) {
                                        $query = self::$connection->prepare("UPDATE `{$config->getPostsTable()}` SET `visibility_issuer` = ? WHERE `id` = ?");

                                        if ($query) {
                                            $query->bindParam(1, $issuer);
                                            $query->bindParam(2, $post);

                                            $query->execute();
                                        }
                                    }
                                }

                                if (!$isOwner) {
                                    try {
                                        $config = new Configuration();
                                        $query = self::$connection->prepare("SELECT `email` FROM `{$config->getUsersTable()}` WHERE `id` = {$p['owner']}");
                                        if ($query && $query->execute()) {
                                            $data = $query->fetchAll(PDO::FETCH_ASSOC)[0];
                            
                                            $email = $data['email'];
                                            if ($email != null) {
                                                $mail = new Mailer($email);

                                                $host = $_SERVER['SERVER_NAME'];
                                                $post_preview = "https://{$host}{$config->getHomePath()}?post={$post}";

                                                $result = $mail->send('Check your post!', 'post_status_changed', array(
                                                    'username' => $this->getProfile($p['owner']),
                                                    'post_title' => $p['title'],
                                                    'post_status' => Status::text($status),
                                                    'administrator' => $this->getProfile($issuer),
                                                    'post_url' => $post_preview
                                                ));

                                                if ($result) {
                                                    return Code::success();
                                                } else {
                                                    return Code::err_post_unknown();
                                                }
                                            }
                                        }
                                    } catch (PDOException $error) {}
                                }

                                return Code::success();
                            }
                        } else {
                            return Code::err_post_sql();
                        }
                    } catch (PDOException $error) {}
                }
            }
            
            return Code::err_post_unknown();
        } else {
            return Code::err_post_auth();
        }
    }

    public function editPost(string $post, string $title, string $content) {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            $config = new Configuration();
            try {
                $p = $this->getPost($post);
                if (sizeof($p) >= 1) {
                    $isOwner = $p['owner'] = $cl->getIdentifier();

                    if ($isOwner || $cl->hasPermission(Permission::edit_post())) {
                        $query = self::$connection->prepare("UPDATE `{$config->getPostsTable()}` SET `title` = ? WHERE `id` = ?");

                        if ($query) {
                            $query->bindParam(1, $title);
                            $query->bindParam(2, $post);

                            if ($query->execute()) {
                                $query = self::$connection->prepare("UPDATE `{$config->getPostsTable()}` SET `content` = ? WHERE `id` = ?");

                                if ($query) {
                                    $query->bindParam(1, $content);
                                    $query->bindParam(2, $post);

                                    if ($query->execute()) {
                                        $query = self::$connection->prepare("UPDATE `{$config->getPostsTable()}` SET `modified` = ? WHERE `id` = ?");
                                        
                                        if ($query) {
                                            $day = date("w");
                                            switch ($day) {
                                                case 0:
                                                    $day = "Sunday";
                                                    break;
                                                case 1:
                                                    $day = "Monday";
                                                    break;
                                                case 2:
                                                    $day = "Tuesday";
                                                    break;
                                                case 3:
                                                    $day = "Wednesday";
                                                    break;
                                                case 4:
                                                    $day = "Thursday";
                                                    break;
                                                case 5:
                                                    $day = "Friday";
                                                    break;
                                                case 6:
                                                    $day = "Saturday";
                                                    break;
                                            }
                        
                                            $date = $day . " " . date("d/m/Y H:i:s");

                                            $query->bindParam(1, $date);
                                            $query->bindParam(2, $post);

                                            if ($query->execute()) {
                                                $query = self::$connection->prepare("UPDATE `{$config->getPostsTable()}` SET `last_iteraction` = current_timestamp() WHERE `id` = ?");
                                                if ($query) {
                                                    $query->bindParam(1, $post);

                                                    return Code::success();
                                                } else {
                                                    return Code::err_post_sql();
                                                }
                                            } else {
                                                return Code::err_post_sql();
                                            }
                                        } else {
                                            return Code::err_post_sql();
                                        }
                                    } else {
                                        return Code::err_post_sql();
                                    }
                                } else {
                                    return Code::err_post_sql();
                                }
                            }
                        } else {
                            return Code::err_post_sql();
                        }
                    }
                }
            } catch (PDOException $error) {
                Utils::set('last_error', $error->getMessage());
            }

            return Code::err_post_unknown();
        } else {
            return Code::err_post_auth();
        }
    }

    public function getPost(string $post) {
        $data = [];

        $config = new Configuration();
        try {
            $query = self::$connection->prepare("SELECT `id`,`title`,`content`,`tags`,`modified`,`owner`,`status`,`status_issuer`,`visibility_issuer`,`topic` FROM `{$config->getPostsTable()}` WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $post);

                if ($query->execute()) {
                    $result = $query->fetchAll(PDO::FETCH_ASSOC);

                    if (sizeof($result) >= 1) {
                        $result = $result[0];

                        $topicId = $result['topic'];
                        $query = self::$connection->prepare("SELECT `display`,`internal_name`,`color` FROM `{$config->getTopicsTable()}` WHERE `id` = ?");
                        if ($query) {
                            $query->bindParam(1, $topicId);

                            if ($query->execute()) {
                                $topicData = $query->fetchAll(PDO::FETCH_ASSOC);

                                if (count($topicData) >= 1) {
                                    $topicData = $topicData[0];

                                    $data['post'] = $result['id'];
                                    $data['title'] = $result['title'];
                                    $data['content'] = str_replace("\n", "[/lb]", $result['content']);
                                    $data['tags'] = $result['tags'];
                                    $data['modified'] = $result['modified'];
                                    $data['owner'] = $result['owner'];
                                    $data['status'] = array(
                                        'code' => $result['status'],
                                        'issuer' => $result['status_issuer'],
                                        'visibility' => $result['visibility_issuer']
                                    );
                                    $data['topic'] = array(
                                        'id' => $topicId,
                                        'display' => $topicData['display'],
                                        'localizer' => $topicData['internal_name'],
                                        'color' => $topicData['color']
                                    );
            
                                    $commentQuery = self::$connection->prepare("SELECT `id`,`post`,`comment`,`created`,`owner` FROM `{$config->getCommentsTable()}` WHERE `post` = ?");
                                    $likeQuery = self::$connection->prepare("SELECT `post`,`owner` FROM `{$config->getLikesTable()}` WHERE `post` = ?");
                                    if ($commentQuery && $likeQuery) {
                                        $commentQuery->bindParam(1, $post);
                                        $likeQuery->bindParam(1, $post);
            
                                        $comments = [];
                                        $likes = [];
                                        if ($commentQuery->execute() && $likeQuery->execute()) {
                                            $commentData = $commentQuery->fetchAll(PDO::FETCH_ASSOC);
                                            $likeData = $likeQuery->fetchAll(PDO::FETCH_ASSOC);
            
                                            foreach ($commentData as $key => $value) {
                                                $c_owner = $value['owner'];
                                                $c_id = $value['id'];
                                                $c_creation = $value['created'];
                                                $newTimezone = new DateTime($c_creation);
                                                $newTimezone->setTimezone(new DateTimeZone('UTC'));

                                                $c_creation = $newTimezone->format('U');
            
                                                $name = $this->getProfile($c_owner);
                                                if ($name != null) {
                                                    if (isset($comments[$c_id])) {
                                                        $userComments = $comments[$c_id];
                                                    } else {
                                                        $userComments = [];
                                                    }
            
                                                    $userComments[$name] = array(
                                                        'comment' => $value['comment'],
                                                        'user_id' => $c_owner,
                                                        'creation' => $c_creation
                                                    );
                                                    $comments[$c_id] = $userComments;
                                                }
                                            }
            
                                            $anon_users = 1;
                                            foreach ($likeData as $key => $value) {
                                                $l_owner = $value['owner'];
            
                                                $name = $this->getProfile($l_owner);

                                                if ($name != null) {
                                                    $c_connection = new ClientData();
                                                    $c_settings = $c_connection->loadProfileData($name);
                                                    $c_settings = json_decode($c_settings['setting'], true);

                                                    if ($c_settings['visibility'] != 'public') {
                                                        if ($c_settings['visibility'] == 'friends') {
                                                            if (ClientData::isAuthenticated()) {
                                                                $current_user = unserialize(Utils::get('client'));

                                                                if (!$current_user->isStaff() && $current_user->isFriend($l_owner)) {
                                                                    array_push($likes, 'Anonymous Cow #' . $anon_users++);
                                                                    continue;
                                                                }
                                                            }
                                                        } else {
                                                            if (ClientData::isAuthenticated()) {
                                                                $current_user = unserialize(Utils::get('client'));

                                                                if (!$current_user->isStaff() && $l_owner != $current_user->getIdentifier()) {
                                                                    array_push($likes, 'Anonymous Cow #' . $anon_users++);
                                                                    continue;
                                                                }
                                                            }
                                                        }
                                                    }
                                                    
                                                    array_push($likes, $name);
                                                }
                                            }
                                        }
            
                                        $data['comments'] = $comments;
                                        $data['likes'] = $likes;
                                    }
                                }
                            }
                        }
                    }  
                }
            }
        } catch (PDOException $error) {}

        return $data;
    }

    public function comment(string $post, string $comment) {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            $config = new Configuration();
            try {
                $clientId = $cl->getIdentifier();

                $query = self::$connection->prepare("INSERT INTO `{$config->getCommentsTable()}` (`post`,`comment`,`owner`) VALUES (?,?,?)");
                if ($query) {
                    $query->bindParam(1, $post);
                    $query->bindParam(2, $comment);
                    $query->bindParam(3, $clientId);

                    if ($query->execute()) {
                        $query = self::$connection->prepare("UPDATE `{$config->getPostsTable()}` SET `last_iteraction` = current_timestamp() WHERE `id` = ?");
                        if ($query) {
                            $query->bindParam(1, $post);

                            return $query->execute();
                        }
                    }
                }
            } catch (PDOException $error) {
                echo "Failed to post comment at post {$post}: " . $error->getMessage();
            }
        }

        return false;
    }

    public function like(string $post) {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            $config = new Configuration();
            try {
                $clientId = $cl->getIdentifier();
                $query = self::$connection->prepare("SELECT `post` FROM `{$config->getLikesTable()}` WHERE `post` = ? AND `owner` = ?");
                if ($query) {
                    $query->bindParam(1, $post);
                    $query->bindParam(2, $clientId);

                    if ($query->execute()) {
                        $data = $query->fetchAll(PDO::FETCH_ASSOC);
                        if (sizeof($data) >= 1) {
                            $query = self::$connection->prepare("DELETE FROM `{$config->getLikesTable()}` WHERE `post` = ? AND `owner` = ?");
                            if ($query) {
                                $query->bindParam(1, $post);
                                $query->bindParam(2, $clientId);

                                return $query->execute();
                            }
                        } else {
                            $query = self::$connection->prepare("INSERT INTO `{$config->getLikesTable()}` (`post`,`owner`) VALUES (?,?)");
                            if ($query) {
                                $query->bindParam(1, $post);
                                $query->bindParam(2, $clientId);

                                return $query->execute();
                            }
                        }
                    }
                }
            } catch (PDOException $error) {
                echo "Failed to post comment at post {$post}: " . $error->getMessage();
            }
        }

        return false;
    }

    public function getCommentAndLikes(int $id) {
        $data = [];

        $config = new Configuration();
        try {
            $query = self::$connection->prepare("SELECT `post` FROM `{$config->getLikesTable()}` WHERE `owner` = ?");

            if ($query) {
                $query->bindParam(1, $id);

                if ($query->execute()) {
                    $likesData = $query->fetchAll(PDO::FETCH_ASSOC);

                    $dataLikes = [];
                    foreach ($likesData as $lId => $info) {
                        $post = $this->getPost($info['post']);
                        if ($post['status']['code'] == Status::post_active()) {
                            array_push($dataLikes, array(
                                'id' => $post['post'],
                                'title' => $post['title'],
                                'content' => $post['content'],
                                'comments' => count($post['comments']),
                                'likes' => count($post['likes'])
                            ));
                        }
                    }

                    $data['likes'] = $dataLikes;
                }
            }

            $query = self::$connection->prepare("SELECT `post`,`created`,`comment` FROM `{$config->getCommentsTable()}` WHERE `owner` = ?");

            if ($query) {
                $query->bindParam(1, $id);

                if ($query->execute()) {
                    $commentsData = $query->fetchAll(PDO::FETCH_ASSOC);

                    $dataComments = [];
                    foreach ($commentsData as $cId => $info) {
                        $post = $this->getPost($info['post']);

                        if ($post['status']['code'] == Status::post_active()) {
                            $c_creation = $info['created'];
                            
                            $newTimezone = new DateTime($c_creation, new DateTimeZone('UTC'));
                            $newTimezone->setTimezone(new DateTimeZone(Utils::get('timezone')));

                            $c_creation = $newTimezone->format('U');

                            $dataComments[$cId] = array(
                                'id' => $post['post'],
                                'title' => $post['title'],
                                'content' => $post['content'],
                                'comments' => count($post['comments']),
                                'likes' => count($post['likes']),
                                'comment' => $info['comment'],
                                'created' => date('d/m/Y H:i:s', $c_creation)
                            );
                        }
                    }

                    $data['comments'] = $dataComments;
                }
            }
        } catch (PDOException $error) {}

        return $data;
    }

    public function getProfile(int $id) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `name` FROM `{$config->getUsersTable()}` WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $id);

                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC)[0];

                    return $data['name'];
                }
            }
        } catch (PDOException $error) {}

        return null;
    }

    public function getGroup(int $id) {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT `group` FROM `{$config->getUsersTable()}` WHERE `id` = ?");
            if ($query) {
                $query->bindParam(1, $id);

                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC)[0];

                    $g_id = $data['group'];
                    $query = self::$connection->prepare("SELECT `display` FROM `{$config->getGroupsTable()}` WHERE `id` = ?");

                    if ($query) {
                        $query->bindParam(1, $id);

                        if ($query->execute()) {
                            return $query->fetchAll(PDO::FETCH_ASSOC)[0]['display'];
                        }
                    }
                }
            }
        } catch (PDOException $error) {}

        return "unknown";
    }

    public function getAllPosts(string|null $q = null, bool $order_by_modified = true) {
        $posts = [];

        $config = new Configuration();
        try {
            $order = ($order_by_modified ? 'modified' : 'last_iteraction');
            $query = self::$connection->prepare("SELECT `id` FROM `{$config->getPostsTable()}` ORDER BY `{$order}`");

            if ($query && $query->execute()) {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);

                foreach ($data as $id => $info) {
                    $tmpData = $this->getPost($info['id']);
                    if ($q != null && !empty($q)) {
                        if (strpos(strtolower($q), "tags:") === 0) {
                            $tmpQuery = str_replace('tags:', '', strtolower($q));

                            $query_tags = explode(',', strtolower($tmpQuery));
                            $tags = explode(',', strtolower($tmpData['tags']));
                            
                            foreach ($query_tags as $tag) {
                                $tag = preg_replace('/^[^a-zA-Z]$/', '', $tag);

                                if (!empty($tag)) {
                                    foreach ($tags as $pT) {
                                        if (strtolower($pT) == strtolower($tag)) {
                                            $posts[$id] = $tmpData;
                                        }
                                    }
                                }
                            }
                        } else {
                            if (strpos(strtolower($q), "author:") === 0) {
                                $tmpQuery = str_replace('author:', '', strtolower($q));

                                $owner = $tmpData['owner'];
                                $name = $this->getProfile($owner);
                                if (strval($owner) == $tmpQuery || strtolower($name) == strtolower($tmpQuery)) {
                                    $posts[$id] = $tmpData;
                                }
                            } else {
                                if (str_contains(strtolower($tmpData['title']), strtolower($q))) {
                                    $posts[$id] = $tmpData;
                                }
                            }
                        }
                    } else {
                        $posts[$id] = $tmpData;
                    }
                }
            }
        } catch (PDOException $error) {}

        return $posts;
    }

    public function getActivePosts(string|null $q = null, bool $order_by_modified = true) {
        $posts = [];

        $config = new Configuration();
        try {
            $order = ($order_by_modified ? 'modified' : 'last_iteraction');
            $query = self::$connection->prepare("SELECT `id` FROM `{$config->getPostsTable()}` WHERE `status` = 1 ORDER BY `{$order}`");

            if ($query && $query->execute()) {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);

                foreach ($data as $id => $info) {
                    $tmpData = $this->getPost($info['id']);
                    if ($q != null && !empty($q)) {
                        if (strpos(strtolower($q), "tags:") === 0) {
                            $tmpQuery = str_replace('tags:', '', strtolower($q));

                            $query_tags = explode(',', strtolower($tmpQuery));
                            $tags = explode(',', strtolower($tmpData['tags']));
                            
                            foreach ($query_tags as $tag) {
                                $tag = preg_replace('/^[^a-zA-Z]$/', '', $tag);

                                if (!empty($tag)) {
                                    foreach ($tags as $pT) {
                                        if (strtolower($pT) == strtolower($tag)) {
                                            $posts[$id] = $tmpData;
                                        }
                                    }
                                }
                            }
                        } else {
                            if (strpos(strtolower($q), "author:") === 0) {
                                $tmpQuery = str_replace('author:', '', strtolower($q));

                                $owner = $tmpData['owner'];
                                $name = $this->getProfile($owner);
                                if (strval($owner) == $tmpQuery || strtolower($name) == strtolower($tmpQuery)) {
                                    $posts[$id] = $tmpData;
                                }
                            } else {
                                if (str_contains(strtolower($tmpData['title']), strtolower($q))) {
                                    $posts[$id] = $tmpData;
                                }
                            }
                        }
                    } else {
                        $posts[$id] = $tmpData;
                    }
                }
            }
        } catch (PDOException $error) {}

        return $posts;
    }

    public function getUserPosts(int $client) {
        $data = [];

        $config = new Configuration();
        try {
            $query = self::$connection->prepare("SELECT `id` FROM `{$config->getPostsTable()}` ORDER BY `modified`");

            if ($query && $query->execute()) {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);

                foreach ($data as $id => $info) {
                    $data[$id] = $this->getPost($info['id']);
                }
            }
        } catch (PDOException $error) {}

        $clientId = -1;
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));
            $clientId = $cl->getIdentifier();
        }

        foreach ($data as $id => $postData) {
            $status = $postData['status']['code'];
            if ($status == Status::post_active() || $client == $clientId) {
                if ($postData['owner'] != $client) {
                    $data[$id] = null;
                }
            } else {
                $data[$id] = null;
            }
        }
        
        if (!isset($data)) {
            $data = [];
        }

        $fixedData = [];

        foreach ($data as $id => $postData) {
            if ($postData != null) {
                array_push($fixedData, $postData);
            }
        }

        return $fixedData;
    }

    public static function getPostsMade() {
        try {
            $config = new Configuration();
            $query = self::$connection->prepare("SELECT COUNT(*) FROM `{$config->getPostsTable()}`");
            if ($query && $query->execute()) {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);

                return $data[0]['COUNT(*)'];
            }
        } catch (PDOException $error) {}

        return -1;
    }

    public function getTopics() {
        $topics = [];

        try {
            $config = new Configuration();

            $query = self::$connection->prepare("SELECT `internal_name`,`display`,`color` FROM `{$config->getTopicsTable()}`");
            if ($query && $query->execute()) {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);

                foreach ($data as $fId => $info) {
                    $topics[$info['id']] = array(
                        'internal' => $info['internal_name'],
                        'display' => $info['display'],
                        'color' => $info['color']
                    );
                }
            }
        } catch (PDOException $error) {}

        return $topics;
    }

    public function getTopic(string $internal) {
        try {
            $config = new Configuration();

            $query = self::$connection->prepare("SELECT `id`,`display`,`color` FROM `{$config->getTopicsTable()}` WHERE `internal_name` = ?");
            if ($query) {
                $query->bindParam(1, $internal);

                if ($query->execute()) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);

                    if (count($data) >= 1) {
                        $data = $data[0];

                        return array(
                            'id' => $data['id'],
                            'internal' => $internal,
                            'display' => $data['display'],
                            'color' => $data['color']
                        );
                    }
                }
            }
        } catch (PDOException $error) {}

        return null;
    }
}