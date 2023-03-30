<?php

namespace KarmaDev\Panel;

class UUID {

    public static function offlineId(string $username) {
        $data = hex2bin(md5("OfflinePlayer:{$username}"));
        $data[6] = chr(ord($data[6]) & 0x0f | 0x30);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return self::fromStripped(bin2hex($data));
    }

    public static function fromStripped(string $striped) {
        $components = array(
            substr($striped, 0, 8),
            substr($striped, 8, 4),
            substr($striped, 12, 4),
            substr($striped, 16, 4),
            substr($striped, 20),
        );

        return implode('-', $components);
    }

    public static function trimFull(string $uuid) {
        return str_replace('-', '', $uuid);
    }

    public static function generate(string $info) {
        $data = hex2bin(md5($info));
        $data[6] = chr(ord($data[6]) & 0x0f | 0x30);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return UUIDUtil::fromStripped(bin2hex($data));
    }

    public static function retrieveId(string $name) {
		$url = self::findValidURL($name);
		$response = self::getResponse($url);
		$uuid = 'unknown';

		switch(self::getProvider($url)) {
			case 'Mojang':
				$json = json_decode($response, true);
				$uuid = $json['id'];
				break;
			case 'Backup 1':
				$uuid = $response;
				break;
			case 'Backup 2':
			default:
				$json = json_decode($response, true);
				$uuid = $json['id'];
				break;
		}

		if (strlen($uuid) == 32) {
			return self::fromStripped($uuid);
		} else {
			return "";
		}
	}

    private static function findValidURL(string $name) {
		$url = "https://api.mojang.com/users/profiles/minecraft/{$name}";
		$header = @get_headers($url);

		if (!$header || !strpos($header[0], '200')) {
			$url = "https://minecraft-api.com/api/uuid/{$name}";
			$header = @get_headers($url);

			if (!$header || !strpos($header[0], '200')) {
				$url = "https://api.minetools.eu/uuid/{$name}";
				$header = @get_headers($url);

				if (!$header || !strpos($header[0], '200')) {
					//Try it until it finds a valid URL
					return UUIDFetch::findValidURL($name);
				} else {
					return $url;
				}
			} else {
				return $url;
			}
		} else {
			return $url;
		}
	}

    private static function getResponse(string $url, object $post = null) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		if(!empty($post)) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		} 
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

    private static function getProvider(string $url) {
		if (str_contains($url, 'api.mojang.com')) {
			return 'Mojang';
		} else {
			if (str_contains($url, 'minecraft-api.com')) {
				return 'Backup 1';
			} else {
				return 'Backup 2';
			}
		}
	}
}