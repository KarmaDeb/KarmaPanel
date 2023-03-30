<?php
if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
$config = new Configuration();

include $config->getWorkingDirectory() . 'vendor/autoload.php';

use KarmaDev\Panel\SQL\Minecraft;
use KarmaDev\Panel\SQL\ClientData;
use KarmaDev\Panel\SQL\PostData;
use KarmaDev\Panel\SQL\LockLogin;

use KarmaDev\Panel\Codes\PostStatus as Post;
use KarmaDev\Panel\Codes\ResultCodes as Code;

use KarmaDev\Panel\UUID;

use KarmaDev\Panel\Utilities as Utils;

use Ramsey\Uuid\Nonstandard\Uuid as UUIDValidator;
use PragmaRX\Google2FA\Google2FA;

use Patreon\API;

header('Content-Type: application/json');

if (!function_exists('getallheaders')) { 
    function getallheaders() { 
       $headers = array ();

       foreach ($_SERVER as $name => $value) { 
           if (substr($name, 0, 5) == 'HTTP_') { 
               $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
           } 
       }

       return $headers; 
    } 
} 

$response = [];

$headers = getallheaders();
foreach($headers as $header_key => $header_value) {
    if (strtolower($header_key) == 'access-key') {
        $access_key = base64_decode($header_value . '=');
    }
}

$data = Utils::buildPost(null, "api", "method", "action", "post", "create_messages", "query", "email", "username", "password", "token", "nosession", "stay", "title", "content", "tags", "topic", "value", "notification", "id", "plugin_version", "server", "name", "parent");
$method = $data['method'];

