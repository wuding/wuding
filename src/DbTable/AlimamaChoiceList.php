<?php
namespace DbTable;

class AlimamaChoiceList extends \Astrology\Database
{
	public $db_name = 'shopping';
	public $table_name = 'alimama_choice_list';
	public $primary_key = 'list_id';
	
	/**
	 * 获取最后更新时间
	 */
	public function getLastUpdated()
	{
		$where = 'updated > 0';
		$row = $this->sel($where, 'updated', 'updated DESC');
		if ($row) {
			return $row->updated;
		}
		return false;
	}
	
	/**
	 * 检测条目是否存在
	 *
	 */
	public function exist($arr, $item_id = null)
	{
		$primary_key = $this->primary_key;
		$time = time();
		$where = [
			'excel_id' => $arr['excel_id'],
		];
		if ($item_id) {
			$where['item_id'] = $item_id;
		}
		$row = $this->sel($where, 'list_id,excel_id,item_id,category_id,title,pic,site,sold,cost,price,save,start,end');
		# print_r([$row, $where]);exit;
		if (!$row || !isset($row->list_id)) {
			$data = [
				'created' => $time,
				'updated' => $time,
			];
			$data += $arr;
			unset($data['url'], $data['link']);
			$field = array_keys($data);
			$value = array_values($data);
			# $this->return = 'into.sql';
			return $last_id = $this->into($field, [$value]);
		}
		
		/* 比较 */
		$diff = $this->array_diff_kv((array) $row, $arr);
		$data = [];
		foreach ($diff as $key => $value) {
			$data[$key] = $value[1];
		}
		
		# print_r([$data, $row, $arr]); 
		
		/* 更新 */
		if ($data) {
			# var_dump($diff);exit; 
			$data['updated'] = $time;
			$result = $this->set([$data, $row->{$primary_key}]);
			$result = $result[0];
			return $result;
		}
		return $row->{$primary_key};
	}
	
	/**
	 * 分类条目数
	 *
	 */
	public function categoryNum()
	{
		$where = [];
		$column = 'category_id,COUNT(0) AS num';
		$option = ['category_id', 200];
		return $all = $this->_select($where, $column, $option, ['category_id']);
		print_r($all);
	}
}
