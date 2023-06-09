<?php

namespace App\Models;

use App\Beans\MedaiRecord;

class TemporalMedai
{
    public static function cleanData(){
        $db = Db::getInstance();
        $sql = 'truncate table TemporalMedai';
        $db->query($sql);
    }


    public static function find($id) {
        $db = Db::getInstance();
        $sql = "SELECT * FROM TemporalMedai where id=:id";
        $params = [
            'id' => $id
        ];
        return $db->select($sql, $params, Db::QUERY_RESULTS_ROW);
    }


    public static function prepareParams($searchQry=[]): array
    {
        $params = [];
        $viewTime = new \DateTime();
        //$fromTime = new \DateTime();
        //$fromTime->setTime(0,0);
        $toTime = new \DateTime();
        $toTime->setTime(23,59);
        $queryType = $searchQry['query_type'] ?? 'history';

        if (!empty($searchQry['client_id'])) {
            $params['client_id'] = intval($searchQry['client_id']);
        }

        if (!empty($searchQry['loinc_id'])) {
            $params['loinc'] = trim($searchQry['loinc_id']);
        }


        $params['view_time'] = $viewTime->format('Y-m-d H:i:s');
        //$params['from_time'] = $fromTime->format('Y-m-d H:i:s');
        //$params['to_time'] = $toTime->format('Y-m-d H:i:s');

        if (!empty($searchQry['pov_date'])) {
            $viewTime = \DateTime::createFromFormat('Y-m-d', $searchQry['pov_date']);
            if (!empty($viewTime)) {
                $viewTime->setTime(23, 59, 59);
                $params['view_time'] = $viewTime->format('Y-m-d H:i:s');
            }
        }

        if (!empty($searchQry['pov_hour'])) {
            $dtime = \DateTime::createFromFormat('H:i', $searchQry['pov_hour']);
            if (!empty($dtime)) {
                $viewTime->setTime($dtime->format('H'), $dtime->format('i'));
                $params['view_time'] = $viewTime->format('Y-m-d H:i:s');
            }
        }

        // ======================================== retrieval ===============================================

        if ($queryType=='retrieval'){
            $atTime = new \DateTime();
            if (!empty($searchQry['at_date'])) {
                $atTime = \DateTime::createFromFormat('Y-m-d', $searchQry['at_date']);
            }
            if (empty($searchQry['at_hour'])) {
                $atTime->setTime(0,0);
                $params['from_time'] = $atTime->format('Y-m-d H:i:s');
                $atTime->setTime(23,59);
                $params['to_time'] = $atTime->format('Y-m-d H:i:s');

            }else {
                $dtime = \DateTime::createFromFormat('H:i', $searchQry['at_hour']);
                if ( !empty($dtime)) {
                    $atTime->setTime($dtime->format('H'), $dtime->format('i'));
                    $params['at_time'] = $atTime->format('Y-m-d H:i:s');
                }
            }
        }

        // ======================================== history ===============================================

        if ($queryType=='history') {
            if (!empty($searchQry['from_date'])) {
                $fromTime = \DateTime::createFromFormat('Y-m-d', $searchQry['from_date']);

                if ($fromTime && !empty($dtime)) {
                    $fromTime->setTime(0, 0, 0);
                    $params['from_time'] = $dtime->format('Y-m-d H:i:s');
                }

            }
            if (!empty($searchQry['from_hour'])) {
                $dtime = \DateTime::createFromFormat('H:i', $searchQry['from_hour']);
                if ($fromTime && !empty($dtime)) {
                    $fromTime->setTime($dtime->format('H'), $dtime->format('i'));
                    $params['from_time'] = $fromTime->format('Y-m-d H:i:s');
                }
            }

            if (!empty($searchQry['to_date'])) {
                $toTime = \DateTime::createFromFormat('Y-m-d', $searchQry['to_date']);
                if (!empty($toTime)) {
                    $toTime->setTime(23, 59, 59);
                    $params['to_time'] = $toTime->format('Y-m-d H:i:s');
                }
            }
            if (!empty($searchQry['to_hour'])) {
                $dtime = \DateTime::createFromFormat('H:i', $searchQry['to_hour']);
                if (!empty($dtime)) {
                    $toTime->setTime($dtime->format('H'), $dtime->format('i'));
                    $params['to_time'] = $toTime->format('Y-m-d H:i:s');
                }
            }
        }
        return $params;
    }


