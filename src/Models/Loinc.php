<?php

namespace App\Models;

use App\Models\Db;

class Loinc
{
    public static function find($loincNum){
        $db = Db::getInstance();
        $sql = "SELECT 
                    loinc_num as id,  
                    long_common_name as name,
                    time_aspct
                FROM Loinc
                WHERE loinc_num = :loinc_num";
        $params = [
            'loinc_num' => $loincNum
        ];
        return $db->select($sql, $params, Db::QUERY_RESULTS_OBJECT_ROW);

    }
    public static function findByLoincNumAutoComplete($searchTerm){
        $db = Db::getInstance();
        $sql = "SELECT loinc_num as `value`,  concat(loinc_num,': ',long_common_name) as `label`
                FROM Loinc
                WHERE 
                    loinc_num LIKE :term  OR                    
                    long_common_name LIKE :term
                LIMIT 10";
        $params = [
            'term' =>  '%'.$searchTerm.'%' ?? ''
        ];
        return $db->select($sql, $params, Db::QUERY_RESULTS_OBJECT_ROWS);
    }
}