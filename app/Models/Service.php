<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Service extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'service_category_id',
        'title',
        'description',
        'duration',
        'price',
        'created_at',
        'updated_at'
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'chain_id',
    ];

    /**
     * Get service by id
     *
     * @param $id
     * @return mixed
     */
    public static function getById($id)
    {
        $service = self::query()->with(['servicePrice'])->find($id);
        return $service;
    }

    /**
     * Relationship for get service prices
     *
     * @return $this
     */
    public function servicePrice()
    {
        return $this->hasMany('App\Models\ServicePrice', 'service_id', 'id')->with(['level']);
    }

    public function service_category(){
        return $this->hasOne('App\Models\ServiceCategory','id','service_category_id')->select(['id','parent_id','title']);
    }
    public static function getServices($filter = null){
        $data = [];
        $response = [];
        if($filter !== null) {
            if(isset($filter['salon_id'])) {
                $salonId = $filter['salon_id'];
                if(isset($filter['employees']) && count($filter['employees'])) {
                    $query = Employee::query();
                    $select = [
                        'salon_has_employees.employee_id',
                        'salon_employee_services.price',
                        'salon_employee_services.duration',
                        'services.id',
                        'services.service_category_id',
                        'services.title',
                        'services.duration as default_duration',
                        'services.description',
                        'services.available_for_online_recording',
                        'services.only_for_online_recording'
                    ];
                    $query->select($select);
                    /*$query->selectRaw("GROUP_CONCAT('{\"id\": ',services.id,','"
                        .",'\"service_category_id\": ',services.service_category_id,','"
                        .",'\"title\": ','\"',services.title,'\"',','"
                        .",'\"duration\": ','\"',services.duration,'\"',','"
                        .",'\"description\": ','\"',services.description,'\"',','"
                        .",'\"available_for_online_recording\": ','\"',services.available_for_online_recording,'\"',','"
                        .",'\"only_for_online_recording\": ','\"',services.only_for_online_recording,'\"',"
                        ."'}') as services");*/
                    $query->selectRaw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": ',service_categories.id,',','\"parent_id\": ',COALESCE(service_categories.parent_id,'null'),',','\"title\": ','\"',service_categories.title,'\"','}')) as service_category");
                    $query->distinct();
                    $query->groupBy([
                        'salon_has_employees.employee_id',
                        'salon_employee_services.price',
                        'salon_employee_services.duration',
                        'services.id',
                        'services.service_category_id',
                        'services.title',
                        'default_duration',
                        'services.description',
                        'services.available_for_online_recording',
                        'services.only_for_online_recording'
                    ]);
                    $employees = $filter['employees'];
                    $query->join('salon_has_employees', function ($join) use($salonId,$employees) {
                        $join->on('employees.id', '=', 'salon_has_employees.employee_id')
                            ->where('salon_has_employees.salon_id', '=', $salonId)
                            ->whereIn('employee_id',$employees);
                    });
                    $query->join('salon_employee_services',function($join) {
                        $join->on('salon_has_employees.id','=','salon_employee_services.shm_id');
                    });
                    $query->join('services',function($join) {
                        $join->on('services.id','=','salon_employee_services.service_id');
                    });
                    $query->join('service_categories',function($join) {
                        $join->on('services.service_category_id','=','service_categories.id');
                    });
                    $result = $temp = $query->get();
                    $data = collect($result)->map(function($item){
                        /*$item->services = rtrim($item->services,",");
                        $item->services = \GuzzleHttp\json_decode("[".$item->services."]");*/
                        $item->service_category = \GuzzleHttp\json_decode($item->service_category);
                        return $item;
                    });
                    $employees = [];
                    foreach ($data as $item) {
                        if(!array_key_exists($item->employee_id,$employees)) {
                            $employees[$item->employee_id] = [
                                "employee_id" => $item->employee_id,
                                "service_groups" => []
                            ];
                        }
                        if(!array_key_exists($item->service_category->id,$employees[$item->employee_id]['service_groups'])){
                            $employees[$item->employee_id]['service_groups'][$item->service_category->id] = $item->service_category;
                            $employees[$item->employee_id]['service_groups'][$item->service_category->id]->services = [];
                        }
                        $temp = clone $item;
                        unset($temp->service_category);
                        unset($temp->employee_id);
                        array_push($employees[$item->employee_id]['service_groups'][$item->service_category->id]->services,$temp);
                    };
                    $employees = array_values($employees);
                    foreach ($employees as &$e) {
                        $e['service_groups'] = array_values($e['service_groups']);
                    }
                    $response['employees'] = $employees;
                }
                else{
                    $query = self::query();
                    $select = [
                        'services.id',
                        'services.service_category_id',
                        'services.title',
                        'services.duration as default_duration',
                        'services.description',
                        'services.available_for_online_recording',
                        'services.only_for_online_recording',
                    ];
                    $query->select($select);
                    $query->distinct();
                    $query->join('salon_has_services',function($join) use($salonId) {
                        $join->on('services.id','=','salon_has_services.service_id')
                            ->where('salon_has_services.salon_id','=',$salonId);
                    });
                    $query->with('service_category');
                    $data = $temp = $query->get();
                    $services = [];
                    foreach ($data as $item) {
//                        dd($item->service_category->id);
                        if(!array_key_exists($item->service_category->id, $services)) {
                            $services[$item->service_category->id] = $item->service_category->getAttributes();
                            $services[$item->service_category->id]['services']= [];
                        }
                        $temp = clone $item;
                        unset($temp->service_category);
                        unset($temp->employee_id);
                        $services[$item->service_category->id]['services'][] = $temp;
                    };
                    $services = array_values($services);
                    $response = ["service_groups"=>$services];
                }
            }
        }

        return $response;
    }
}