    public static function getLatestRecord($clientId, $loincCode, $validTime, $viewTime=null){

        $db=Db::getInstance();
        if (empty($viewTime)){
            $viewTime = new \DateTime();
        }
        if ($viewTime instanceof  \DateTime){
            $viewTime->setTime($viewTime->format('H'),$viewTime->format('i'),0);
            $viewTime = $viewTime->format('Y-m-d H:i:s');
        }

        if ($validTime instanceof  \DateTime){
            $validTime->setTime($validTime->format('H'),$validTime->format('i'),0);
            $validTime = $validTime->format('Y-m-d H:i:s');
        }
        $sql = "SELECT *
                from  TemporalMedai
                WHERE is_deleted=0 and client_id=:client_id and loinc_code=:loinc_code 
                    and valid_start_time=:valid_start_time  and transaction_time <=:transaction_time 
                ORDER BY transaction_time desc
                LIMIT 1
                ";
        $params = [
            'client_id' => $clientId,
            'loinc_code' => $loincCode,
            'valid_start_time' => $validTime,
            'transaction_time' => $viewTime,
        ];

        return $db->select($sql,$params,Db::QUERY_RESULTS_ROW);
    }
    public static function getRecords($searchQry, $page=0, $pageSize = 100): array
    {
        $params =  self::prepareParams($searchQry);
        $queryType = $searchQry['query_type'] ?? 'history';

        $sql = "SELECT c.first_name, c.last_name, t.* ". PHP_EOL.
                "FROM  TemporalMedai as t ". PHP_EOL.
                "INNER JOIN Clients as c ON t.client_id = c.id ". PHP_EOL.
                "WHERE ". PHP_EOL;

        $whereQry[]  = '(transaction_time <= :view_time and valid_start_time <= :view_time)';
        $whereQry[]  = '(is_deleted=0  or deleted_at > :view_time)';

        if (isset($params['client_id'])){
            $whereQry[]  = '(client_id = :client_id)';
        }

        if (isset($params['loinc'])){
            $whereQry[]  = '(loinc_code = :loinc)';
        }

        if (isset($params['at_time'])){
            $whereQry[]  = '(valid_start_time = :at_time)';
        }

        if (isset($params['from_time'])){
            $whereQry[]  = '(valid_start_time >= :from_time)';
        }

        if (isset($params['to_time'])){
            $whereQry[]  = '(valid_start_time <= :to_time and (valid_end_time is NULL or valid_end_time <= :to_time))';
        }

        if (!empty($whereQry)){
            $sql .= implode(PHP_EOL.' AND ', $whereQry);
        }
        $offset = $page* $pageSize;
        if ($queryType=='retrieval') {
            $sql .= "\n ORDER BY valid_start_time desc, transaction_time desc ";
            $sql .= "\n LIMIT 1 ";
        } else {
            $sql .= "\n ORDER BY  valid_start_time ";
            $sql .= "\n LIMIT $offset,$pageSize ";
        }

        $db= Db::getInstance();
        $results = $db->select($sql, $params, Db::QUERY_RESULTS_OBJECT_ROWS);
        #echo ($sql);
        #echo(json_encode($params));
        #error_log($sql);
        #error_log(json_encode($params));

        $cliParams =[];
        foreach($params as $k=>$v) {
            $cliParams[':'.$k] = "'".$v ."'";
        }
        $sql = str_replace(array_keys($cliParams), array_values($cliParams), $sql);

        return [
            'qry' => $sql,
            'data' => $results,
        ];
    }