if ($method != null) {
    if (strtolower($method) == 'api' || strtolower($method) == 'plugin') {
        $action = $data['action'];

        if ($action != null) {
            if (strtolower($method) == 'api') {
                switch (strtolower($action)) {
                    case 'request':
                        $isTemp = $data['nosession'];

                        if ($isTemp == 'true' || $isTemp == true || $isTemp == 1) {
                            $tmp_hash = Utils::generateTmpAPIKey();

                            $response['success'] = true;
                            $response['message'] = rtrim(base64_encode($tmp_hash), '=');
                        } else {
                            if (ClientData::isAuthenticated()) {
                                $connection = new ClientData();
                                $api_data = $connection->generateAPIKey();
                                
                                if (gettype($api_data) == 'string') {
                                    $response['success'] = true;
                                    $response['message'] = "Successfully generated API key";
                                    $response['api_key'] = rtrim($api_data, '=');
                                } else {
                                    $response['success'] = false;
                                    $response['message'] = "This account already owns an API key";
                                }
                            } else {
                                $response['success'] = false;
                                $response['message'] = "You need to be authenticated to generate an API key";
                            }
                        }
                        break;
                    case 'revoke':
                        if (ClientData::isAuthenticated()) {
                            $connection = new ClientData();
                            if ($connection->revokeAPIKey()) {
                                $response['success'] = true;
                                $response['message'] = "Successfully revoked API key";
                            } else {
                                $response['success'] = false;
                                $response['message'] = "Failed to revoke API key";
                            }
                        } else {
                            $response['success'] = false;
                            $response['message'] = "You need to be authenticated to generate an API key";
                        }
                        break;
                    case 'test':
                        $key = $data['content'];

                        if ($key != null) {
                            $key = base64_decode($key . '=');

                            if (ClientData::isAuthenticated()) {
                                $cl = unserialize(Utils::get('client'));
                                $connection = new ClientData();

                                $uData = $connection->loadAPIKey($key);
                                if (isset($uData['id']) && $uData['id'] == $cl->getIdentifier()) {
                                    $response['success'] = true;
                                    $response['message'] = "Successfully tested API key";

                                    $connection->performRequest($uData['id'], 'api', 'test', null);
                                } else {
                                    $response['success'] = false;
                                    $response['message'] = "API key not found";
                                }
                            } else {
                                $response['success'] = false;
                                $response['message'] = "You must be authenticated to test an API key";
                            }
                        } else {
                            $response['success'] = false;
                            $response['message'] = "API key must be provided";
                        }
                        break;
                    default:
                        $response['success'] = false;
                        $response['message'] = "Unknown API action: " . $action;
                        break;
                }
            }
        } else {
            $response['success'] = false;
            $response['message'] = "API action must be provided";
        }
    } else {
        if (!isset($access_key)) {
            if ($data['api'] != null) {
                $access_key = base64_decode($data['api'] . '=');
            }
        }

        if (isset($access_key)) {
            $store_request = true;
            
            $connection = new ClientData();
            $access_client = array();

            $access_client = $connection->loadAPIKey($access_key);

            if (!isset($access_client['name']) && Utils::consumeTempApiKey($access_key)) {
                $store_request = false;

                if (ClientData::isAuthenticated()) {
                    $cl = unserialize(Utils::get('client'));

                    $access_client = $connection->loadProfileData($cl->getIdentifier());
                } else {
                    $access_client['id'] = 0;
                    $access_client['email'] = 'karmapanel@no-reply.net';
                    $access_client['name'] = 'Server';
                    $access_client['photo'] = '';
                    $access_client['description'] = 'KarmaPanel server';
                    $access_client['password'] = '';
                    $access_client['patreon'] = null;
                    $access_client['address'] = '';
                    $access_client['token'] = null;
                    $access_client['remember'] = false;
                    $access_client['registered'] = time();
                    $access_client['group'] = 'user';
                    $access_client['setting'] = array();
                }
            }

            if (isset($access_client['name'])) {
                $tmpRequestData = $data;
                unset($tmpRequestData['api']);
                unset($tmpRequestData['method']);
                unset($tmpRequestData['action']);
                if ($tmpRequestData['password'] != null) {
                    $tmpRequestData['password'] = 'REDACTED';
                }
                if ($tmpRequestData['token'] != null) {
                    $tmpRequestData['token'] = 'REDACTED';
                }
                foreach ($tmpRequestData as $key => $value) {
                    if ($value == null) {
                        unset($tmpRequestData[$key]);
                    }
                }
                if (sizeof($tmpRequestData) <= 0) {
                    $tmpRequestData = null;
                }

                switch (strtolower($method)) {
                    case 'timezone':
                        $timezone_offset_minutes = $data['query'];

                        if ($timezone_offset_minutes != null) {
                            $timezone_name = timezone_name_from_abbr("", $timezone_offset_minutes * 60, false);
                            $response['success'] = true;
                            $response['message'] = "Successfully set timezone to " . $timezone_name;

                            Utils::set('timezone', $timezone_name);
                        } else {
                            $response['success'] = false;
                            $response['message'] = "Failed to store timezone";
                        }

                        break;
                    case 'admin':
                        $action = $data['action'];
                        $users = $data['query'];

                        if ($action != null && $action == 'notify') {
                            if (ClientData::isAuthenticated()) {
                                $cl = unserialize(Utils::get('client'));

                                if ($cl->hasPermission('system_notify')) {
                                    $title = $data['title'];
                                    $message = $data['content'];

                                    if ($title != null && $message != null) {
                                        if ($users == null) {
                                            Utils::notifyMultiple(null, $title, $message);
                                        } else {
                                            if (str_contains($users, ',')) {
                                                Utils::notifyMultiple(explode(',', $users), $title, $message);
                                            } else {
                                                Utils::notifyMultiple([$users], $title, $message);
                                            }
                                        }

                                        $response['success'] = true;
                                        $response['message'] = 'Notification has been sent to everyone!';
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = 'Notification title and content must be provided!';
                                    }
                                } else {
                                    $response['success'] = false;
                                    $response['message'] = "Unauthorized";
                                }
                            } else {
                                $response['success'] = false;
                                $response['message'] = "Unknown user!";
                            }
                        } else {
                            $response['success'] = false;
                            $response['message'] = "Unrecognized action";
                        }
                        break;
                    case 'patreon':
                        if (ClientData::isAuthenticated()) {
                            $cl = unserialize(Utils::get('client'));

                            if ($cl->hasPermission('system_notify')) {
                                $action = $data['action'];

                                if ($action != null) {
                                    $api = new API($action);
                                    $response = $api->fetch_user();
                                } else {
                                    $response['success'] = false;
                                    $response['message'] = "Patreon action token must be provided";
                                }
                            } else {
                                $response['success'] = false;
                                $response['message'] = "Unauthorized";
                            }
                        } else {
                            $response['success'] = false;
                            $response['message'] = "Unknown user!";
                        }
                        break;
                    case "minecraft":
                        $action = $data['action'];
                        if ($action != null) {
                            $query = $data['query'];

                            if ($query != null) {
                                switch (strtolower($action)) {
                                    case 'fetch':
                                        $connection = new Minecraft();
                                        $tmpData = $connection->fetch($query);
                                        if ($tmpData != null) {
                                            $response['success'] = true;
                                            $response['message'] = "Fetched minecraft data";
                                            $response['accounts'] = sizeof($tmpData);
                        
                                            foreach ($tmpData as $key => $info) {
                                                $response[$key] = array(
                                                    'online' => array(
                                                        'uuid' => $info['online'],
                                                        'trimmed' => $info['online_short']
                                                    ),
                                                    'offline' => array(
                                                        'uuid' => $info['offline'],
                                                        'trimmed' => $info['offline_short']
                                                    )
                                                );
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "No data found";
                                            $response['accounts'] = 0;
                                        }
                                        break;
                                    case 'create':
                                        $connection = new Minecraft();

                                        $online = UUID::retrieveId($query);
                                        $offline = UUID::offlineId($query);

                                        $online_short = str_replace('-', '', $online);
                                        $offline_short = str_replace('-', '', $offline);

                                        if (empty($online)) {
                                            $online = null;
                                        }

                                        if (!$connection->store($query, $offline, $online)) {
                                            $connection->update($query, $offline, $online);
                                        }

                                        $response['success'] = true;
                                        $response['message'] = "Created user info";
                                        $response['accounts'] = 1;
                                        $response[$query] = array(
                                            'online' => array(
                                                'uuid' => $online,
                                                'trimmed' => $online_short
                                            ),
                                            'offline' => array(
                                                'uuid' => $offline,
                                                'trimmed' => $offline_short
                                            )
                                        );
                                        break;
                                    default:
                                        break;
                                }
                            } else {
                                $response['success'] = false;
                                $response['message'] = "A search query must be provided";
                            }
                        } else {
                            $response['success'] = false;
                            $response['message'] = "Search method must be specified";
                        }
                        
                        break;
                    case "post":
                        $action = $data['action'];

                        if ($action != null) {
                            switch (strtolower($action)) {
                                case 'create':
                                    $title = strip_tags($data['title']);
                                    $content = strip_tags($data['content']);
                                    $tags = strip_tags($data['tags']);
                                    $topic = strip_tags($data['topic']);

                                    if ($title != null && $content != null && $tags != null && $topic != null) {
                                        $connection = new PostData();
                                        $result = $connection->createPost($title, $content, $topic, explode(',', $tags));

                                        $response['success'] = is_string($result);
                                        if (is_string($result)) {
                                            $response['message'] = $result;
                                        } else {
                                            $response['message'] = Post::parse($result);
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "Title, content, tags and topic must be provided!";
                                    }
                                    break;
                                case 'edit':
                                    $title = strip_tags($data['title']);
                                    $content = strip_tags($data['content']);
                                    $post = strip_tags($data['post']);

                                    if ($title != null && $content != null && $post != null) {
                                        $connection = new PostData();
                                        $tmpData = $connection->getPost($post);

                                        if (count($tmpData) >= 1) {
                                            $result = $connection->editPost($post, $title, $content);

                                            $response['success'] = $result == 0;
                                            $response['message'] = ($result == 0 ? "Post saved successfully" : "There was an error while editing post {$post}. This error code may help you: {$result}");
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Post with id {$post} does not exists!";
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "New title, new content and post id must be provided!";
                                    }
                                    break;
                                case 'comment':
                                    $content = strip_tags($data['content']);
                                    $id = strip_tags($data['post']);
                                    $c_messages = strip_tags($data['create_messages']);
                                    if (!isset($c_messages) || !is_bool($c_messages)) {
                                        $c_messages = false;
                                    }

                                    if ($id != null && $content != null) {
                                        $postConnection = new PostData();
                                        $tmpData = $postConnection->getPost($id);

                                        if (sizeof($tmpData) >= 1) {
                                            if (ClientData::isAuthenticated()) {
                                                $cl = unserialize(Utils::get('client'));
                                                $last_post = Utils::getGlobal(strval($cl->getIdentifier()));
                                                $proceed = true;
                                                if ($last_post != null) {
                                                    $now = time();

                                                    if ($last_post + 60 > $now) {
                                                        $proceed = false;
                                                    }
                                                }

                                                if ($proceed) {
                                                    if ($postConnection->comment($id, $content)) {
                                                        $response['success'] = true;
                                                        $response['message'] = "The comment has been posted";
                                                    } else {
                                                        if ($c_messages) {
                                                            Utils::set('alert_message', "Failed to post comment. Make sure you are logged in and the comment doesn't contain special characters.<br>
                                                            <br>
                                                            Allowed special characters:<br>
                                                            - _<br>
                                                            - .<br>
                                                            - ,<br>
                                                            - @<br>
                                                            - ñ<br>
                                                            - á-ú<br>
                                                            - ä-ü<br>
                                                            - à-ù<br>
                                                            <br>
                                                            <br>%h");
                                                            Utils::set('alert_type', 'alert-danger');
                                                            Utils::set('alert_header', 'Failed');
                                                        }

                                                        $response['success'] = false;
                                                        $response['message'] = "Special characters have been found while trying to post comment";
                                                    }
                                                } else {
                                                    Utils::set('alert_message', 'Failed to post comment. You are posting too fast<br><br>%h');
                                                    Utils::set('alert_type', 'alert-danger');
                                                    Utils::set('alert_header', 'Failed');
                                                }
                                            } else {
                                                if ($c_messages) {
                                                    Utils::set('alert_message', 'Failed to post comment. The post must be enabled in order to comment<br><br>%h');
                                                    Utils::set('alert_type', 'alert-danger');
                                                    Utils::set('alert_header', 'Failed');
                                                }

                                                $response['success'] = false;
                                                $response['message'] = "Authentication is required in order to post a comment";
                                            }
                                        } else {
                                            if ($c_messages) {
                                                Utils::set('alert_message', 'Failed to post comment. The post does not exists<br><br>%h');
                                                Utils::set('alert_type', 'alert-danger');
                                                Utils::set('alert_header', 'Failed');
                                            }

                                            $response['success'] = false;
                                            $response['message'] = "The post {$id} does not exists";
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "Comment content and post must be provided";
                                    }
                                    break;
                                case 'like':
                                    $id = strip_tags($data['post']);
                                    if ($id != null) {
                                        $postConnection = new PostData();
                                        $tmpData = $postConnection->getPost($id);

                                        if (sizeof($tmpData) >= 9) {
                                            $owner = $tmpData['owner'];
                                            $currentId = -1;

                                            if (ClientData::isAuthenticated()) {
                                                $cl = unserialize(Utils::get('client'));

                                                $currentId = $cl->getIdentifier();
                                                if ($postConnection->like($id)) {
                                                    $response['success'] = true;
                                                    $response['message'] = "Successfully liked/unliked post with id {$id}";
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "Failed to like post with id {$id}";
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "You must be authenticated to like a post";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "A post with id {$id} does not exists";
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "Post id must be specified";
                                    }
                                    break;
                                case 'status':
                                    $id = strip_tags($data['post']);
                                    $code = strip_tags($data['content']);
                                    if ($id != null && $code != null) {
                                        $postConnection = new PostData();
                                        $tmpData = $postConnection->getPost($id);

                                        if (sizeof($tmpData) >= 9) {
                                            $owner = $tmpData['owner'];
                                            $currentId = -1;

                                            if (ClientData::isAuthenticated()) {
                                                $cl = unserialize(Utils::get('client'));

                                                $permission = Post::getPermission($code);

                                                if ($cl->hasPermission($permission)) {
                                                    $currentId = $cl->getIdentifier();

                                                    $code = $postConnection->setPostStatus($id, $currentId, $code);
                                                    if ($code == 0) {
                                                        $response['success'] = true;
                                                        $response['message'] = "Successfully updated status of post " . $id . " ( #" . $code . " )";
                                                    } else {
                                                        $response['success'] = false;
                                                        $response['message'] = "Failed to update status of post " . $id . " ( #" . $code . " )";
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "You need special privileges to set the post in that status!";
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "You must be authenticated to edit a post status";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "A post with id {$id} does not exists";
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "Post id and status code must be specified";
                                    }
                                    break;
                                case 'fetch':
                                    $id = strip_tags($data['post']);

                                    if ($id != null) {
                                        $postConnection = new PostData();
                                        $tmpData = $postConnection->getPost($id);

                                        if (sizeof($tmpData) <= 8) {
                                            $tmpData = $postConnection->getAllPosts();

                                            foreach ($tmpData as $post_id => $post_data) {
                                                if ($id != $postConnection->getProfile($post_data['owner'])) {
                                                    unset($tmpData, $post_id);
                                                }
                                            }
                                        }

                                        if (!isset($tmpData)) {
                                            $tmpData = [];
                                        }

                                        if (sizeof($tmpData) >= 1) {
                                            $response['success'] = true;
                                            $response['message'] = "Displaying post {$id} info";

                                            if (sizeof($tmpData) <= 10) {
                                                $status = $tmpData['status']['code'];

                                                $display = false;
                                                if ($status == Post::post_active()) {
                                                    $display = true;
                                                } else {
                                                    if (ClientData::isAuthenticated()) {
                                                        $cl = unserialize(Utils::get('client'));
                
                                                        if ($cl->hasPermission('approve_post') || $tmpData['owner'] == $cl->getIdentifier()) {
                                                            $display = true;
                                                        }
                                                    }
                                                }
                
                                                if ($display) {
                                                    $tmpData['id'] = $tmpData['post'];
                                                    $tmpData['post'] = array(
                                                        'title' => $tmpData['title'],
                                                        'content' => $tmpData['content'],
                                                        'tags' => explode(',', $tmpData['tags']),
                                                        'metadata' => array(
                                                            'comments' => $tmpData['comments'],
                                                            'likes' => $tmpData['likes']
                                                        )
                                                    );
                                                    $tmpData['date'] = $tmpData['modified'];
                                                    $tmpData['owner'] = array(
                                                        'id' => $tmpData['owner'],
                                                        'name' => $postConnection->getProfile($tmpData['owner']),
                                                        'group' => $postConnection->getGroup($tmpData['owner'])
                                                    );
                                                    $status = [];
                                                    $visibility = [];

                                                    $code = $tmpData['status']['code'];
                                                    $status['code'] = $code;
                                                    $issuer = $tmpData['status']['issuer'];
                                                    if ($issuer != null) {
                                                        $status['admin'] = $issuer;
                                                        $status['name'] = $postConnection->getProfile($issuer);
                                                    }

                                                    $visibility['code'] = $code;
                                                    if ($code == 2 || $code == 1) {
                                                        $issuer = $tmpData['status']['visibility'];
                                                        if ($issuer != null) {
                                                            $visibility['issuer'] = $issuer;
                                                            $visibility['name'] = $postConnection->getProfile($issuer);
                                                        }
                                                    }
                
                                                    unset($tmpData['title']);
                                                    unset($tmpData['content']);
                                                    unset($tmpData['tags']);
                                                    unset($tmpData['comments']);
                                                    unset($tmpData['likes']);

                                                    $tmpData['status'] = $status;
                                                    $tmpData['visibility'] = $visibility;
                                                } else {
                                                    $tmpData['id'] = $id;
                                                    $tmpData['status'] = "Post is not approved!";
                                                }

                                                $response['0'] = $tmpData;
                                            } else {
                                                foreach ($data as $post_id => $post_data) {
                                                    $tmpData = [];

                                                    $status = $post_data['status']['code'];

                                                    $display = false;
                                                    if ($status == Post::post_active()) {
                                                        $display = true;
                                                    } else {
                                                        if (ClientData::isAuthenticated()) {
                                                            $cl = unserialize(Utils::get('client'));
                    
                                                            if ($cl->hasPermission('approve_post')) {
                                                                $display = true;
                                                            }
                                                        }
                                                    }
                    
                                                    $tmpData['id'] = $post_data['post'];
                                                    if ($display) {
                                                        $tmpData['post'] = array(
                                                            'title' => $post_data['title'],
                                                            'content' => $post_data['content'],
                                                            'tags' => explode(',', $post_data['tags']),
                                                            'metadata' => array(
                                                                'comments' => $post_data['comments'],
                                                                'likes' => $post_data['likes']
                                                            )
                                                        );
                                                        $tmpData['date'] = $post_data['modified'];
                                                        $tmpData['owner'] = array(
                                                            'id' => $post_data['owner'],
                                                            'name' => $postConnection->getProfile($post_data['owner']),
                                                            'group' => $postConnection->getGroup($post_data['owner'])
                                                        );
                                                    }

                                                    $status = [];
                                                    $visibility = [];

                                                    $code = $tmpData['status']['code'];
                                                    $status['code'] = $code;
                                                    $issuer = $tmpData['status']['issuer'];
                                                    if ($issuer != null) {
                                                        $status['admin'] = $issuer;
                                                        $status['name'] = $postConnection->getProfile($issuer);
                                                    }

                                                    $visibility['code'] = $code;
                                                    if ($code == 2 || $code == 1) {
                                                        $issuer = $tmpData['status']['visibility'];
                                                        if ($issuer != null) {
                                                            $visibility['issuer'] = $issuer;
                                                            $visibility['name'] = $postConnection->getProfile($issuer);
                                                        }
                                                    }
                    
                                                    $tmpData['status'] = $status;
                                                    $tmpData['visibility'] = $visibility;

                                                    $response[$post_id] = $tmpData;
                                                }
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "The post {$id} does not exists and any user named like that made a post";
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "Post must be provided";
                                    }
                                    break;
                                case 'remove':
                                    $id = strip_tags($data['post']);
                                    if ($id != null) {
                                        $postConnection = new PostData();
                                        $tmpData = $postConnection->getPost($id);

                                        if (sizeof($data) >= 9) {
                                            $owner = $data['owner'];
                                            $currentId = -1;

                                            if (ClientData::isAuthenticated()) {
                                                $cl = unserialize(Utils::get('client'));

                                                $currentId = $cl->getIdentifier();

                                                if (($owner == $currentId && $cl->hasPermission('remove_post')) || $cl->hasPermission('remove_other_post')) {
                                                    $result = $postConnection->setPostStatus($id, $currentId, Post::post_removed());

                                                    $response['success'] = $result == 0;
                                                    $response['message'] = ($result == 0 ? "Post {$id} has been removed" : Post::parse($result));
                                                } else {
                                                    $response['success'] = false;
                                                    if ($owner == $currentId) {
                                                        $response['message'] = "Something went wrong";
                                                    } else {
                                                        $response['message'] = "Insufficient privileges";
                                                    }
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "You must be authenticated to remove a post";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "A post with id {$id} does not exists";
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "Post id must be specified";
                                    }
                                    break;
                                case 'visibility':
                                    $id = strip_tags($data['post']);
                                    if ($id != null) {
                                        $postConnection = new PostData();
                                        $tmpData = $postConnection->getPost($id);

                                        if (sizeof($tmpData) >= 9) {
                                            $owner = $tmpData['owner'];
                                            $currentId = -1;

                                            if (ClientData::isAuthenticated()) {
                                                $cl = unserialize(Utils::get('client'));

                                                $currentId = $cl->getIdentifier();

                                                if ($owner == $currentId || $cl->hasPermission('disable_post')) {
                                                    if ($tmpData['status']['code'] == Post::post_active() || ($tmpData['status']['code'] == Post::post_private() && $tmpData['status']['visibility'] == $currentId)) {
                                                        $isPublic = $tmpData['status']['code'] == Post::post_active();
                                                        if ($isPublic) {
                                                            $result = $postConnection->setPostStatus($id, $currentId, Post::post_private());
                                                        } else {
                                                            $result = $postConnection->setPostStatus($id, $currentId, Post::post_active());
                                                        }

                                                        $response['success'] = $result == 0;
                                                        $response['message'] = ($result == 0 ? "Post {$id} is now " . ($isPublic ? "private" : "public") : Post::parse($result));
                                                    } else {
                                                        if ($tmpData['status']['code'] == Post::post_private()) {
                                                            $response['success'] = false;
                                                            $response['message'] = "Can not change visilibity of a post changed by an administrator!";
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "Post must be public or private in order to swtich between post visibility";
                                                        }
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    if ($owner == $currentId) {
                                                        $response['message'] = "Something went wrong";
                                                    } else {
                                                        $response['message'] = "Insufficient privileges";
                                                    }
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "You must be authenticated to edit a post visiblitiy";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "A post with id {$id} does not exists";
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "Post id must be specified";
                                    }
                                    break;
                                default:
                                    $response['success'] = false;
                                    $response['message'] = "Unknown post action: {$action}";
                                    break;
                            }
                        } else {
                            $response['success'] = false;
                            $response['message'] = "Post action must be provided";
                        }
                        break;
                    case "account":
                        $action = $data['action'];

                        if ($action != null) {
                            switch (strtolower($action)) {
                                case 'description':
                                    $content = $data['content'];

                                    if ($content != null) {
                                        if (ClientData::isAuthenticated()) {
                                            $cl = unserialize(Utils::get('client'));

                                            $description = strip_tags(preg_replace('/^[^a-zA-Z0-9_.,@ñáéíóúäëïöüàèìòù]$/', '', str_split($content, 255))[0]);
                                            $connection = new ClientData();

                                            $connection->updateDescription($cl->getIdentifier(), $description);

                                            $response['success'] = true;
                                            $response['message'] = $description;
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "You must be authenticated to modify your description";
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "Account description must be provided";
                                    }
                                    break;
                                case 'setting':
                                    $content = $data['content'];
                                    $value = $data['value'];
                                    if ($content != null && $value != null) {
                                        if (ClientData::isAuthenticated()) {
                                            $cl = unserialize(Utils::get('client'));
                                            $connection = new ClientData();

                                            $settings = json_decode($connection->loadProfileData($cl->getIdentifier())['setting'], true);

                                            $store = false;
                                            switch (strtolower($content)) {
                                                case 'visibility':
                                                    switch (strtolower($value)) {
                                                        case 'public':
                                                        case 'private':
                                                        case 'friends':
                                                            $settings['visibility'] = strtolower($value);
                                                            $store = true;
                                                            break;
                                                        default:
                                                            $response['success'] = false;
                                                            $response['message'] = "Invalid setting key: {$content} = {$value}. public/private/friends expected";
                                                            break;
                                                    }
                                                    break;
                                                case 'broadcast_status':
                                                    switch (strtolower($value)) {
                                                        case 'false':
                                                            $settings['broadcast_status'] = false;
                                                            $store = true;
                                                            break;
                                                        case 'true':
                                                            $settings['broadcast_status'] = true;
                                                            $store = true;
                                                            break;
                                                        default:
                                                            $response['success'] = false;
                                                            $response['message'] = "Invalid setting key: {$content} = {$value}. true/false expected";
                                                            break;
                                                    }
                                                    break;
                                                case 'broadcast_registration':
                                                    switch (strtolower($value)) {
                                                        case 'false':
                                                            $settings['broadcast_registration'] = false;
                                                            $store = true;
                                                            break;
                                                        case 'true':
                                                            $settings['broadcast_registration'] = true;
                                                            $store = true;
                                                            break;
                                                        default:
                                                            $response['success'] = false;
                                                            $response['message'] = "Invalid setting key: {$content} = {$value}. true/false expected";
                                                            break;
                                                    }
                                                    break;
                                                case 'email_notifications':
                                                    switch (strtolower($value)) {
                                                        case 'false':
                                                            $settings['email_notifications'] = false;
                                                            $store = true;
                                                            break;
                                                        case 'true':
                                                            $settings['email_notifications'] = true;
                                                            $store = true;
                                                            break;
                                                        default:
                                                            $response['success'] = false;
                                                            $response['message'] = "Invalid setting key: {$content} = {$value}. true/false expected";
                                                            break;
                                                    }
                                                    break;
                                                default:
                                                    $response['success'] = false;
                                                    $response['message'] = "Unknown setting key: {$content}";
                                                    break;
                                            }

                                            if ($store) {
                                                $connection->saveSettings($cl->getIdentifier(), $settings);
                                                
                                                $response['success'] = true;
                                                $response['message'] = "Successfully updated client settings";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "User must be logged in in order to modify settings";
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "Setting key and value must be provided";
                                    }
                                    break;
                                case "fetch":
                                    $query = $data['query'];

                                    if (ClientData::isAuthenticated()) {
                                        $response['success'] = true;
                                        $response['message'] = "Profile information fetched";
                                        $connection = new ClientData();
                                        $postConnection = new PostData();

                                        $cl = unserialize(Utils::get('client'));
                                        $profile = $connection->loadProfileData($cl->getIdentifier());

                                        $description = $profile['description'];
                                        $description = explode("\r\n", $description);

                                        if ($query) {
                                            if (str_contains($query, ',')) {
                                                $query = explode(',', $query);
                                            } else {
                                                $query = array(
                                                    $query
                                                );
                                            }
                                        } else {
                                            $query = array('*');
                                        }

                                        if (in_array('created_posts', $query) || in_array('*', $query)) {
                                            $posts = $postConnection->getUserPosts($cl->getIdentifier());
                                        }
                                        if (in_array('commented_posts', $query) || in_array('liked_posts', $query) || in_array('*', $query)) {
                                            $commentAndLikes = $postConnection->getCommentAndLikes($cl->getIdentifier());
                                        }

                                        if (isset($posts)) {
                                            $postData = array();

                                            foreach ($posts as $postId => $data) {
                                                array_push($postData, array(
                                                    'post' => $data['post'],
                                                    'title' => str_replace("\r\n", "", $data['title'])
                                                ));
                                            }
                                        }

                                        if (isset($commentAndLikes) && (in_array('commented_posts', $query) || in_array('*', $query))) {
                                            $commData = array();

                                            foreach ($commentAndLikes['comments'] as $commentId => $data) {
                                                if (!isset($commData[$data['id']])) {
                                                    $commData[$data['id']] = array(
                                                        'title' => $data['title'],
                                                        'comments' => array()
                                                    );
                                                }
                                
                                                $postCommData = $commData[$data['id']];

                                                $postComments = $postCommData['comments'];
                                                array_push($postComments, array(
                                                    'content' => $data['comment'],
                                                    'published' => $data['created']
                                                ));

                                                $postCommData['comments'] = $postComments;
                                                $commData[$data['id']] = $postCommData;
                                            }
                                        }

                                        if (isset($commentAndLikes) && (in_array('liked_posts', $query) || in_array('*', $query))) {
                                            $likeData = array();

                                            foreach ($commentAndLikes['likes'] as $likeId => $data) {
                                                if (!isset($likeData[$data['id']])) {
                                                    $likeData[$data['id']] = array(
                                                        'title' => $data['title'],
                                                        'likes' => $data['likes']
                                                    );
                                                }
                                            }
                                        }

                                        if (in_array('user_id', $query) || in_array('*', $query)) {
                                            $response['user_id'] = $cl->getIdentifier();
                                        }
                                        if (in_array('user_uuid', $query) || in_array('*', $query)) {
                                            $response['uuid'] = $cl->getUniqueIdentifier();
                                        }
                                        if (in_array('name', $query) || in_array('*', $query)) {
                                            $response['name'] = $profile['name'];
                                        }
                                        if (in_array('email', $query) || in_array('*', $query)) {
                                            $response['email'] = $profile['email'];
                                        }

                                        if (in_array('settings', $query) || in_array('*', $query)) {
                                            $response['settings'] = array(
                                                'visibility' => $cl->getSetting('visibility'),
                                                'broadcast_status' => $cl->getSetting('broadcast_status'),
                                                'broadcast_registration' => $cl->getSetting('broadcast_registration'),
                                                'email_notifications' => $cl->getSetting('email_notifications')
                                            );
                                        }

                                        if (in_array('image', $query) || in_array('description', $query) || in_array('*', $query) || isset($postData) || isset($likeData) || isset($commData)) {
                                            $response['profile'] = array();

                                            if (in_array('image', $query) || in_array('*', $query)) {
                                                $response['profile']['image'] = str_replace("data:image/png;base64,", "", $profile['photo']);
                                            }
                                            if (in_array('description', $query) || in_array('*', $query)) {
                                                $response['profile']['description'] = $description;
                                            }
                                            if (isset($postData)) {
                                                $response['profile']['created_posts'] = $postData;
                                            }
                                            if (isset($likeData)) {
                                                $response['profile']['liked_posts'] = $likeData;
                                            }
                                            if (isset($commData)) {
                                                $response['profile']['commented_posts'] = $postData;
                                            }
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "You must be authenticated to fetch your profile data";
                                    }
                                    break;
                                case "requests":
                                    if (ClientData::isAuthenticated()) {
                                        $connection = new ClientData();
                                        $cl = unserialize(Utils::get('client'));

                                        $requests = $connection->fetchRequests($cl->getIdentifier());

                                        $response['success'] = true;
                                        $response['message'] = "Successfully fetched API requests made from this account";

                                        $reqData = [];
                                        $reqData['amount'] = sizeof($requests);
                                        foreach ($requests as $requestId => $requestData) {
                                            $tmpData = [];

                                            if (isset($requestData['method'])) {
                                                $tmpData['method'] = $requestData['method'];
                                            }
                                            if (isset($requestData['action'])) {
                                                $tmpData['action'] = $requestData['action'];
                                            }
                                            if (isset($requestData['data'])) {
                                                $tmpData['data'] = $requestData['data'];
                                            }
                                            $tmpData['date'] = $requestData['modification'];

                                            $reqData[$requestId] = $tmpData;
                                        }

                                        $response['requests'] = $reqData;
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "You must be authenticated to fetch your profile API requests";
                                    }
                                    break;
                                case "update":
                                    $content = $data['content'];

                                    if ($content != null) {
                                        if (ClientData::isAuthenticated()) {
                                            //For multiple editing...
                                            $content = json_decode($content, true);

                                            $visibility = strtolower($content['settings']['visibility']);
                                            $broadcast_status = $content['settings']['broadcast_status'];
                                            $broadcast_register = $content['settings']['broadcast_registration'];
                                            $send_emails = $content['settings']['email_notifications'];

                                            if (isset($content['profile']['image'])) {
                                                $profile_image = "data:image/png;base64," . $content['profile']['image'];
                                            }

                                            $profile_image = Utils::resizeImage($profile_image, 120, 120, true);

                                            $profile_description = $content['profile']['description'];
                                            
                                            $string_builder = "";
                                            foreach ($profile_description as $key) {
                                                $string_builder = $string_builder . $key . "\n";
                                            }

                                            $profile_description = preg_replace('/^[^a-zA-Z0-9_.,@ñáéíóúäëïöüàèìòù]$/', '', str_split(rtrim($string_builder, "\n"), 255))[0];

                                            $cl = unserialize(Utils::get('client'));
                                            $connection = new ClientData();

                                            $settings = json_decode($connection->loadProfileData($cl->getIdentifier())['setting'], true);

                                            $store = false;
                                            switch ($visibility) {
                                                case "public":
                                                case "private":
                                                case "friends":
                                                    $settings['visibility'] = $visibility;
                                                    $store = true;
                                                    break;
                                            }

                                            $settings['broadcast_status'] = !strcasecmp($broadcast_status, "true");
                                            $settings['broadcast_registration'] = !strcasecmp($broadcast_register, "true");
                                            $settings['email_notifications'] = !strcasecmp($send_emails, "true");

                                            if ($store) {
                                                $connection->saveSettings($cl->getIdentifier(), $settings);
                                            }

                                            $connection->updateDescription($cl->getIdentifier(), $profile_description);
                                            if (isset($profile_image)) {
                                                $connection->updateProfilePhoto($cl->getIdentifier(), $profile_image);
                                            }

                                            $response['success'] = true;
                                            $response['message'] = "Successfully updated client settings";
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "You must be authenticated to update your profile data";
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = "New settings must be provided";
                                    }
                                    break;
                                case "status":
                                    $content = $data['content'];
                                    $value = $data['value'];

                                    if ($content != null) {
                                        if ($value != null && is_numeric($value)) {
                                            $new_friend_status = null;
                                            $new_follow_status = null;

                                            switch (strtolower($content)) {
                                                case 'friend':
                                                    $new_friend_status = true;
                                                    break;
                                                case 'unfriend':
                                                    $new_friend_status = false;
                                                    break;
                                                case 'follow':
                                                    $new_follow_status = true;
                                                    break;
                                                case 'unfollow':
                                                    $new_follow_status = false;
                                                    break;
                                                default:
                                                    $response['success'] = false;
                                                    $response['message'] = 'Unknown friend status: ' . $content;
                                                    break;
                                            }

                                            if (ClientData::isAuthenticated()) {
                                                $connection = new ClientData();

                                                $connection->updateFriendStatus(intval($value), $new_follow_status, $new_friend_status);

                                                $response['success'] = true;
                                                $response['message'] = 'Successfully updated friend status';
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = 'You must be authenticated in order to manage your friends/follows';
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = 'You must provide a valid user ID to follow/friend with!';
                                        }
                                    } else {
                                        $response['success'] = false;
                                        $response['message'] = 'You must provide a friend status!';
                                    }
                                    break;
                                default:
                                    $response['success'] = false;
                                    $response['message'] = "Unknown account action: {$action}";
                                    break;
                            }
                        } else {
                            $response['success'] = false;
                            $response['message'] = "Account action must be provided";
                        }
                        break;
                    case "notification":
                        $action = $data['action'];

                        if ($action != null) {
                            if (ClientData::isAuthenticated()) {
                                $cl = unserialize(Utils::get('client'));

                                switch (strtolower($action)) {
                                    case 'read':
                                        $nId = $data['notification'];

                                        if ($nId != null) {
                                            $cl->read(intval($nId));

                                            $response['success'] = true;
                                            $response['message'] = "Successfully marked notification {$nId} as read";
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Notification id must be provided";
                                        }
                                        break;
                                    case 'unread':
                                        $nId = $data['notification'];

                                        if ($nId != null) {
                                            $cl->unread(intval($nId));

                                            $response['success'] = true;
                                            $response['message'] = "Successfully unmarked notification {$nId} as read";
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Notification id must be provided";
                                        }
                                        break;
                                    case 'delete':
                                        $nId = $data['notification'];

                                        if ($nId != null) {
                                            $cl->remove(intval($nId));

                                            $response['success'] = true;
                                            $response['message'] = "Successfully removed notification {$nId}";
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Notification id must be provided";
                                        }
                                        break;
                                    case 'fetch':
                                        $nType = strtolower($data['notification']);
                                        if ($nType != 'unread' && $nType != 'read' && $nType != 'all') {
                                            $nType = 'unread';
                                        }

                                        $notifications = null;
                                        switch ($nType) {
                                            case 'unread':
                                                $notifications = $cl->getUnread();
                                                break;
                                            case 'read':
                                                $notifications = $cl->getRead();
                                                break;
                                            case 'all':
                                            default:
                                                $notifications = $cl->getAll();
                                                break;
                                        }

                                        $response['success'] = true;
                                        $response['message'] = 'Fetched ' . $nType . ' notifications successfully';
                                        $response['notifications'] = $notifications;

                                        break;
                                    default:
                                        $response['success'] = false;
                                        $response['message'] = "Unknown notification action: {$action}";
                                        break;
                                }
                            } else {
                                $response['success'] = false;
                                $response['message'] = "You must be authenticated to manage notifications";
                            }
                        } else {
                            $response['success'] = false;
                            $response['message'] = "Notification action must be provided";
                        }
                        break;
                    case 'locklogin':
                        $action = $data['action'];

                        if ($action != null) {
                            $server_hash = $data['server'];
                            $lockLogin = new LockLogin();

                            if ($server_hash != null) {
                                switch (strtolower($action)) {
                                    case 'append_hook':
                                        $server = $lockLogin->getServer($server_hash);

                                        if ($server != null) {
                                            if ($lockLogin->canHook($server_hash)) {
                                                if ($lockLogin->enableHook($server_hash)) {
                                                    $response['success'] = true;
                                                    $response['message'] = "Successfully hooked into server";
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "Server is available but couldn't hook into it";
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Server already in use";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Unknown server";
                                        }
                                        break;
                                    case 'remove_hook':
                                        $server = $lockLogin->getServer($server_hash);

                                        if ($server != null) {
                                            if ($lockLogin->disableHook($server_hash)) {
                                                $response['success'] = true;
                                                $response['message'] = "Server unhooked successfully";
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Unauthorized";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Unknown server";
                                        }
                                        break;
                                    case "fetch_commands":
                                        $response['success'] = true;
                                        $response['message'] = "[name:KarmaDev,id:abc,password:test]";
                                        $response['command'] = "create_account";
                                        break;
                                    /*case 'create_profile':
                                        No longer exists, is better to have this in the fetch_profile method, so fetching also creates

                                        $username = $data['name'];
                                        $password = $data['content'];

                                        if ($username != null && $password != null) {
                                            if (UUIDValidator::isValid($username)) {
                                                $mc = new Minecraft();

                                                $mcData = $mc->fetch($username);
                                                foreach ($mcData as $name => $userData) {
                                                    $username = $name;
                                                }
                                            }
                                            
                                            $connection = new ClientData();
                                            $ac_code = $connection->authenticate($username, $password, false);

                                            $profData = $connection->loadProfileData($username);
                                            
                                            if ($ac_code == Code::success()) {
                                                if ($profData != null) {
                                                    if (Utils::generateLockLoginProfile($profData['id'])) {
                                                        $response['success'] = true;
                                                        $response['message'] = "The profile has been created as user id " . $profData['id'];
                                                    } else {
                                                        $lockloginProfile = Utils::fetchLockLoginProfile($profData['id']);

                                                        if ($lockloginProfile != null) {
                                                            $response['success'] = true;
                                                            $response['message'] = "The user already had a profile created";
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "An error occurred while generating your profile";
                                                        }
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "Failed to generate profile";
                                                }
                                            } else {
                                                if ($ac_code == Code::err_login_exists()) {
                                                    $email = 'tmp' . Utils::generate(6) . '@karmadev.es';

                                                    $ac_code = $connection->register($email, $username, $password, false);
                                                    if ($ac_code == 0) {
                                                        $profData = $connection->loadProfileData($email);
                                                        
                                                        $response['success'] = true;
                                                        $response['message'] = 'Successfully created LockLogin profile as user id ' . $profData['id'];
                                                    } else {
                                                        $response['success'] = false;
                                                        $response['message'] = "Failed to create profile; Error code: {$ac_code}";
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "Failed to create profile; Error code: {$ac_code}";
                                                }
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "User name and password must be provided in order to create a profile";
                                        }
                                        break;*/
                                    case 'fetch_profile':
                                        $server = $lockLogin->getServer($server_hash);

                                        if ($server != null) {
                                            if ($lockLogin->isHookOwner($server_hash)) {
                                                $username = $data['name'];
                                                $password = $data['content'];

                                                if ($username != null && $password != null) {
                                                    if (UUIDValidator::isValid($username)) {
                                                        $mc = new Minecraft();

                                                        $mcData = $mc->fetch($username);
                                                        foreach ($mcData as $name => $userData) {
                                                            $username = $name;
                                                        }
                                                    }

                                                    $connection = new ClientData();
                                                    $ac_code = $connection->authenticate($username, $password, false);

                                                    $profData = $connection->loadProfileData($username);
                                                    
                                                    if ($ac_code == Code::success()) {
                                                        if ($profData != null) {
                                                            $lockloginProfile = $lockLogin->findProfile($profData['id']);

                                                            if ($lockloginProfile != null) {
                                                                $response['success'] = true;
                                                                $response['message'] = "Profile info successfully fetched";

                                                                $lockloginProfile['pin'] = Utils::generate(32);
                                                                $lockloginProfile['token'] = Utils::generate(128);
                                                                $lockloginProfile['panic'] = Utils::generate(64);

                                                                $response[$username] = $lockloginProfile;

                                                                $uAccessKey = Utils::generate();
                                                                Utils::setGlobal($username . '_access', Utils::encode($uAccessKey));

                                                                $response['access_key'] = $uAccessKey;
                                                            } else {
                                                                $response['success'] = false;
                                                                $response['message'] = "An error occurred while fetching your profile";
                                                                $response['code'] = 1;
                                                            }
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "Failed to fetch profile";
                                                            $response['code'] = 2;
                                                        }
                                                    } else {
                                                        if ($ac_code == Code::err_login_exists()) {
                                                            $email = 'tmp' . Utils::generate(6) . '@karmadev.es';

                                                            $ac_code = $connection->register($email, $username, $password, false);
                                                            if ($ac_code == 0) {
                                                                $profData = $connection->loadProfileData($email);
                                                                
                                                                if ($profData != null) {
                                                                    if ($lockLogin->createProfile($profData['id'])) {
                                                                        $lockloginProfile = $lockLogin->findProfile($profData['id']);

                                                                        $response['success'] = true;
                                                                        $response['message'] = 'Successfully created LockLogin profile as user id ' . $profData['id'];

                                                                        $lockloginProfile['pin'] = Utils::generate(32);
                                                                        $lockloginProfile['token'] = Utils::generate(128);
                                                                        $lockloginProfile['panic'] = Utils::generate(64);

                                                                        $response[$username] = $lockloginProfile;

                                                                        $uAccessKey = Utils::generate();
                                                                        Utils::setGlobal($username . '_access', Utils::encode($uAccessKey));

                                                                        $response['access_key'] = $uAccessKey;
                                                                    } else {
                                                                        $response['success'] = false;
                                                                        $response['message'] = "Failed to create LockLogin profile";
                                                                        $response['code'] = 3;
                                                                    }
                                                                } else {
                                                                    $response['success'] = false;
                                                                    $response['message'] = "Failed to create profile; Profile data is null";
                                                                    $response['code'] = 4;
                                                                }
                                                            } else {
                                                                $response['success'] = false;
                                                                $response['message'] = "Failed to create profile; Error code: {$ac_code}";
                                                                $response['code'] = 5;
                                                            }
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "Failed to create profile; Error code: {$ac_code}";
                                                            $response['code'] = 6;
                                                        }
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "User name and password must be provided in order to fetch a profile";
                                                    $response['code'] = 7;
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Invalid source";
                                                $response['code'] = 8;
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Unknown server";
                                            $response['code'] = 9;
                                        }
                                        break;
                                    case 'set_option':
                                        $server = $lockLogin->getServer($server_hash);

                                        if ($server != null) {
                                            if ($lockLogin->isHookOwner($server_hash)) {
                                                $username = $data['name'];
                                                $password = $data['content'];
                                                $key = $data['parent'];
                                                $value = $data['query'];

                                                if ($username != null && $password != null && $key != null && $value != null) {
                                                    if (UUIDValidator::isValid($username)) {
                                                        $mc = new Minecraft();

                                                        $mcData = $mc->fetch($username);
                                                        foreach ($mcData as $name => $userData) {
                                                            $username = $name;
                                                        }
                                                    }

                                                    $connection = new ClientData();
                                                    $profData = $connection->loadProfileData($username);
                                                    
                                                    if ($ac_code == Code::success()) {
                                                        if ($profData != null) {
                                                            $lockloginProfile = $lockLogin->findProfile($profData['id']);

                                                            if ($lockloginProfile != null) {
                                                                switch (strtolower($key)) {
                                                                    case '2fa':
                                                                        $value = strval($value);
                                                                        switch (strtolower($value)) {
                                                                            case 'true':
                                                                            case '1':
                                                                                if ($lockloginProfile['2fa'] == null) {
                                                                                    $google2fa = new Google2FA();
                                                                                    $secretKey = $google2fa->generateSecretKey();

                                                                                    $qr = $google2fa->getQRCodeUrl(
                                                                                        'LockLogin',
                                                                                        $username,
                                                                                        $secretKey
                                                                                    );

                                                                                    $host = 'https://' . $_SERVER['SERVER_NAME'] . $config->getHomePath() . 'locklogin/?qr=' . $qr;

                                                                                    $response['success'] = true;
                                                                                    $response['message'] = 'Google 2FA generated successfully';
                                                                                    $response['scan'] = $host;

                                                                                    $gCode = openssl_encrypt($secretKey, 'AES-128-CTR', $config->getSecretKey(), 0, $config->getSecretIV());

                                                                                    $lockloginProfile['2fa'] = base64_encode($gCode);
                                                                                    $lockLogin->saveProfile($profData['id'], '2fa', true);
                                                                                    $lockLogin->saveProfile($profData['id'], 'token', base64_encode($gCode));
                                                                                } else {
                                                                                    $google2fa = new Google2FA();

                                                                                    $gCode = base64_decode($lockloginProfile['token']);
                                                                                    $gCode = openssl_decrypt($gCode, 'AES-128-CTR', $config->getSecretKey(), 0, $config->getSecretIV());

                                                                                    $qr = $google2fa->getQRCodeUrl(
                                                                                        'LockLogin',
                                                                                        $username,
                                                                                        $gCode
                                                                                    );

                                                                                    $host = 'https://' . $_SERVER['SERVER_NAME'] . $config->getHomePath() . 'locklogin/?qr=' . $qr;

                                                                                    $response['success'] = true;
                                                                                    $response['message'] = 'Google 2FA generated successfully';
                                                                                    $response['scan'] = $host;
                                                                                }
                                                                                break;
                                                                            case 'false':
                                                                            case '0':
                                                                                if ($lockloginProfile['2fa']) {
                                                                                    $response['success'] = true;
                                                                                    $response['message'] = '2FA token has been removed from this account';

                                                                                    $lockLogin->saveProfile($profData['id'], '2fa', 0);
                                                                                    $lockLogin->saveProfile($profData['id'], 'token', null);
                                                                                } else {
                                                                                    $response['success'] = false;
                                                                                    $response['message'] = 'User is not using 2fa';
                                                                                }
                                                                                break;
                                                                            default:
                                                                                $response['success'] = false;
                                                                                $response['message'] = 'Unexpected 2FA value. Expecting true/false';
                                                                                break;
                                                                        }
                                                                        break;
                                                                    case 'pin':
                                                                        $value = strval($value);
                                                                        if ($value == '----') {
                                                                            if ($lockloginProfile['pin'] != null) {
                                                                                $lockLogin->saveProfile($profData['id'], 'pin', null);

                                                                                $response['success'] = true;
                                                                                $response['success'] = 'User pin has been removed from this account';
                                                                            } else {
                                                                                $response['success'] = false;
                                                                                $response['message'] = 'User is not using pin';
                                                                            }
                                                                        } else {
                                                                            if ($lockloginProfile['pin'] == null) {
                                                                                $pin = Utils::encode($value);

                                                                                $lockLogin->saveProfile($profData['id'], 'pin', $pin);

                                                                                $response['success'] = true;
                                                                                $response['message'] = 'User pin has been set';
                                                                            } else {
                                                                                $response['success'] = false;
                                                                                $response['message'] = 'User pin has been already set in this account';
                                                                            }
                                                                        }
                                                                        break;
                                                                    case 'panic':
                                                                        //Not implemented
                                                                        $response['success'] = false;
                                                                        $response['message'] = 'Not implemented yet!';
                                                                        break;
                                                                    default:
                                                                        $response['success'] = false;
                                                                        $response['message'] = 'Unknown profile key: ' . $key;
                                                                        break;
                                                                }
                                                            } else {
                                                                $response['success'] = false;
                                                                $response['message'] = "An error occurred while fetching your profile";
                                                            }
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "Failed to fetch profile";
                                                        }
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "User name, password, profile option key and profile option value must be provided in order to update a profile";
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Invalid source";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Unknown server";
                                        }
                                        break;
                                    case 'auth_password':
                                        $server = $lockLogin->getServer($server_hash);

                                        if ($server != null) {
                                            if ($lockLogin->isHookOwner($server_hash)) {
                                                $username = $data['name'];
                                                $password = $data['content'];

                                                if ($username != null && $password != null) {
                                                    if (UUIDValidator::isValid($username)) {
                                                        $mc = new Minecraft();

                                                        $mcData = $mc->fetch($username);
                                                        foreach ($mcData as $name => $userData) {
                                                            $username = $name;
                                                        }
                                                    }

                                                    $connection = new ClientData();
                                                    $ac_code = $connection->authenticate($username, $password, false);

                                                    $profData = $connection->loadProfileData($username);
                                                    
                                                    if ($ac_code == Code::success()) {
                                                        if ($profData != null) {
                                                            $lockloginProfile = $lockLogin->findProfile($profData['id']);

                                                            if ($lockloginProfile != null) {
                                                                $response['success'] = true;
                                                                $response['message'] = "Successfully authenticated user and password";
                                                            } else {
                                                                $response['success'] = false;
                                                                $response['message'] = "An error occurred while fetching your profile";
                                                            }
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "Failed to fetch profile";
                                                        }
                                                    } else {
                                                        $response['success'] = false;
                                                        $response['message'] = "Invalid credentials";
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "User name and password must be provided in order to fetch a profile";
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Invalid source";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Unknown server";
                                        }
                                        break;
                                    case 'auth_pin':
                                        $server = $lockLogin->getServer($server_hash);

                                        if ($server != null) {
                                            if ($lockLogin->isHookOwner($server_hash)) {
                                                $username = $data['name'];
                                                $password = $data['content'];
                                                $pin = $data['query'];

                                                if ($username != null && $password != null) {
                                                    if (UUIDValidator::isValid($username)) {
                                                        $mc = new Minecraft();

                                                        $mcData = $mc->fetch($username);
                                                        foreach ($mcData as $name => $userData) {
                                                            $username = $name;
                                                        }
                                                    }

                                                    $connection = new ClientData();
                                                    $ac_code = $connection->authenticate($username, $password, false);

                                                    $profData = $connection->loadProfileData($username);
                                                    
                                                    if ($ac_code == Code::success()) {
                                                        if ($profData != null) {
                                                            $lockloginProfile = $lockLogin->findProfile($profData['id']);

                                                            if ($lockloginProfile != null) {
                                                                if ($lockloginProfile['pin'] != null) {
                                                                    if (Utils::auth($pin, base64_decode($lockloginProfile['pin']))) {
                                                                        $response['success'] = true;
                                                                        $response['message'] = "Pin successfully validated";
                                                                    } else {
                                                                        $response['success'] = false;
                                                                        $response['message'] = "Failed to validate pin";
                                                                    }
                                                                } else {
                                                                    $response['success'] = false;
                                                                    $response['message'] = "Failed to validate pin";
                                                                }
                                                            } else {
                                                                $response['success'] = false;
                                                                $response['message'] = "An error occurred while fetching your profile";
                                                            }
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "Failed to fetch profile";
                                                        }
                                                    } else {
                                                        $response['success'] = false;
                                                        $response['message'] = "Invalid credentials";
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "User name and password must be provided in order to fetch a profile";
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Invalid source";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Unknown server";
                                        }
                                        break;
                                    case 'auth_2fa':
                                        $server = $lockLogin->getServer($server_hash);

                                        if ($server != null) {
                                            if ($lockLogin->isHookOwner($server_hash)) {
                                                $username = $data['name'];
                                                $password = $data['content'];
                                                $code = $data['query'];

                                                if ($username != null && $password != null) {
                                                    if (UUIDValidator::isValid($username)) {
                                                        $mc = new Minecraft();

                                                        $mcData = $mc->fetch($username);
                                                        foreach ($mcData as $name => $userData) {
                                                            $username = $name;
                                                        }
                                                    }

                                                    $connection = new ClientData();
                                                    $ac_code = $connection->authenticate($username, $password, false);

                                                    $profData = $connection->loadProfileData($username);
                                                    
                                                    if ($ac_code == Code::success()) {
                                                        if ($profData != null) {
                                                            $lockloginProfile = $lockLogin->findProfile($profData['id']);

                                                            if ($lockloginProfile != null) {
                                                                if ($lockloginProfile['2fa']) {
                                                                    $google2fa = new Google2FA();

                                                                    $gCode = base64_decode($lockloginProfile['token']);
                                                                    $gCode = openssl_decrypt($gCode, 'AES-128-CTR', $config->getSecretKey(), 0, $config->getSecretIV());

                                                                    if ($google2fa->verifyKey($gCode, $code)) {
                                                                        $response['success'] = true;
                                                                        $response['message'] = '2FA code successfully validated';
                                                                    } else {
                                                                        $response['success'] = false;
                                                                        $response['message'] = 'Incorrect 2fa code';
                                                                    }
                                                                } else {
                                                                    $response['success'] = false;
                                                                    $response['message'] = "User is not using 2fa";
                                                                }
                                                            } else {
                                                                $response['success'] = false;
                                                                $response['message'] = "An error occurred while fetching your profile";
                                                            }
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "Failed to fetch profile";
                                                        }
                                                    } else {
                                                        $response['success'] = false;
                                                        $response['message'] = "Invalid credentials";
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "User name and password must be provided in order to fetch a profile";
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Invalid source";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Unknown server";
                                        }
                                        break;
                                    case 'module':
                                        $server = $lockLogin->getServer($server_hash);

                                        if ($server != null) {
                                            if ($lockLogin->isHookOwner($server_hash)) {
                                                $moduleName = $data['name'];
                                                $pluginId = $data['plugin_version'];

                                                if ($moduleName != null && $pluginId != null) {
                                                    if (strpos($pluginId, '-') !== false) {
                                                        $vData = explode('-', $pluginId)[1];
                                                        if (strpos($vData, '.') !== false) {
                                                            $vData = explode('.', $vData);
                                                            $vId = $vData[0];
                                                            $rest = intval($vData[1]);

                                                            $idData = explode('/', $vId);

                                                            $pluginVersion = "";
                                                            foreach ($idData as $component) {
                                                                $num = intval($component);
                                                                $result = abs($num - $rest);

                                                                $pluginVersion = $pluginVersion . $result . '.';
                                                            }

                                                            $pluginVersion = substr($pluginVersion, 0, strlen($pluginVersion) - 1);
                                                            $moduleData = $lockLogin->getModule($moduleName);

                                                            if ($moduleData != null) {
                                                                $moduleVersions = $moduleData['versions'];

                                                                $validVersions = [];
                                                                foreach ($moduleVersions as $version => $versionInfo) {
                                                                    $min_version = $versionInfo['min_version'];
                                                                    $max_version = $versionInfo['max_version'];

                                                                    $min_check = Utils::checkVersions($pluginVersion, $min_version);
                                                                    if ($min_check == 0 || $min_check == 1) {
                                                                        if ($max_version != 'latest') {
                                                                            $max_check = Utils::checkVersions($pluginVersion, $max_version);

                                                                            if ($max_check == -1) {
                                                                                $host = 'https://' . $_SERVER['SERVER_NAME'] . $config->getHomePath() . 'locklogin/products/product.php?download=' . $versionInfo['version_id'] . Utils::generate(6);

                                                                                $validVersions[$version] = array(
                                                                                    'info' => str_replace("\r", '', str_replace("\n", '', $versionInfo['info'])),
                                                                                    'required_version' => $min_version,
                                                                                    'last_compatible' => $max_version,
                                                                                    'download_token' => $host
                                                                                );
                                                                            }
                                                                        } else {
                                                                            $host = 'https://' . $_SERVER['SERVER_NAME'] . $config->getHomePath() . 'locklogin/products/product.php?download=' . $versionInfo['version_id'] . Utils::generate(6);

                                                                            $validVersions[$version] = array(
                                                                                'info' => str_replace("\r", '', str_replace("\n", '', $versionInfo['info'])),
                                                                                'required_version' => $min_version,
                                                                                'download_token' => $host
                                                                            );
                                                                        }
                                                                    }
                                                                }

                                                                $response['success'] = true;
                                                                $response['message'] = "Found a valid module for your server!";

                                                                $response['versions'] = $validVersions;
                                                            } else {
                                                                $response['success'] = false;
                                                                $response['message'] = "Module does not exists!";
                                                            }
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "Invalid plugin version ID ( 2 )";
                                                        }
                                                    } else {
                                                        $response['success'] = false;
                                                        $response['message'] = "Invalid plugin version ID";
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "Plugin version ID and module name must be provided";
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Invalid source";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Unknown server";
                                        }
                                        break;
                                    case 'modules':
                                        $server = $lockLogin->getServer($server_hash);

                                        if ($server != null) {
                                            if ($lockLogin->isHookOwner($server_hash)) {
                                                $pluginId = $data['plugin_version'];
                                                if ($pluginId == null) {
                                                    //Find all modules, all versions
                                                    $allModules = $lockLogin->getModules();

                                                    $foundModules = [];
                                                    foreach ($allModules as $moduleName => $moduleId) {
                                                        $moduleData = $lockLogin->getModule($moduleId);

                                                        if ($moduleData != null) {
                                                            $moduleVersions = $moduleData['versions'];
                        
                                                            $validVersions = [];
                                                            foreach ($moduleVersions as $version => $versionInfo) {
                                                                $min_version = $versionInfo['min_version'];
                                                                $max_version = $versionInfo['max_version'];
                        
                                                                $host = 'https://' . $_SERVER['SERVER_NAME'] . $config->getHomePath() . 'locklogin/products/product.php?download=' . $versionInfo['version_id'] . Utils::generate(6);
                        
                                                                $validVersions[$version] = array(
                                                                    'version' => $version,
                                                                    'info' => str_replace("\r", '', str_replace("\n", '', $versionInfo['info'])),
                                                                    'required_version' => $min_version,
                                                                    'last_compatible' => $max_version,
                                                                    'download_token' => $host
                                                                );
                                                            }
                        
                                                            $foundModules[$moduleName] = $validVersions;
                                                        }
                                                    }

                                                    $response['success'] = true;
                                                    $response['message'] = "Fetched module store!";

                                                    $response['modules'] = $foundModules;
                                                } else {
                                                    //Find all modules compatibles with current version
                                                    if (strpos($pluginId, '-') !== false) {
                                                        $vData = explode('-', $pluginId)[1];
                                                        if (strpos($vData, '.') !== false) {
                                                            $vData = explode('.', $vData);
                                                            $vId = $vData[0];
                                                            $rest = intval($vData[1]);

                                                            $idData = explode('/', $vId);

                                                            $pluginVersion = "";
                                                            foreach ($idData as $component) {
                                                                $num = intval($component);
                                                                $result = abs($num - $rest);

                                                                $pluginVersion = $pluginVersion . $result . '.';
                                                            }

                                                            $pluginVersion = substr($pluginVersion, 0, strlen($pluginVersion) - 1);
                                                            $allModules = $lockLogin->getModules();

                                                            $foundModules = [];
                                                            foreach ($allModules as $moduleName => $moduleId) {
                                                                $moduleData = $lockLogin->getModule($moduleId);

                                                                if ($moduleData != null) {
                                                                    $moduleVersions = $moduleData['versions'];
                        
                                                                    $validVersions = [];
                                                                    foreach ($moduleVersions as $version => $versionInfo) {
                                                                        $min_version = $versionInfo['min_version'];
                                                                        $max_version = $versionInfo['max_version'];
                        
                                                                        $min_check = Utils::checkVersions($pluginVersion, $min_version);
                                                                        if ($min_check == 0 || $min_check == 1) {
                                                                            if ($max_version != 'latest') {
                                                                                $max_check = Utils::checkVersions($pluginVersion, $max_version);
                        
                                                                                if ($max_check == -1) {
                                                                                    $host = 'https://' . $_SERVER['SERVER_NAME'] . $config->getHomePath() . 'locklogin/products/product.php?download=' . $versionInfo['version_id'] . Utils::generate(6);

                                                                                    $validVersions[$version] = array(
                                                                                        'info' => str_replace("\r", '', str_replace("\n", '', $versionInfo['info'])),
                                                                                        'required_version' => $min_version,
                                                                                        'last_compatible' => $max_version,
                                                                                        'download_token' => $host
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                $host = 'https://' . $_SERVER['SERVER_NAME'] . $config->getHomePath() . 'locklogin/products/product.php?download=' . $versionInfo['version_id'] . Utils::generate(6);

                                                                                $validVersions[$version] = array(
                                                                                    'info' => str_replace("\r", '', str_replace("\n", '', $versionInfo['info'])),
                                                                                    'required_version' => $min_version,
                                                                                    'download_token' => $host
                                                                                );
                        
                                                                                break;
                                                                            }
                                                                        }
                                                                    }
                        
                                                                    $foundModules[$moduleName] = $validVersions;
                                                                }
                                                            }

                                                            $response['success'] = true;
                                                            $response['message'] = "Fetched module store!";

                                                            $response['modules'] = $foundModules;
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "Invalid plugin version ID ( 2 )";
                                                        }
                                                    } else {
                                                        $response['success'] = false;
                                                        $response['message'] = "Invalid plugin version ID";
                                                    }
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Invalid source";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Unknown server";
                                        }
                                        break;
                                    case 'version':
                                        $server = $lockLogin->getServer($server_hash);

                                        if ($server != null) {
                                            if ($lockLogin->isHookOwner($server_hash)) {
                                                $pluginId = $data['plugin_version'];
                                                $latest_version = $lockLogin->getUpdate(true, $channel);

                                                if ($latest_version != null) {
                                                    if ($pluginId == null) {
                                                        //Returns the latest version
                                                        $response['success'] = true;
                                                        $response['message'] = $latest_version['version'];
                                                    } else {
                                                        //Returns the latest version and tells if the current is out of date
                                                        
                                                        $latestId = $latest_version['version'];
                                                        if (strpos($latestId, '-') !== false) {
                                                            $vData = explode('-', $latestId)[1];
                                                            if (strpos($vData, '.') !== false) {
                                                                $vData = explode('.', $vData);
                                                                $vId = $vData[0];
                                                                $rest = intval($vData[1]);
                        
                                                                $idData = explode('/', $vId);
                        
                                                                $latestPluginVersion = "";
                                                                foreach ($idData as $component) {
                                                                    $num = intval($component);
                                                                    $result = abs($num - $rest);
                        
                                                                    $latestPluginVersion = $pluginVersion . $result . '.';
                                                                }
                        
                                                                $latestPluginVersion = substr($latestPluginVersion, 0, strlen($latestPluginVersion) - 1);

                                                                if (strpos($pluginId, '-') !== false) {
                                                                    $vData = explode('-', $pluginId)[1];
                                                                    if (strpos($vData, '.') !== false) {
                                                                        $vData = explode('.', $vData);
                                                                        $vId = $vData[0];
                                                                        $rest = intval($vData[1]);
                                
                                                                        $idData = explode('/', $vId);
                                
                                                                        $pluginVersion = "";
                                                                        foreach ($idData as $component) {
                                                                            $num = intval($component);
                                                                            $result = abs($num - $rest);
                                
                                                                            $pluginVersion = $pluginVersion . $result . '.';
                                                                        }
                                
                                                                        $pluginVersion = substr($pluginVersion, 0, strlen($pluginVersion) - 1);

                                                                        $result = Utils::checkVersions($pluginVersion, $latestPluginVersion);

                                                                        switch ($result) {
                                                                            case 1:
                                                                                $response['success'] = true;
                                                                                $response['message'] = 'Plugin is over the latest version';
                                                                                break;
                                                                            case -1:
                                                                                $response['success'] = false;
                                                                                $diff = $lockLogin->getOldness($pluginVersion);
                                                                                $response['message'] = 'Plugin is out of date ( '. $diff .' updates behind )';
                                                                                break;
                                                                            case 0:
                                                                            default:
                                                                                $response['success'] = true;
                                                                                $response['message'] = 'Plugin is up to date';
                                                                                break;
                                                                        }
                                                                    } else {
                                                                        $response['success'] = false;
                                                                        $response['message'] = "Invalid version ID";
                                                                    }
                                                                } else {
                                                                    $response['success'] = false;
                                                                    $response['message'] = "Invalid version ID";
                                                                }
                                                            } else {
                                                                $response['success'] = false;
                                                                $response['message'] = "Invalid latest version ID";
                                                            }
                                                        } else {
                                                            $response['success'] = false;
                                                            $response['message'] = "Invalid latest version ID";
                                                        }
                                                    }
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "There're no updates released!";
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Invalid source";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Unknown server";
                                        }
                                        break;
                                    case 'resolve':
                                        $pluginId = $data['plugin_version'];

                                        if (strpos($pluginId, '-') !== false) {
                                            $vData = explode('-', $pluginId)[1];
                                            if (strpos($vData, '.') !== false) {
                                                $vData = explode('.', $vData);
                                                $vId = $vData[0];
                                                $rest = intval($vData[1]);

                                                $idData = explode('/', $vId);

                                                $version = "";
                                                foreach ($idData as $component) {
                                                    $num = intval($component);
                                                    $result = abs($num - $rest);

                                                    $version = $version . $result . '.';
                                                }

                                                $version = substr($version, 0, strlen($version) - 1);

                                                $response['success'] = true;
                                                $response['message'] = $version;
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Invalid plugin version ID ( 2 )";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Invalid plugin version ID";
                                        }
                                        break;
                                    case 'changelog':
                                        $pluginId = $data['plugin_version'];
                                        $channel = $data['query'];
                                        if ($channel == null) {
                                            $channel = 'release';
                                        }

                                        if ($pluginId == null) {
                                            //Retrieve changelog for latest version
                                            $latest_version = $lockLogin->getUpdate(true, $channel);

                                            if ($latest_version != null) {
                                                $response['success'] = true;
                                                $response['message'] = explode("\n", str_replace("\r", "", $latest_version['changelog']));
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "There're no updates released!";
                                            }
                                        } else {
                                            //Retrieve changelog for specific version
                                            $update_version = $lockLogin->getUpdate(false, $pluginId);

                                            if ($update_version != null) {
                                                $response['success'] = true;
                                                $response['message'] = explode("\n", str_replace("\r", "", $update_version['changelog']));
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "There's no update for that version!";
                                            }
                                        }
                                        break;
                                    case 'info':
                                        $server = $lockLogin->getServer($server_hash);

                                        if (ClientData::isAuthenticated()) {
                                            $result = $lockLogin->getServer($server_hash);

                                            if ($result != null) {
                                                $clientData = new ClientData();
                                                $owner_data = $clientData->loadProfileData($result['owner']);

                                                if (!empty($owner_data)) {
                                                    $allowed_names = [];

                                                    foreach ($result['allowed'] as $allowedId) {
                                                        $uData = $clientData->loadProfileData($allowedId);

                                                        if (!empty($uData)) {
                                                            array_push($allowed_names, $uData['name']);
                                                        }
                                                    }

                                                    $c_date = $result['date'];

                                                    $newTimezone = new DateTime($c_date, new DateTimeZone('UTC'));
                                                    $newTimezone->setTimezone(new DateTimeZone(Utils::get('timezone')));

                                                    $c_date = $newTimezone->format('U');

                                                    $response['success'] = true;
                                                    $response['message'] = "Successfully fetched server information";
                                                    $response[$result['server']] = array(
                                                        'owner' => $owner_data['name'],
                                                        'registration_date' => date("d/m/y H:m:s", $c_date),
                                                        'allowed_users' => $allowed_names
                                                    );
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "Server not available";
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "Unknown server";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "User must be authenticated in order to fetch server information";
                                        }
                                        break;
                                    case 'register':
                                        $display = $data['name'];

                                        if ($display != null) {
                                            if (ClientData::isAuthenticated()) {
                                                $result = $lockLogin->registerServer($server_hash, $display);

                                                if ($result) {
                                                    $response['success'] = true;
                                                    $response['message'] = "Server registered successfully";
                                                } else {
                                                    $response['success'] = false;
                                                    $response['message'] = "Failed to register server. Make sure it is not already registered";
                                                }
                                                if (gettype($result) == 'string') {
                                                    $response['success'] = false;
                                                    $response['message'] = $result;
                                                } else {
                                                    $response['success'] = $result;
                                                    $response['message'] = ($result ? "Registered server {$display}" : "Failed to register server {$display}");
                                                }
                                            } else {
                                                $response['success'] = false;
                                                $response['message'] = "User must be authenticated in order to register a server";
                                            }
                                        } else {
                                            $response['success'] = false;
                                            $response['message'] = "Server display name must be provided";
                                        }
                                        break;
                                }
                            } else {
                                $response['success'] = false;
                                $response['message'] = "This LockLogin instance is not authorized to perform this request or server hash must be provided in order to register a LockLogin instance";
                            }
                        } else {
                            $response['success'] = false;
                            $response['message'] = "Plugin action must be provided";
                        }
                        break;
                    default:
                        $response['success'] = false;
                        $response['message'] = "Unknown API method: " . $method;
                        break;
                }
            } else {
                $response['success'] = false;
                $response['message'] = "API key not found";
            }

            if ($store_request && isset($access_client['id'])) {
                $connection = new ClientData();
                $connection->performRequest($access_client['id'], $method, (isset($data['action']) ? $data['action'] : null), $tmpRequestData);
                $response['api_client'] = $access_client['name'];
            }
        } else {
            $response['success'] = false;
            $response['message'] = "Invalid API key";
        }
    }
} else {
    $response['success'] = false;
    $response['message'] = "API method must be provided";
}

echo json_encode($response, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);