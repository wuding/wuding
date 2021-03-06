<?php
namespace DbTable;

class RentingSiteArea extends \Astrology\Database
{
    public $db_name = 'classified';
    public $table_name = 'renting_site_area';
    public $primary_key = 'area_id';
    
    /**
     * 省
     *
     */
    public function findProv($name, $site_id, $column = '*')
    {
        $where = [
            'title' => $name,
            'site_id' => $site_id,
        ];
        return $row = $this->sel($where, $column);
    }
    
    public function provinceExists($name, $site_id)
    {
        $row = $this->findProv($name, $site_id, 'area_id');
        
        if (!$row) {
            $time = time();
            $data = [
                'title' => $name,
                'site_id' => $site_id,
                'type' => 1,
                'created' => $time,
                'updated' => $time,
            ];
            return $this->insert($data);
        }
        return $row->area_id;
    }
    
    /**
     * 市
     */
    public function cityExists($where, $set = [], $column = '*')
    {
        return $this->rowExists($where, $set, $column, 2);
    }
    
    #! 根据条件查找所有
    public function fetchAll($where = [], $column = '*', $order = 'm3u8_id DESC')
    {
        $option = [$order, 30];
        return $all = $this->_select($where, $column, $option);
    }

    public function districtExists($where, $set = [], $column = '*')
    {
        return $this->rowExists($where, $set, $column, 3);
    }

    public function townExists($where, $set = [], $column = '*')
    {
        return $this->rowExists($where, $set, $column, 4);
    }

    public function complexExists($where, $set = [], $column = '*')
    {
        return $this->rowExists($where, $set, $column, 6);
    }

    public function rowExists($where, $set = [], $column = '*', $type = 4)
    {
        $where['type'] = $type;
        $row = $this->sel($where, $column);
        
        if (!$row) {
            $time = time();
            $data = [
                'created' => $time,
                'updated' => $time,
            ];
            $data += $where + $set;
            return $this->insert($data);
        }
        return $row->area_id;
    }

    public function area_id($id, $data = [], $level = 3)
    {
        $type = ['country', 'province', 'city', 'district', 'town', 'village', 'complex'];
        $row = $this->find($id, 'area_id, upper_id, title, type');
        if ($row) {
            $key = $type[$row->type];
            $data[$key] = $row;
            # print_r($data);
            if ($level < $row->type) {
                if ($row->upper_id) {
                    return $this->area_id($row->upper_id, $data, $level);
                }
                
            }
        }
        return $data;
    }

    /**
     * 通过缩写获取城市 ID
     *
     * @param      string  $abbr   城市缩写
     *
     * @return     integer         返回城市 ID
     */
    public function city_id($abbr)
    {
        $arr = [
            'mas' => 53,
        ];

        return isset($arr[$abbr]) ? $arr[$abbr] : 0;
    }
}
