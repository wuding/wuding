<?php

namespace Astrology;

class Database
{
	public static $adapter = null;
	public $driver = 'pdo_mysql';
	public $host = 'localhost';
	public $port = 3306;
	public $user = 'root';
	public $password = 'root';
	public $db_name = 'mysql';
	public $table_name = 'user';
	public $primary_key = null;
	public $join = '';
	public $group_by = '';
	public $having = '';
	
	public function __construct($arg = [])
	{
		if (!$arg) {
			$arg = $GLOBALS['CONFIG']['database'];
		}
		$this->init($arg);
	}
	
	public function setVar($arg = [])
	{
		foreach ($arg as $key => $value) {
			$this->$key = $value;
		}
	}
	
	public function init($arg = [])
	{
		$this->setVar($arg);
		$arg = [
			'host' => $this->host,
			'port' => $this->port,
			'db_name' => $this->db_name,
			'username' => $this->user,
			'password' => $this->password,
		];
		$this->getAdapter($this->driver, $arg);
	}
	
	public function getAdapter($name, $arg)
	{
		$name = str_replace('_', ' ', $name);
		$name = ucwords($name);
		$name = str_replace(' ', '', $name);
		$class = '\Astrology\Extension\Php' . $name;
		return self::$adapter = new $class($arg);
	}
	
	public function query($sql = null)
	{
		return self::$adapter->query($sql);
	}
	
	public function from($name = null)
	{
		return $name = $name ? : $this->db_name . '.' . $this->table_name;
	}
	
	public function sqlSet($data)
	{
		if (!is_array($data)) {
			return $data;
		}
		
		$arr = [];
		foreach ($data as $key => $value) {
			$value = addslashes($value);
			$arr[] = "`$key` = '$value'";
		}
		return $str = implode(", ", $arr);
	}
	
	public function sqlWhere($where, $type = 'AND')
	{
		if (!is_array($where)) {
			return $where;
		}
		
		$arr = [];
		foreach ($where as $key => $value) {
			// 没有列名的直接写SQL语句
			if (is_numeric($key)) {
				$arr[] = $value;
				
			// 多条件
			} elseif (preg_match('/^(ADN|OR)$/', $key, $matches)) {
				print_r([$matches, __FILE__, __LINE__]);
				exit;
			} elseif (is_array($value)) {
				print_r([$value, __FILE__, __LINE__]);
				exit;
			} else {
				$value = addslashes($value);
				$arr[] = "`$key` = '$value'";
			}
		}
		return $str = implode(" $type ", $arr);
	}
	
	public function insert($data)
	{
		$db_table = $this->from();
		$sql = "INSERT INTO $db_table SET ";
		$sql .= $this->sqlSet($data);
		return self::$adapter->insert($sql);
	}
	
	public function find($where = null, $column = '*', $order = null, $limit = 1)
	{
		$db_table = $this->from();
		$sql = "SELECT $column FROM $db_table";
		$where = $this->sqlWhere($where);
		if ($where) {
			$sql .= " WHERE $where";
		}
		if ($order) {
			$sql .= " ORDER BY $order";
		}
		$sql .= " LIMIT $limit";
		return self::$adapter->find($sql);
	}
	
	public function count($where = null)
	{
		$db_table = $this->from();
		$sql = "SELECT COUNT(1) AS num FROM $db_table";
		$where = $this->sqlWhere($where);
		if ($where) {
			$sql .= " WHERE $where";
		}
		$row = self::$adapter->find($sql);
		return $row->num;
	}
	
	public function select($where = null, $column = '*', $order = null, $limit = 10)
	{
		$db_table = $this->from();
		$sql = "SELECT $column FROM $db_table";
		if ($this->join) {
			$sql .= " $this->join";
		}
		
		$where = $this->sqlWhere($where);
		if ($where) {
			$sql .= " WHERE $where";
		}
		
		if ($this->group_by) {
			$sql .= " GROUP BY $this->group_by";
			if ($this->having) {
				$sql .= " HAVING $this->having";
			}
		}
		
		if ($order) {
			$sql .= " ORDER BY $order";
		}
		$sql .= " LIMIT $limit";
		return self::$adapter->select($sql);
	}
	
	public function update($set = [], $where = null, $order = null, $limit = null)
	{
		$db_table = $this->from();
		$sql = "UPDATE $db_table SET ";
		$sql .= $this->sqlSet($set);
		
		$where = $this->sqlWhere($where);
		if ($where) {
			$sql .= " WHERE $where";
		}
		
		if ($order) {
			$sql .= " ORDER BY $order";
		}
		if (null !== $limit) {
			$sql .= " LIMIT $limit";
		}
		# echo $sql;exit;
		return $this->exec($sql);
	}
	
	public function __call($name, $arguments)
	{
		return self::$adapter->$name($arguments[0]);
	}
}