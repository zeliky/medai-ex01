<?php

namespace App\Models;

use App\Models\Db;



class Clients
{
    public static function find($id)
    {
        $db = Db::getInstance();
        $sql = "SELECT * FROM Clients where id=:id";
        $params = [
            'id' => $id
        ];
        return $db->select($sql, $params, Db::QUERY_RESULTS_OBJECT_ROW);
    }

    public static function findByName($firstName, $lastName)
    {
        $db = Db::getInstance();
        $sql = "SELECT * 
                FROM Clients 
                WHERE first_name=:first_name and last_name=:last_name
                LIMIT 1";
        $params = [
            'first_name' => $firstName,
            'last_name' => $lastName
        ];
        return $db->select($sql, $params, Db::QUERY_RESULTS_OBJECT_ROW);
    }

    public static function findByNameAutoComplete($searchTerm){
        $nameParts = explode(' ', $searchTerm);

        $db = Db::getInstance();
        $sql = "SELECT id as `value`, concat(first_name, ' ' , last_name) as `text`
                FROM Clients
                WHERE 
                    (first_name LIKE :part1 AND last_name LIKE :part2)  OR                    
                    (first_name LIKE :part2 AND  last_name LIKE :part1) 
                LIMIT 10";
        $params = [
            'part1' => '%'.$nameParts[0].'%' ?? '',
            'part2' => '%'.$nameParts[1].'%' ?? ''
        ];

        return $db->select($sql, $params, Db::QUERY_RESULTS_OBJECT_ROWS);
    }
    public static function add($firstName, $lastName)
    {
        $db = Db::getInstance();
        $client = self::findByName($firstName, $lastName);
        if (is_null($client)) {
            $data = [
                'first_name' => $firstName,
                'last_name' => $lastName
            ];
            return $db->insert('Clients', $data);
        }
    }
}