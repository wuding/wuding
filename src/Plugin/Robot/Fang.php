<?php
/**
 * 房天下
 *
 * www.fang.com
 */
namespace Plugin\Robot;

use Astrology\Extension\Mbstring;
use Astrology\Extension\DOM;
use DbTable\RentingSiteArea;
use DbTable\RentingSiteDetail;


class Fang extends \Plugin\Robot
{
    public $enable_relay = true;
    public $overwrite = false;
    public $api_host = 'http://lan.urlnk.com';
    public $site_id = 1;
    public $city_abbr = 'mas';
    public $city_id = -1;


    /**
     * 自定义初始化
     * 
     * @return [type] [description]
     */
    public function _init()
    {
        $cache_dir = CACHE_ROOT . '/http/zu.fang.com';
        $this->api_host = 'http://' . $_SERVER['HTTP_HOST'];
        $Area = new RentingSiteArea;

        $this->paths = [
            $cache_dir . '/cities.aspx.gz', //城市列表
            $cache_dir . '/%1/house/i3%2.gz', //出租列表pc 1城市 2页码
            $cache_dir . '/m/zf/%1/index.html', //出租首页 1城市
            $cache_dir . '/m/zf/%1/%3/%2.html', //出租详情 1城市 2ID 3类型
            $cache_dir . '/m/zf/%1/%2.html', //出租列表 1城市 2页码
        ];

        $this->urls = [
            'http://zu.fang.com/cities.aspx',
            'http://%1.zu.fang.com/house/i3%2/',
            'https://m.fang.com/zf/%1/',
            'https://m.fang.com/zf/%1/%3_%2.html',
            'https://m.fang.com/zf/?renttype=cz&c=zf&a=ajaxGetList&city=%1&page=%2',
        ];

        $this->relay_urls = [
            'parse/city' => "$this->api_host/robot/fang/parse/city?debug&type=json",
            'download/zf' => "$this->api_host/robot/fang/download/zf?debug&type=json",
            'download/list' => "$this->api_host/robot/fang/download/list?debug&type=json",
        ];

        // 城市
        $ct = [
            'site_id' => $this->site_id,
            'abbr' => $this->city_abbr,
        ];
        $set = [];
        $this->city_id = $Area->cityExists($ct, $set, 'area_id');
    }

    /*
     +------------------------------
     | 列表
     +------------------------------
     */

    /**
     * 下载列表首页
     * 
     * @return array api数据
     */
    public function downloadZf()
    {
        /* 下载 */
        $size = $this->putFileCurl([], 2, 'mas');
        if (!$size) {
            return [
                'code' => 1, 
                'msg' => 'download error', 
                'info' => [__FILE__, __LINE__],
            ];
        }
        # print_r($size);exit;
        
        /* 解析 */
        $data = $this->getPathContents(2, 'mas');
        $doc = $this->parse_dom($data, null, null, 'gbk', ['/charset=gbk/', 'charset=utf-8'])[0]; # echo exit;
        
        // 页数
        $input = $doc->getElementsByTagName('input');
        $length = $input->length;
        $arr = [];
        for ($i = 0; $i < $length; $i++) {
            $node = $input->item($i);
            $data_id = $node->getAttribute('data-id');
            if (in_array($data_id, ['total', 'pagesize'])) {
                $arr[$data_id] = $node->getAttribute('value');
                if ('pagesize' == $data_id) {
                    break;
                }
            }
        }
        # print_r($arr);exit; 
        $obj = (object) $arr;
        $limit = 16;
        $_SESSION['next_page'] = $next_page = $obj->pagesize / $limit + 1;
        $_SESSION['total_page'] = $total_page = ceil($obj->total / $limit);

        /* 列表 */
        $doc = $doc->getElementById('content');     
        $list = $this->check_list($doc);
        # print_r($list);exit; 
        
        $msg = $this->enable_relay ? $this->relay_urls['download/list'] . "&page=$next_page" : '';
        return [
            'msg' => $msg,
            'result' => $list,
            'pageCount' => 1,
        ];
    }

    /**
     * 更新不需要改状态的
     * @return array api数据
     */
    public function updateStatus()
    {
        $Detail = new RentingSiteDetail;
        # $Detail ->return = 'update.sql';
        $update = $Detail->update(['status' => 1], ['cache_set' => "NOT LIKE '%\"status\":%'"]);
        $msg = '';
        return [
            'msg' => $msg,
            'result' => $update,
            'pageCount' => 1,
        ];
    }

