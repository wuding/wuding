<?php
/**
 * 阿里妈妈
 *
 *
 */
namespace Plugin\Robot;

use DbTable\AlimamaChoiceExcel;
use DbTable\AlimamaChoiceList;
use DbTable\AlimamaProductCategory;

class Alimama extends \Plugin\Robot
{
	#! public $site_id = null;
	public $api_host = 'http://lan.urlnk.com';
	
	/**
	 * 自定义初始化 
	 */
	public function _init()
	{
		$this->bill = $bill = isset($_GET['bill']) ? (int) $_GET['bill'] : 1;		
		$this->cache_dir = $cache_dir = CACHE_ROOT . '/http/www.alimama.com';
		$this->api_host = 'http://' . $_SERVER['HTTP_HOST'];
		
		$this->paths = [
			$cache_dir . "/$bill.csv",
            $cache_dir . "/$bill.xls",
			"",
			$cache_dir . "/$bill.xls",
			$cache_dir . "/4/%1.json",
        ];
		
		$this->urls = [
			"",
			"https://pub.alimama.com/coupon/qq/export.json?adzoneId=126228652&siteId=35532130",
			"",
			"https://pub.alimama.com/operator/tollbox/excelDownload.json?excelId=JUPINTUAN_LIST&adzoneId=126228652&siteId=35532130",
			"https://pub.alimama.com/items/channel/qqhd.json?channel=qqhd&toPage=%1&sortType=13&dpyhq=1&perPageSize=50&shopTag=dpyhq&t=1531379311587&_tb_token_=&pvid=",
			'reurl' => 'http://www.jijizy.com/vod/?%1.html',			
        ];
	}
	
	/*
	------------------------------------------------
	| 列表
	------------------------------------------------
	*/
	
	/**
	 * 解析表格.csv
	 */
	public function parseExcel()
	{
		$Excel = new AlimamaChoiceExcel;
		
		$offset = $this->attr['limit'] - 1;
		$bill = $this->bill;
		$key = 0;
		$path = $this->getProp($key, 'paths');
		# $path = $this->cache_dir . "/$bill.csv";
		
		$count = 500;
		$max = $offset + $count;
		$max_url = $this->api_host . '/robot/alimama/optimize/list?debug&type=json';
		$juhuasuan_url = $this->api_host . '/robot/alimama/parse/excel?debug&type=json&bill=3';
		
		// 清单列名
		$keys = [
			[],
			[
				'item', 
				'name', 
				'pic', 
				'url', 
				'class', 
				'taobaoke', 
				'price', 
				'sale', 
				'ratio', 
				'commission', 
				'wangwang', 
				'seller', 
				'shop', 
				'platform', 
				'coupon', 
				'total', 
				'remain', 
				'denomination', 
				'start', 
				'end', 
				'link', 
				'promotion'
			],
			[
				'class', 
				'shop', 
				'platform', 
				'item', 
				'name', 
				'url', 
				'pic', 
				'price', 
				'ratio', 
				'begin', 
				'denomination', 
				'cost',
				'total', 
				'remain', 
				'start', 
				'end', 
				'promotion', 
				'note'
			],
			[
				'item', 
				'name', 
				'price', 
				'cost', 
				'group', 
				'pic', 
				'start', 
				'end', 
				'total', 
				'sale', 
				'remain', 
				'taobaoke', 
				'url', 
				'ratio', 
				'commission', 
				'coupon', 
				'class'
			],
			
		];		
		$key = isset($keys[$bill]) ? $keys[$bill] : [];
		$num = count($key);
		# print_r($key);
		
		/* 解析 CSV 文件 */
		$row = 0;
		$result = [];
		if (($handle = fopen($path, "r")) !== false) {
			while (($data = fgetcsv($handle, 0, ",")) !== false) {
				
				if ($offset < $row && $row < $max) {
					/* 列数检测 */
					$num_data = count($data);
					if ($num_data != $num) {
						print_r([__METHOD__, __LINE__, __FILE__, $key, $data]);exit; #
					}
				
					/* 列名转键名 */					
					$arr = ['choice_id' => $bill];
					for ($c = 0; $c < $num; $c++) {
						$keyname = isset($key[$c]) ? $key[$c] : $c;
						$datum = null;
						if (isset($data[$c])) {
							$datum =$data[$c];
						} else {
							print_r($data);exit; #
						}						
						$datum = mb_convert_encoding($datum, 'utf-8', 'gbk');# 
						$arr[$keyname] = $datum;
					}
					$arr['cost'] = $arr['price'];

					/* 匹配优惠券信息 */
					if (isset($arr['denomination'])) {
						if (preg_match("/满(\d+)元减(\d+)元/", $arr['denomination'], $matches)) {
							$arr['full'] = $matches[1];
							$arr['discount'] = $matches[2];
							if ($arr['full'] <= $arr['price']) {
								$arr['cost'] = $arr['price'] - $arr['discount'];
							}
							
						} elseif (preg_match("/(\d+)元无条件券/", $arr['denomination'], $matches)) {
							$arr['discount'] = $matches[1];
							$arr['cost'] = $arr['price'] - $arr['discount'];
						}
					}
					
					/* 后续修复处理 */
					if (1 == $bill) {
						
						
					} elseif (2 == $bill) {
						$arr['sale'] = $arr['total'] - $arr['remain'];
						$url = parse_url($arr['promotion'], PHP_URL_QUERY);
						parse_str($url, $query);
						# print_r($query);exit;
						$arr['coupon'] = isset($query['e']) ? $query['e'] : $arr['start'] .'_'. $arr['end'];
						
					} elseif (3 == $bill) {
						$arr['discount'] = $arr['price'] - $arr['cost'];
					}
					
					// 开始结束时间
					if ($arr['start'] && !preg_match("/:/", $arr['start'])) {
						$arr['start'] .= ' 00:00:00';
					}
					if ($arr['end'] && !preg_match("/:/", $arr['end'])) {
						$arr['end'] .= ' 23:59:59';
					}
					# print_r($arr);exit;

					/* 检查条目 */
					# $Excel->return = 'update.status';
					$check = $Excel->exist($arr); #, 'data'
					# print_r($check);exit;
					$check = is_numeric($check) ? $check : $check; #'update'
					$result[$row] = [$arr['item'], $check];
				} elseif ($row == $max) {
					$max_url = "$this->api_host/robot/alimama/parse/excel?debug&type=json&bill=$bill&limit=$max";
					break;
				}
				$row++;
			}
			fclose($handle);
		}
		
		/* 接力任务 */
		$code = 0;
		$msg = '';		
		switch ($bill) {
			case 3:
				$code = 1;
				$msg = $max_url;
				break;
			default:
				if (count($result)) {
					$msg = $max_url ? : $juhuasuan_url;
				} else {
					$msg = $juhuasuan_url;
				}
		}
		
		/* 返回数据 */
		return [
			'code' => $code,
			'msg' => $msg,
			'result' => $result,
			'pageCount' => 1,
        ];
	}
	
