<?php

class ModelLocalisationNpApiAllUpdate extends Model {

    protected static $api_key = '[KEY]'; // персональный api key

    public function rebuildDatabase()
    {
		$query = $this->db->query("ALTER TABLE `" . DB_PREFIX . "zone` ADD `ref` varchar(50) NOT NULL AFTER `status`");
		$query = $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "city`");
		$query = $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "zone`");
        $this->rebuildCountries(); // области
        $this->addZones();  // города
        $this->addCities(); // отделения
    }

    public function rebuildCountries()
    {
        $params = [
            'modelName' => 'Address',
            'calledMethod' => 'getAreas',
            'methodProperties' => [
                'Language' => 'ru'
            ],
            'apiKey' => self::$api_key
        ];

        $areas = self::getApiData($params);
        if (!empty($areas)) {
            $query = $this->db->query("DROP TABLE `" . DB_PREFIX . "country`");
            $query = $this->db->query("CREATE TABLE `" . DB_PREFIX . "country` (`country_id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(128) NOT NULL, `iso_code_2` varchar(2) NOT NULL, `iso_code_3` varchar(3) NOT NULL, `address_format` text NOT NULL, `postcode_required` tinyint(1) NOT NULL,  `status` tinyint(1) NOT NULL DEFAULT '1', `ref` varchar(50), PRIMARY KEY (`country_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");

            $insert = '';
            $count_areas = count($areas);
            for ($i = 0; $i < $count_areas; $i++) {
                if ($i === 0) {
                    continue;
                }
                $id = $i+300000; // id как в модуле opencart2x(webmakers)
                $insert .= "({$id},	'{$areas[$i]['Description']}',	'UA',	'UKR',	'',	0,	1,	'{$areas[$i]['Ref']}')";
                if ($i+1 !== $count_areas) {
                    $insert .= ',';
                }
            }
            $this->db->query("INSERT INTO `" . DB_PREFIX . "country` (`country_id`, `name`, `iso_code_2`, `iso_code_3`, `address_format`, `postcode_required`, `status`, `ref`) VALUES {$insert}");
        }
    }

    public function addZones()
    {
        $params = [
            'modelName' => 'Address',
            'calledMethod' => 'getCities',
            'apiKey' => self::$api_key
        ];

        $zones = self::getApiData($params);
        if (!empty($zones)) {
            //$query = $this->db->query("DELETE FROM `" . DB_PREFIX . "zone`");
            $insert = '';
            $count_zones = count($zones);
            for ($i = 0; $i < $count_zones; $i++) {
                $current = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE `code` = '{$zones[$i]['CityID']}' LIMIT 1");
                if ($current->num_rows > 0) {
                    if ($current->row['ref'] === null) {
                        $this->db->query("UPDATE `" . DB_PREFIX . "zone` SET `ref` = '{$zones[$i]['Ref']}' WHERE `code` = '{$zones[$i]['CityID']}';");
                    }
                    continue;
                }
                $country = $this->db->query("SELECT `country_id` FROM `" . DB_PREFIX . "country` WHERE `ref` = '{$zones[$i]['Area']}' LIMIT 1");
                $country_id = (int)$country->row['country_id'];
                $insert .= "({$country_id}, '{$zones[$i]['DescriptionRu']}',	'{$zones[$i]['CityID']}',	1, '{$zones[$i]['Ref']}')";
                if ($i+1 !== $count_zones) {
                    $insert .= ',';
                }
            }
            if (strlen($insert) > 0) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "zone` (`country_id`, `name`, `code`, `status`,`ref`) VALUES {$insert}");
            }
        }
    }

    public function addCities()
    {
        $params = [
            'modelName' => 'Address',
            'calledMethod' => 'getWarehouses',
            'apiKey' => self::$api_key
        ];

        $cities = self::getApiData($params);
        if (!empty($cities)) {
            //$query = $this->db->query("DELETE FROM `" . DB_PREFIX . "city`");
            $insert = '';
            $count_cities = count($cities);
            for ($i = 0; $i < $count_cities; $i++) {
                $current = $this->db->query("SELECT * FROM `" . DB_PREFIX . "city` WHERE `code` = '{$cities[$i]['SiteKey']}' LIMIT 1");
                if ($current->num_rows > 0) {
                    continue;
                }
                $zone = $this->db->query("SELECT `zone_id` FROM `" . DB_PREFIX . "zone` WHERE `ref` = '{$cities[$i]['CityRef']}' LIMIT 1");
                $zone_id = (int)$zone->row['zone_id'];
                $insert .= "({$zone_id}, '{$cities[$i]['DescriptionRu']}',	1,  '{$cities[$i]['SiteKey']}',	1)";
                if ($i+1 !== $count_cities) {
                    $insert .= ',';
                }
            }
            if (strlen($insert) > 0) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "city` (`zone_id`, `name`, `status`, `code`, `sort_order`) VALUES {$insert}");
            }
        }
    }

    protected static function getApiData($params)
    {
        $ch = curl_init('https://api.novaposhta.ua/v2.0/json/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        // Submit the POST request
        $result = json_decode(curl_exec($ch), true);

        // Close cURL session handle
        curl_close($ch);

        if ($result['success'] === true) {
            return $result['data'];
        }
        return [];
    }
}