    /**
     * 更新状态
     * @return array api数据
     */
    public function optimizeStatus(){
        $page = $this->attr['page'];
        $limit = 10;
        $Detail = new RentingSiteDetail;
        $where = "cache_set LIKE '%\"status\":%'";       
        $all = $Detail->fetchAll($where, 'detail_id,cache_set', 'detail_id', $page, $limit);
        $pageCount = $Detail->pageCount($where, $limit);

        $result = [];
        foreach ($all as $row) {
            $obj = json_decode($row->cache_set);
            $result[] = $Detail->update($obj->status, $row->detail_id);
        }

        $msg = '';
        return [
            'msg' => $msg,
            'result' => $result,
            'pageCount' => $pageCount,
        ];
    }

    /**
     * 清除非正常状态的缓存队列
     * @return array api数据
     */
    public function updateCache(){
        $page = $this->attr['page'];
        $limit = 10;
        $Detail = new RentingSiteDetail;
        $where = "status != 1";       
        $all = $Detail->fetchAll($where, 'detail_id,cache_set', 'detail_id', $page, $limit);
        $pageCount = $Detail->pageCount($where, $limit);

        $result = [];
        foreach ($all as $row) {
            $obj = json_decode($row->cache_set);
            unset($obj->status);
            # $arr = (array) $obj;
            $json = json_encode($obj);
            if ($json) {
                $json = '{}' == $json ? '' : $json;
                $result[] = $Detail->update(['cache_set' => $json], $row->detail_id);
            } else {
                $result[] = $json;
            }
            # print_r([$arr, $json, $result]);exit;
            
        }

        $msg = '';
        return [
            'msg' => $msg,
            'result' => $result,
            'pageCount' => $pageCount,
        ];
    }

    /**
     * 下载出租列表
     * @return array api数据
     */
    public function downloadList()
    {
        /* 下载 */
        $size = $this->putFileCurl([], 4, 'mas', $this->attr['page']);
        if (!$size) {
            return [
                'code' => 1, 
                'msg' => 'download error', 
                'info' => [__FILE__, __LINE__],
            ];
        }

        /* 检测 */
        $data = $this->getPathContents(4, 'mas', $this->attr['page']);
        $doc = $this->parse_dom($data, 'utf-8')[0]; # echo exit;
        $list = $this->check_list($doc);
        #print_r($list);exit;
        #
        $msg = '';

        return [
            'msg' => $msg,
            'result' => $list,
            'pageCount' => $_SESSION['total_page'],
        ];
    }

    /**
     * 解析 DOM
     * @param  string $str           html
     * @param  string $charset       html字符集
     * @param  string $id            元素id
     * @param  string $from_encoding 源编码
     * @param  array  $replace       html替换
     * @return object                dom元素
     */
    public function parse_dom($str = null, $charset = null, $id = null, $from_encoding = null, $replace = [])
    {
        if ($from_encoding) {
            $mb = new Mbstring($str, $from_encoding);
            if ($replace) {
                $str = $mb->preg_replace($replace[0], $replace[1]);
            } else {
                $str = $mb->str;
            }
        }

        $dom = new DOM($str, $charset);
        $doc = $dom->doc;
        if ($id) {
            $doc = $doc->getElementById($id);
        }
        return [$doc, $str];
    }