    public static function addRecord(array $data, $returnNewRecord=true): ?array
    {
        static $i = 0;
        $record = new MedaiRecord($data);

        $values = $record->toDbRecord();
        $currentValues = self::getLatestRecord($record->client_id, $record->loinc_code, $record->valid_start_time );
        if (!empty($currentValues)){
            if($currentValues['transaction_time'] !== $record->transaction_time->format('Y-m-d H:i:s')){
                self::updateRecord($currentValues, $record->value, $record->transaction_time);
            }
            throw new \Exception('same record already exist ');
        }

        //print_r($i++ . ')'.  implode(" | " , $values)  . ' ---> '. ($currentValues!==false). PHP_EOL) ;


        $db = Db::getInstance();

        $id= $db->insert('TemporalMedai', $values);

        if ($returnNewRecord)
            return self::find($id);
        return null;
    }

    public static  function updateRecordByKeys(MedaiRecord $record): array
    {
        if (!empty($record->client_id)) {
            $client = Clients::find($record->client_id);
        }elseif ( !empty($record->first_name) && !empty($record->last_name) ){
            $client = Clients::findByName($record->first_name, $record->last_name);
        }

        if (empty($client)){
            throw new \Exception( 'Invalid client (first name/ last name)');
        }

        $currentValues = self::getLatestRecord($client->id, $record->loinc_code, $record->valid_start_time );
        if (empty($currentValues)){
            throw new \Exception('no valid record for requested {client, loinc, time} ');
        }
        if($currentValues['value'] == $record->value){
            throw new \Exception('The updated value is same as original value.');
        }
        return self::updateRecord($currentValues, $record->value);
    }


    public static  function updateRecordById($id, $newValue){
        $currentValues = self::find($id);
        if (empty($lastRecord)){
            throw new \Exception('no valid record for requested id ');
        }
        self::updateRecord($currentValues, $newValue);
    }
    public static function updateRecord($values, $newTestValue, $transactionTime=null): array
    {
        if(is_null($transactionTime))
            $transactionTime = new \DateTime();

        self::deleteRecordById($values['id'], $transactionTime);
        unset($values['id']);
        $values['value'] = $newTestValue;
        $values['transaction_time'] = $transactionTime->format('Y-m-d H:i:s');
        $values['transaction_date'] = $transactionTime->format('Y-m-d');
        $values['transaction_hour'] = $transactionTime->format('H:i:s');

        $db = Db::getInstance();
        $id = $db->insert('TemporalMedai', $values);
        return self::find($id);
    }

    public static function deleteRecordNaturalKey($firstName, $lastName, $loincCode, $validTime, $deletedAt=null): bool
    {
        $client = Clients::findByName($firstName,$lastName);
        if (empty($client)){
            throw new \Exception( 'Invalid client (first name/ last name)');
        }
        if ($validTime instanceof  \DateTime){
            $validTime->setTime($validTime->format('H'),$validTime->format('i'),0);
            $validTime = $validTime->format('Y-m-d H:i:s');
        }
        if ($deletedAt instanceof  \DateTime){
            $deletedAt->setTime($deletedAt->format('H'),$deletedAt->format('i'),0);

        }


        $latest = self::getLatestRecord($client->id,$loincCode, $validTime );
        if (empty($latest)){
            throw new \Exception('no valid record for requested {client, loinc, time}');
        }
        return self::deleteRecordById($latest['id'],$deletedAt);
    }

    /**
     * @param $id
     * @return bool
     */
    public static function deleteRecordById($id, $deletedAt=null): bool
    {
        $now = new \DateTime();
        $db = Db::getInstance();
        $upd['is_deleted'] = 1;
        $upd['deleted_at'] = $deletedAt->format('Y-m-d H:i:s') ?? $now->format('Y-m-d H:i:s');
        $rowCount = $db->update('TemporalMedai', $upd, 'id=' . $id);
        return ($rowCount > 0);
    }


}