	/**
	 * 逆向更新
	 *
	 */
	public function updateList()
	{
		$page = $this->attr['page'];
		$limit = 10;
		$offset =  $page * $limit - $limit;
		
		$Excel = new AlimamaChoiceExcel;
		$Category = new AlimamaProductCategory;
		$List = new AlimamaChoiceList;
		
		$where = "`pic` = 'http://img.alicdn.com/bao/uploaded/i1/2863563282/TB2lNkaFAOWBuNjSsppXXXPgpXa_!!2863563282-0-item_pic.jpg'";
		$where = [];
		$column = '*';
		$option = ['list_id', "$offset,$limit"];
		$join = null;
		$all = $List->_select($where, $column, $option, null, $join);
		$count = $List->count($where);
		$pageCount = ceil($count / $limit);
		
		$result = [];
		$time = time();
		foreach ($all as $key => $row) {
			$arr = $Excel->sel("excel_id = $row->excel_id", '*');			
			$url = $arr->taobaoke;
			$link = $arr->promotion;
			$price = $arr->cost;
			if (0 < $arr->group) {
				$link = $arr->url;
			}			
			if (0 > $price) {
				$price = $arr->price;
			}
			$ar = [
				'title' => $arr->name,
				'pic' => $arr->pic,
				'url' => $url,
				'link' => $link,
				'sold' => $arr->sale,
				'cost' => $arr->price,
				'price' => $price,
				'save' => $arr->discount,
				'start' => $arr->start,
				'end' => $arr->end,
			];
			
			/* 比较 */
			$diff = $List->array_diff_kv($ar, (array) $row);			
			$data = [];
			foreach ($diff as $k => $value) {
				$data[$k] = $value[0];
			}
			/* 更新 */
			if ($data) {
				$data['updated'] = $time;
				$res = $List->set([$data, $row->list_id]);
				$result[] = $res[0];
			}
		}
		
		/* 接力任务 */		
		$msg = '';
		$code = 0;
		if ($page < $pageCount) {
			$page++;
			$msg = "http://lan.urlnk.com/robot/alimama/update/list?debug&type=json&page=$page";
		} else {
			$code = 1;
		}
		
		/* 返回数据 */
		$result = array(
            'result' => $result,
			'pageCount' => $pageCount,
			'msg' => $msg,
			'code' => $code,
        );		
		return $result;
	}
	