    /**
     * 检测出租详情数据
     * @param  object $doc dom对象
     * @param  object $row 数据条目对象
     * @return array       检测结果
     */
    public function check_detail($doc, $row = null)
    {
        $Detail = new RentingSiteDetail;
        $html = $doc[1];
        $doc = $doc[0];
        $dom = new DOM();
        $slider = $doc->getElementById('slider');
        $section = $doc->getElementsByTagName('section');
        $body = $doc->getElementsByTagName('body');
        
        // 404页面
        if ($body->length) {
            $class = $body[0]->getAttribute('class');
            if ('box404' == $class) {
                # $update = $Detail->update(['cache_set' => 'status=404'], $row->detail_id);
                $set = [
                    'status' => 'status=404',
                ];
                $update = $Detail->fieldMessageQueue($row->detail_id, $set, 'cache_set');
                return [404, $update];
            }
        } else {
            echo $html;
            print_r($doc);
        }
       
        $data = [
            'site_id' => $this->site_id,
            'item_id' => $row->item_id,
            'type' => $row->type,
        ];
        // 幻灯片
        if ($slider) {
            $slides = [];
            $img = $slider->getElementsByTagName('img');
            $len = $img->length;
            for ($i = 0; $i < $len; $i++) {
                $node = $img->item($i);
                $src = $node->getAttribute('src');
                $slides[] = $src;
            }
            $data['slides'] = implode(',', $slides);
        }

        for ($i = 0; $i < $section->length; $i++) {
            $node = $section->item($i);
            $class = $node->getAttribute('class');
            switch ($class) {
                case 'xqCaption mb8':
                    $data = $this->xqCaption($node, $data);
                    break;
                case 'xqBox mb8':
                    $data = $this->xqBox($node, $data);
                    break;
                case 'mBox':
                    $data = $this->xqDescription($node, $data);
                    break;
            }
            # $data[] = $class;
            if ('mBox' == $class) {
                break;
            }
        }

        if (array_key_exists(0, $data)) {
            return $data;
        }
        return $Detail->exist($data);
    }

    /**
     * 获取刷新时间和地点区域
     * @param  object $node dom节点
     * @param  array  $data 原数据
     * @return array        返回数据
     */
    public function xqCaption($node, $data = [])
    {
        $p = $node->getElementsByTagName('p');
        $arr = [];
        for ($i = 0; $i < $p->length; $i++) {
            if (1 < $i) {
                break;
            }

            $nd = $p->item($i);
            switch ($i) {
                case 0:
                    $a = $nd->getElementsByTagName('a');
                    $data = $this->xqCrumbs($a, $data);
                    break;
                case 1:
                    $str = preg_replace('/刷新时间：\s+/', '', trim($nd->nodeValue));
                    $time = strtotime($str);
                    $data['refresh_time'] = date('Y-m-d H:i:s', $time);
                    break;
            }
        }
        return $data;
    }

    /**
     * 获取地点区域
     * @param  object $node_list    a节点列表
     * @param  array  $data         原数据
     * @return array                返回数据
     */
    public function xqCrumbs($node_list, $data = [])
    {
        $Area = new RentingSiteArea;
        $arr = [
            'district' => '',
            'town' => '',
            'complex' => '',
        ];
        $links = [];
        for ($i = 0; $i < $node_list->length; $i++) {
            $node = $node_list->item($i);
            $href = $node->getAttribute('href');
            $links[] = [$node->nodeValue, $href];
            if (preg_match('/\/\/m\.fang\.com\/zf\/([a-z]+)_([a-z0-9_]+)\//', $href, $matches)) {
                $id = trim($matches[2]);
                $key = 'district';
                if (preg_match('/^[a-z]+(\d+)$/', $id, $match)) {
                    $key = 'complex';
                    $id = $match[1];
                } elseif (preg_match('/_(\d+)$/', $id, $match)) {
                    $key = 'town';
                    $id = $match[1];
                } elseif (!preg_match('/^(\d+)$/', $id, $match)) {
                    $key = $i;
                }

                if (!is_numeric($key)) {
                    $arr[$key] = [$node->nodeValue, $id];
                }
                # print_r([$links, $arr]);exit; 
            } elseif (preg_match('/^javascript/', $href, $match)) {
                $key = 'complex';
                $arr[$key] = [$node->nodeValue, -1];
                # print_r([$links, $arr]);exit; 
            }
            
        }

        

        $district = $arr['district'];
        $town = $arr['town'];
        $complex = $arr['complex'];

        // 区县
        $district_id = $district_origin = -1;
        if ($district) {
            $district_origin = $district[1];
            $where = [
                'upper_id' => $this->city_id,
                'site_id' => $this->site_id,
                'note' => $this->city_abbr,
                'title' => $district[0],
                'origin_id' => $district_origin,
            ];
            $arr['district']['id'] = $district_id = $Area->districtExists($where);
            $data['district_name'] = $district[0];
        }
        

        // 乡镇
        $town_id = $town_origin = -1;
        if ($town) {
            $town_origin = $town[1];
            $where = [
                'upper_id' => $district_id,
                'site_id' => $this->site_id,
                'note' => $district_origin,
                'title' => $town[0],
                'origin_id' => $town_origin,
            ];
            $data['area_id'] = $town_id = $Area->townExists($where);
        }

        // 小区
        if ($complex) {
            $where = [
                'upper_id' => $town_id,
                'site_id' => $this->site_id,
                'note' => $town_origin,
                'title' => $complex[0],
                'origin_id' => $complex[1],
            ];
            $data['complex_id'] = $Area->complexExists($where);
            $data['complex_name'] = $complex[0];
        }

        if (!$complex || !$town || !$district) {
            # print_r([$links, $arr]);print_r($data);exit;
            
            
        }
        return $data;
    }

