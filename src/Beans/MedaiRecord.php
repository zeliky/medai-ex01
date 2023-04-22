<?php

namespace App\Beans;

use App\Models\Clients;
use App\Models\Loinc;

class MedaiRecord
{
    public $client_id;
    public $first_name;
    public $last_name;

    public $concept_name;

    public $loinc_code;

    public $value;

    public $unit;

    public $valid_start_time;

    public $valid_stop_time;

    public $transaction_time;


    public function __construct($data)
    {

        $this->client_id = $data['client_id'] ?? null;
        $this->first_name = $data['first_name'] ?? null;
        $this->last_name = $data['last_name'] ?? null;
        $this->concept_name = $data['concept_name'] ?? null;
        $this->loinc_code = $data['loinc_code'] ?? null;
        $this->value = $data['value'] ?? null;
        $this->unit = $data['unit'] ?? null;

        $sample = $data['valid_start_time']??$data['valid_stop_time']??$data['transaction_time']??null;

        $format = detectFormat($sample);

        $dtime =  \DateTime::createFromFormat($format, $data['valid_start_time'] ?? null) ;
        if ($dtime)
            $this->valid_start_time = $dtime;

        $dtime =  \DateTime::createFromFormat($format, $data['valid_stop_time'] ?? null) ;
        if ($dtime)
            $this->valid_stop_time = $dtime;

        $dtime =  \DateTime::createFromFormat($format, $data['transaction_time'] ?? null) ;
        if ($dtime)
            $this->transaction_time = $dtime;

    }

    public function toDbRecord()
    {
        if(empty($this->loinc_code)){
            throw new \Exception('missing loinc_code');
        }
        $this->client_id =  null;
        $loincRecord = Loinc::find($this->loinc_code);
        if(empty($loincRecord)){
            throw new \Exception('invalid loinc_code');
        }
        $loincLongCommonName = $loincRecord->name;


        if (!empty($this->first_name) && !empty($this->last_name)) {
            $this->client_id = Clients::add($this->first_name, $this->last_name);
        }
        $transactionTime =  (!empty($this->transaction_time) ? $this->transaction_time: new \DateTime() );
        $transactionTime->setTime($transactionTime->format('H'), $transactionTime->format('i'), 0);

        $validStarTime = (!empty($this->transaction_time) ? $this->valid_start_time: new \DateTime() );
        $validStarTime->setTime($validStarTime->format('H'), $validStarTime->format('i'), 0);


        $validStarTime = (!empty($this->transaction_time) ? $this->valid_start_time: new \DateTime() );
        $validStarTime->setTime($validStarTime->format('H'), $validStarTime->format('i'), 0);

        $this->valid_stop_time = $this->calcValidEndTime($validStarTime, $loincRecord->time_aspct);


        return [
            'transaction_time' => $transactionTime->format('Y-m-d H:i:s'),
            'valid_start_time' => $validStarTime->format('Y-m-d H:i:s') ,
            'valid_end_time' => $this->valid_stop_time->format('Y-m-d H:i:s'),
            'client_id' => $this->client_id,
            'loinc_code' => $this->loinc_code,
            'loinc_long_common_name' => $loincLongCommonName,
            'value' => $this->value,
            'unit' => $this->unit,
            'transaction_date' => $transactionTime->format('Y-m-d'),
            'transaction_hour' => $transactionTime->format('H:i:s'),
            'valid_start_date' => $validStarTime->format('Y-m-d'),
            'valid_start_hour' => $validStarTime->format('H:i:s'),
            'valid_end_date' => !is_null($this->valid_stop_time) ? $this->valid_stop_time->format('Y-m-d') : null,
            'valid_end_hour' => !is_null($this->valid_stop_time) ? $this->valid_stop_time->format('H:i:s') : null,
            'is_deleted' => 0,
            'deleted_at' => null
        ];

    }


    private function calcValidEndTime($validStartTime, $timeAspect)
    {
        static $map = [
            'Pt' => "PT0H", //	To identify measures at a point in time. This is a synonym for “spot” or “random” as applied to urine measurements.
            '1M' => "PT1M", //	1 minute
            '5M' => "PT5M", //	5 minutes
            '10M' => "PT10M", //	10 minutes
            '15M' => "PT15M",//	15 minutes
            '20M' => "PT20M",//	20 minutes
            '30M' => "PT30M",//	30 minutes
            '45M' => "PT45M",//	45 minutes
            '90M' => "PT90M",//	90 minutes
            '1H' => "PT1H", //1 hour
            '2H' => "PT2H", //2 hour
            '2.5H' => "PT2.5H", //2.5 hour2
            '3H' => "PT3H", //3 hours
            '4H' => "PT4H", //4 hours
            '5H' => "PT5H", //5 hours
            '6H' => "PT6H", //6 hours
            '7H' => "PT7H", //7 hours
            '8H' => "PT8H", //8 hours
            '9H' => "PT9H", //9 hours
            '10H' => "PT10H", //10 hours
            '12H' => "PT12H", //12 hours
            '18H' => "PT18H", //18 hours
            '24H' => "PT24H", //24 hours
            '48H' => "PT48H", //48 hours
            '72H' => "PT72H", //72 hours
            '1D' => "P1D", //1 Day
            '2D' => "P2D", //2 Days
            '3D' => "P3D", //3 Days
            '4D' => "P4D", //4 Days
            '5D' => "P5D", //5 Days
            '6D' => "P6D", //6 Days
            '7D' => "P7D", //7 Days
            '14D' => "P14D", //14 Days
            '30D' => "P30D", //30 Days
            '90D' => "P90D", //90 Days
            '100D' => "P100D", //100 Days
            '180D' => "P180D", //180 Days
            '1W' => "P1W", //1 Week
            '2W' => "P2W", //2 Weeks
            '3W' => "P3W", //3 Weeks
            '4W' => "P4W", //4 Weeks
            '1Mo' => "P1M", //1 Month
            '2Mo' => "P2M", //2 Months
            '3Mo' => "P3M", //3 Months
            '6Mo' => "P6M", //6 Months
            '12Mo' => "P12M", //1 Month
            '1Y' => "P1Y", //1 year
            '2Y' => "P2Y", //2 years
            '3Y' => "P3Y", //3 years
            '10Y' => "P10Y" //10 years
        ];

        if (!isset($map[$timeAspect])) {
            return null;
        }
        $validEndTime = clone($validStartTime);
        return $validEndTime->add(new \DateInterval($map[$timeAspect]));


    }


}

function detectFormat($sample){
    $supportedFormats = ['Y-m-d H:i:s','Y-m-d H:i', 'd/n/Y H:i'];

    foreach($supportedFormats as $format){
        if ( \DateTime::createFromFormat($format, $sample)){
            return $format;
        }
    }
    return null;

}