	/**
	 * 优化列表
	 *
	 */
	public function optimizeList()
	{
		$page = $this->attr['page'];
		$offset =  $page * 10 - 10;
		
		$Excel = new AlimamaChoiceExcel;
		$Category = new AlimamaProductCategory;
		$List = new AlimamaChoiceList;
		
		$where = 'modified > 1535385600 OR created > 1535385600';
		# $where = '';
		# $column = 'alimama_choice_excel.*, B.category_id';
		$column = '*';
		$option = ['excel_id DESC', "$offset,10"];
		# $join = 'LEFT JOIN com_urlnk.alimama_product_category B ON B.title = alimama_choice_excel.class';
		$join = null;
		# print_r([$where, $column, $option]);
		$all = $Excel->_select($where, $column, $option, null, $join);
		# print_r($all);exit;
		$count = $Excel->count($where);
		$pageCount = ceil($count / 10);
		
		$categories = [];
		$cat = [];
		foreach ($all as $key => $row) {
			if ($row->class) {
				$cat[] = $row->class;
			}
		}
		if ($cat) {
			$cat = implode("', '", $cat);
			$where = "title IN('$cat')";
			$cats = $Category->_select($where, 'category_id,title');
			foreach ($cats as $c) {
				$categories[$c->title] = $c->category_id;
			}
		}
		# print_r($categories);
		
		$result = [];
		foreach ($all as $key => $row) {
			$url = $row->taobaoke;
			$link = $row->promotion;
			$price = $row->cost;
			$site = 1;
			$category_id = isset($categories[$row->class]) ? $categories[$row->class] : 0;
			if (0 < $row->group) {
				$link = $row->url;
				$site = 3;
			} elseif ('天猫' == $row->platform) {
				$site = 2;
			}
			
			if (0 > $price) {
				$price = $row->price;
			}
			$arr = [
				'excel_id' => $row->excel_id,
				'item_id' => $row->item,
				'category_id' => $category_id,
				'title' => $row->name,
				'pic' => $row->pic,
				'url' => $url,
				'link' => $link,
				'site' => $site,
				'sold' => $row->sale,
				'cost' => $row->price,
				'price' => $price,
				'save' => $row->discount,
				'start' => $row->start,
				'end' => $row->end,
			];
			$result[] = $List->exist($arr);
		}
		unset($all);
		# print_r($all);
		# print_r($result);
		
		/* 接力任务 */		
		$msg = '';
		$code = 0;
		if ($page < $pageCount) {
			$page++;
			$msg = "http://lan.urlnk.com/robot/alimama/optimize/list?debug&type=json&page=$page";
		} else {
			$code = 1;
		}
		
		/* 返回数据 */
		$result = array(
            'result' => $result,
			'pageCount' => $pageCount,
			'msg' => $msg,
			'code' => $code,
        );		
		return $result;
	}
	
	/*
	------------------------------------------------
	| 分类
	------------------------------------------------
	*/
	
	/**
	 * 解析分类
	 *
	 */
	public function parseCategory()
	{
		$Excel = new AlimamaChoiceExcel;
		$Category = new AlimamaProductCategory;
		# $all = $Category->rootIds(); return $all; 
		
		// 聚划算分类
		$classes = $Excel->classIds();
		$arr = [];
		foreach ($classes as $class) {
			$row = [
				'title' => $class->class,
				'class_id' => $class->coupon,
			];
			$arr[]= $Category->exist($row);
		}
		
		// 其他分类
		$classes = $Excel->classIds('=');
		# $arr = [];
		foreach ($classes as $class) {
			$row = [
				'title' => $class->class,
			];
			$arr[]= $Category->exist($row);
		}
		return ['result' =>  $arr, 'pageCount' => 1];
		print_r([$arr, $classes]);
	}
	
	/**
	 * 优化分类
	 *
	 */
	public function optimizeCategory()
	{
		$List = new AlimamaChoiceList;
		$Category = new AlimamaProductCategory;
		$time = time();
		$update = $Category->update(['total' => 0, 'updated' => $time]);
		
		/* 更新子类 */
		$catNum = $List->categoryNum();
		$result = [];
		foreach ($catNum as $c) {
			$result[$c->category_id] = $Category->update(['total' => $c->num, 'updated' => $time], ['category_id' => $c->category_id]);
		}
		
		
		/* 更新主类 */
		$cat = $Category->rootNum();
		$res = [];
		foreach ($cat as $r) {
			$res[$r->upper_id] = $Category->update(['total' => $r->num, 'updated' => $time], $r->upper_id);
		}
		
		/* 返回数据 */
		$result = array(
            'result' => $result + $res,
			'pageCount' => 1,
			'msg' => '',
			'code' => 0,
        );		
		return $result;
	}
}