    /**
     * 获取租金价格和配套设施
     * @param  object $section section节点
     * @param  array  $data    原数据
     * @return array           返回数据
     */
    public function xqBox($section, $data = [])
    {
        $div = $section->getElementsByTagName('div');
        $arr = [];
        for ($i = 0; $i < $div->length; $i++) {
            $node = $div->item($i);
            $class = $node->getAttribute('class');
            switch ($class) {
                case 'price-box mt20':
                    $data = $this->xqPrice($node, $data);
                    break;
                case 'bb pdY10':
                    $data = $this->xqTable($node, $data);
                    break;
                case 'ptss-zf pdY14':
                    $data['facilities'] = $this->xqFacility($node);
                    goto a;
                    break;
            }
            # $arr[] = $class;
        }
        a:
        # $data[] = $arr;
        return $data;
    }

    /**
     * 获取租金价格和支付方式 
     * @param  object $node    div节点
     * @param  array  $data    原数据
     * @return array           返回数据
     */
    public function xqPrice($node, $data = [])
    {
        $span = $node->getElementsByTagName('span');
        $arr = [];
        for ($i = 0; $i < $span->length; $i++) {
            $nd = $span->item($i);
            $key = 'rental_price';
            $value = $nd->nodeValue;
            if (1 == $i) {
                $key = 'pay';
                $value = preg_replace('/（|）/', '', $value);
            } elseif (1 < $i) {
                break;
            }
            $data[$key] = $value;
        }
        return $data;
    }

    /**
     * 获取详情
     * @param  object $node    div节点
     * @param  array  $data    原数据
     * @return array           返回数据
     */
    public function xqTable($node, $data = [])
    {
        $span = $node->getElementsByTagName('li');
        $arr = [
            '租赁方式' => 'rental_method',
            '户型' => 'house_type',
            '建筑面积' => 'building_area',
            '楼层' => 'floor',
            '朝向' => 'orientation',
            '装修' => 'decoration',
            '入住时间' => 'check_in_time',
        ];
        for ($i = 0; $i < $span->length; $i++) {
            $nd = $span->item($i);
            $explode = explode('：', trim($nd->nodeValue));
            $item = $explode[0];
            $value = $explode[1];
            if (array_key_exists($item, $arr)) {
                $key = $arr[$item];
                if ('floor' == $key) {
                    $exp = explode('/', $value);
                    $count = count($exp);
                    if (1 < $count) {
                        $data['total_floor'] = $exp[1];
                        $value = $exp[0];
                    } elseif (2 < $count) {
                        $data[] = $explode;
                    }
                }
                $data[$key] = $value;
            } else {
                $data[] = $explode;
            }
            
        }
        return $data;
    }

    /**
     * 获取配套设施
     * @param  object $node    div节点
     * @return string          返回数据
     */
    public function xqFacility($node)
    {
        $span = $node->getElementsByTagName('span');
        $arr = [];
        for ($i = 0; $i < $span->length; $i++) {
            $nd = $span->item($i);
            $class = $nd->getAttribute('class');
            if ('on' == $class) {
                $arr[] = $nd->nodeValue;
            }            
        }
        $str = implode(',', $arr);
        return $str;
    }

    /**
     * 获取房源描述
     * @param  object $node dom节点
     * @param  array  $data 原数据
     * @return array        返回数据
     */
    public function xqDescription($node, $data = [])
    {
        $div = $node->getElementsByTagName('div');
        $arr = [];
        for ($i = 0; $i < $div->length; $i++) {
            $nd = $div->item($i);
            $class = $nd->getAttribute('class');
            switch ($class) {
                case 'fymsList pdX20':
                    $data = $this->xqFymsList($nd, $data);
                    break;
            }
            # $arr[] = $class;
            if ('fymsList pdX20' == $class) {
                break;
            }
        }

        # $data[] = $arr;
        return $data;
    }

