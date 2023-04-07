<?php

namespace App\Models;

use App\Beans\MedaiRecord;

class TemporalMedai
{
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
        $fromTime = new \DateTime();
        $fromTime->setTime(0,0);
        $toTime = new \DateTime();
        $toTime->setTime(23,59);

        if (!empty($searchQry['client_id'])) {
            $params['client_id'] = intval($searchQry['client_id']);
        }

        if (!empty($searchQry['loinc'])) {
            $params['loinc'] = trim($searchQry['loinc']);
        }


        //$params['view_time'] = $viewTime->format('Y-m-d H:i:s');
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

        if (!empty($searchQry['from_date'])) {
            $fromTime = \DateTime::createFromFormat('Y-m-d', $searchQry['from_date']);

            if (!empty($dtime)) {
                $fromTime->setTime(0, 0, 0);
                $params['from_time'] = $dtime->format('Y-m-d H:i:s');
            }

        }
        if (!empty($searchQry['from_hour'])) {
            $dtime = \DateTime::createFromFormat('H:i', $searchQry['from_hour']);
            if (!empty($dtime)) {
                $fromTime->setTime($dtime->format('H'), $dtime->format('i'));
                $params['from_time'] = $fromTime->format('Y-m-d H:i:s');
            }

        }

        # if to time it not set: consider  from=to  - to get exact time match
        if(!empty( $params['from_time'])) {
            $params['to_time'] = $params['from_time'];
        }

        if (!empty($searchQry['to_date'])) {
            $dtime = \DateTime::createFromFormat('Y-m-d', $searchQry['to_date']);
            if (!empty($dtime)) {
                $dtime->setTime(23, 59, 59);
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

        $sql = "SELECT *
                from  TemporalMedai
                WHERE  ";

        $whereQry =['is_deleted=0'];

        if (isset($params['client_id'])){
            $whereQry[]  = '(client_id = :client_id)';
        }

        if (isset($params['loinc'])){
            $whereQry[]  = '(loinc_code = :loinc)';
        }

        if (isset($params['view_time'])){
            $whereQry[]  = '(transaction_time <= :view_time)';
        }

        if (isset($params['from_time'])){
            $whereQry[]  = '(valid_start_time >= :from_time)';
        }

        if (isset($params['to_time'])){
            $whereQry[]  = '(valid_start_time <= :to_time and (valid_end_time is NULL or valid_end_time <= :to_time))';
        }

        if (!empty($whereQry)){
            $sql .= implode(' AND ', $whereQry);
        }
        $offset = $page* $pageSize;
        $sql .= " LIMIT $offset,$pageSize ";

        $db= Db::getInstance();
        $results = $db->select($sql, $params, Db::QUERY_RESULTS_OBJECT_ROWS);

        error_log($sql);
        error_log(json_encode($params));
        return $results;
        /*return [
            'sql' => $sql,
            'params' => $params,
            'data' => $results,
        ];*/
    }

    public static function addRecord(array $data): array
    {
        $record = new MedaiRecord($data);
        $values = $record->toDbRecord();

        $currentValues = self::getLatestRecord($values['client_id'],$values['loinc_code'], $values['valid_start_time'] );
        if (!empty($currentValues)){
            throw new \Exception('same record already exist ');
        }



        $db = Db::getInstance();
        $id= $db->insert('TemporalMedai', $values);
        return self::find($id);
    }

    public static  function updateRecordByKeys($firstName, $lastName, $loincCode, $validTime, $newValue): array
    {
        $client = Clients::findByName($firstName,$lastName);
        if (empty($client)){
            throw new \Exception( 'Invalid client (first name/ last name)');
        }
        if ($validTime instanceof  \DateTime){
            $validTime->setTime($validTime->format('H'),$validTime->format('i'),0);
            $validTime = $validTime->format('Y-m-d H:i:s');
        }

        $currentValues = self::getLatestRecord($client->id,$loincCode, $validTime );
        if (empty($currentValues)){
            throw new \Exception('no valid record for requested {client, loinc, time} ');
        }

        return self::updateRecord($currentValues, $newValue);
    }


    public static  function updateRecordById($id, $newValue){
        $currentValues = self::find($id);
        if (empty($lastRecord)){
            throw new \Exception('no valid record for requested id ');
        }
        self::updateRecord($currentValues, $newValue);
    }
    public static function updateRecord($values, $newTestValue): array
    {

        $transactionTime = new \DateTime();
        unset($values['id']);
        $values['value'] = $newTestValue;
        $values['transaction_time'] = $transactionTime->format('Y-m-d H:i:s');
        $values['transaction_date'] = $transactionTime->format('Y-m-d');
        $values['transaction_hour'] = $transactionTime->format('H:i:s');

        $db = Db::getInstance();
        $id = $db->insert('TemporalMedai', $values);
        return self::find($id);
    }

    public static function deleteRecord($firstName, $lastName, $loincCode, $validTime): bool
    {

        $client = Clients::findByName($firstName,$lastName);
        if (empty($client)){
            throw new \Exception( 'Invalid client (first name/ last name)');
        }
        if ($validTime instanceof  \DateTime){
            $validTime->setTime($validTime->format('H'),$validTime->format('i'),0);
            $validTime = $validTime->format('Y-m-d H:i:s');
        }


        $latest = self::getLatestRecord($client->id,$loincCode, $validTime );
        if (empty($latest)){
            throw new \Exception('no valid record for requested {client, loinc, time}');
        }
        $now = new \DateTime();
        $db = Db::getInstance();
        $upd['is_deleted'] = 1;
        $upd['deleted_at'] = $now->format('Y-m-d H:i:s');
        $rowCount =  $db->update('TemporalMedai', $upd,'id='.$latest['id']);
        return ($rowCount>0);
    }
}