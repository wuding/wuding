<?php
namespace DbTable;

class AlimamaProductCategory extends \Astrology\Database
{
	public $db_name = 'shopping';
	public $table_name = 'alimama_product_category';
	public $primary_key = 'category_id';
	
	/**
	 * 检查条目是否存在
	 *
	 *
	 */
	public function exist($arr, $return = null)
	{
		# $this->return = 'into.sql'; 
		
		$primary_key = $this->primary_key;
		$time = time();
		
		/* 检测 */
		$where = [
			'title' => $arr['title'],
		];
		/*
		if (isset($arr['class_id'])) {
			$where['class_id'] = $arr['class_id'];
		}
		*/
		$row = $this->sel($where, '*', 'category_id');
		
		/* 插入 */
		if (!$row) {
			
			
			$data = [
				# 'status' => 0,
				'created' => $time,
			];			
			$data += $arr;
			if ('data' == $return) {
				return $data;
			}
			
			$field = array_keys($data);
			$value = array_values($data);
			return $last_id = $this->into($field, [$value]);
		}
		
		/* 更新 */
		if (isset($arr['class_id']) && $arr['class_id']) {
			$set = $arr;
			$set['updated'] = $time; # 
			$result = $this->set([$set, $row->{$primary_key}]);
			# print_r($result); exit; 
			return $result = $result[0];
		}
		
		return $row->{$this->primary_key};
	}
	
	/**
	 * 获取主类目
	 *
	 */
	public function rootIds()
	{
		$where = "`upper_id` = -1";
		$column = '*';
		$option = ['category_id', 20];
		$all = $this->_select($where, $column, $option);
		return $all;
	}
	
	/**
	 * 获取主类条目数
	 *
	 */
	public function rootNum()
	{
		$where = 'upper_id > -1';
		$column = 'upper_id,SUM(total) num';
		$option = ['upper_id', 100];
		$group = ['upper_id'];
		return $all = $this->_select($where, $column, $option, $group);
	}
}