    /**
     * 获取房源描述列表
     * @param  object $node dom节点
     * @param  array  $data 原数据
     * @return array        返回数据
     */
    public function xqFymsList($node, $data = [])
    {
        $span = $node->getElementsByTagName('li');
        $arr = [];
        $keys = [
            '房源亮点' => 'description',
        ];
        for ($i = 0; $i < $span->length; $i++) {
            $nd = $span->item($i);
            $h3 = $nd->getElementsByTagName('h3')[0];
            $p = $nd->getElementsByTagName('p')[0];
            $key = trim($h3->nodeValue);
            if (array_key_exists($key, $keys)) {
                $column = $keys[$key];
                $data[$column] = trim($p->nodeValue);
            } else {
                $data[] = $key;
            }
        }
        return $data;
    }

    

    /**
     * 检测出租列表数据
     * @param  object $doc dom
     * @return array       检测结果集
     */
    public function check_list($doc)
    {
        $detail = new RentingSiteDetail;
        $dom = new DOM();
        $li = $doc->getElementsByTagName('li');
        $len = $li->length;
        $list = [];
        for ($j = 0; $j < $len; $j++) {
            $nd = $li->item($j);            
            $data_bg = $nd->getAttribute('data-bg');
            if ($data_bg) {
                $h3 = $nd->getElementsByTagName('h3');              
                $bg = json_decode($data_bg);
                $img = $nd->getElementsByTagName('img')->item(0)->getAttribute('data-original');
                $span = $nd->getElementsByTagName('span');
                $p = $nd->getElementsByTagName('p');
                $arr = [
                    'site_id' => $this->site_id,
                    'title' => trim($h3->item(0)->nodeValue),
                    'item_id' => $bg->houseid,
                    'agent_id' => $bg->agentid,
                    'type' => $bg->housetype,
                    'data' => $bg->listingtype,
                    'pic' => $img,
                ];
                $tags = [];
                for ($s = 0; $s < $span->length; $s++) {
                    $node = $span->item($s);
                    $class = $node->getAttribute('class');
                    $value = $node->nodeValue;
                    switch ($class) {
                        case 'new':
                            $arr['rental_price'] = $value;
                            break;
                        case 'flor':
                            $arr['refresh_time'] = $value;
                            break;
                        case 'red-z':
                            $tags[] = $value;
                            break;
                        default:
                            break;
                    }
                }
                if ($tags) {
                    $arr['tags'] = implode(',', $tags);
                }

                for ($i = 0; $i < $p->length; $i++) {
                    if (1 < $i) {
                        break;
                    }

                    $nod = $p->item($i);
                    $html = $dom->innerHTML($nod);
                    $text = $dom->stripTagsContent($html);
                    $split = preg_split('/(\s+\-\s+|\s+)/', $text, 2);
                    # print_r([$text, $split]);
                    switch ($i) {
                        case 0:
                            $arr['house_type'] = $split[0];
                            $arr['rental_method'] = isset($split[1]) ? $split[1] : '';
                            break;
                        case 1:
                            list($arr['district_name'], $arr['complex_name']) = $split;
                            break;                          
                    }
                }
                # print_r($arr);exit;
                $list[] = $detail->exist($arr, 1);
            }
        }
        return $list;
    }

    /**
     * 下载PC版列表
     * 
     * @return [type] [description]
     */
    public function downloadPc()
    {
        /*      
        $size = $this->putFile(1, 'mas', $this->attr['page']);
        if (!$size) {
            return [
                'code' => 1, 
                'msg' => 'download error', 
                'info' => [__FILE__, __LINE__],
            ];
        }
        */

        $data = $this->getPathContents(1, 'mas', $this->attr['page']);
        $str = gzdecode($data);
        # header('Content-Type: text/html; charset=utf-8');
        $mb = new Mbstring($str, 'gbk');        
        echo $str = $mb->preg_replace('/charset=gb2312/', 'charset=utf-8');exit;

        $msg = '';

        return [
            'msg' => $msg,
            'result' => $size,
            'pageCount' => 1,
        ];
    }

    /*
     +------------------------------
     | 出租详情
     +------------------------------
     */

    /**
     * 下载出租详情
     * @return array api数据
     */
    public function downloadDetail()
    {
        # header('Content-Type: text/html; charset=utf-8');
        $this->min_size = 200;
        $this->returnVars = ['data', 'filesize']; # 
        $page = $this->attr['page'];
        $limit = 10;
        $Detail = new RentingSiteDetail;
        $where = "status IN (-1,-2)";       
        $all = $Detail->fetchAll($where, 'detail_id,city_name,item_id,type', 'detail_id', $page, $limit);
        $pageCount = $Detail->pageCount($where, $limit);
        
        $result = [];
        foreach ($all as $row) {
            $put = $this->putFileCurl([], 3, $row->city_name, $row->item_id, $row->type);
            # $put = 0; 
            if (!$put) {
                return [
                    'code' => 1, 
                    'msg' => 'download error', 
                    'info' => [__FILE__, __LINE__],
                    'row' => $row,
                ];
            }
            /*
            print_r($put);exit;
            
            echo $put['data'];exit;
             */
            $doc = $this->parse_dom($put['data'], null, null, 'gbk', [['/charset=\"gbk\"/', '/charset=gbk/'], ['charset="utf-8"', 'charset=utf-8']]); # echo $doc[1];exit;
            
            $put = $this->check_detail($doc, $row);
            $result[] = $put;
        }

        $msg = '';
        return [
            'msg' => $msg,
            'result' => $result,
            'pageCount' => $pageCount,
        ];
    }

    /*
     +------------------------------
     | 地点区域
     +------------------------------
     */

    /**
     * 下载城市列表
     * @return array api数据
     */
    public function downloadCity()
    {
        $size = $this->putFile();
        if (!$size) {
            return [
                'code' => 1, 
                'msg' => 'download error', 
                'info' => [__FILE__, __LINE__],
            ];
        }

        $msg = $this->enable_relay ? $this->relay_urls['parse/city'] : '';
        return [
            'msg' => $msg,
            'result' => $size,
            'pageCount' => 1,
        ];
    }

    /**
     * 解析城市列表
     * @return array api数据
     */
    public function parseCity()
    {
        $area = new RentingSiteArea;
        $data = $this->getPathContents();
        
        /*
        $path = $this->getProp(0, 'paths');     
        \Astrology\Extension\Zlib::uncompress($path, $path . '.txt');
        */
        
        $str = gzdecode($data);
        # header('Content-Type: text/html; charset=utf-8');
        $mb = new Mbstring($str, 'gbk');        
        $str = $mb->preg_replace('/charset=gb2312/', 'charset=utf-8');
        $doc = new \DOMDocument('1.0', 'utf-8');
        @$doc->loadHTML($str);      
        $c02 = $doc->getElementById('c02');

        // 省
        $li = $c02->getElementsByTagName('li');
        $len = $li->length;
        $last = $len - 1;
        $arr = [];
        for ($i = 0; $i < $len; $i++) {
            $node = $li->item($i);
            $strong = $node->getElementsByTagName('strong');
            $a = $node->getElementsByTagName('a');
            $prov = $i;
            if (0 == $i) { //直辖市
                
            } elseif ($last == $i) { //其他
                $prov = 0;
            } else { //省市自治区
                $prov = $strong->item(0)->nodeValue;
                $prov = $area->provinceExists($prov, $this->site_id);
            }

            // 市
            $length = $a->length;
            $cities = [];           
            for ($j = 0; $j < $length; $j++) {
                $nd = $a->item($j);
                $spell = $nd->getAttribute('spell');
                $href = $nd->getAttribute('href');
                $abbr = '';
                if (preg_match('/^(http:|)\/\/([a-z]+\.zu|zu\.[a-z]+)\.fang\.com/i', $href, $matches)) {
                    # print_r($matches);
                    $abbr = preg_replace('/^zu\.|\.zu$/i', '', $matches[2]);
                }

                $ct = [
                    'site_id' => $this->site_id,
                    'upper_id' => $prov,
                    'title' => $nd->nodeValue,
                ];
                $set = [
                    'name' => $spell,
                    'abbr' => $abbr,
                ];
                $city = $area->cityExists($ct, $set, 'area_id');
                $cities[$city] = $ct + $set;
            }
            $arr[] = [$prov, $cities];
            
        }
        # print_r($arr);
        
        $msg = $this->enable_relay ? $this->relay_urls['download/zf'] : '';
        return [
            'msg' => $msg,
            'result' => $arr,
            'pageCount' => 1,
        ];
    }
}