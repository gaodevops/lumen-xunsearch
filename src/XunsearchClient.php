<?php 
namespace Liugj\Xunsearch;

use Cache;

class XunsearchClient
{
	/**
	 * @var XSIndex 索引操作对象
	 */
	private $_index;

	/**
	 * @var XSSearch 搜索操作对象
	 */
	private $_search;

	/**
	 * @var XSServer scws 分词服务器
	 */
	private $_scws;

	/**
	 * @var XSFieldScheme 当前字段方案
	 */
	private $_scheme, $_bindScheme;
	private $_config;

	/**
	 * @var XS 最近创建的 XS 对象
	 */
	private static $_lastXS;

	/**
	 * 构造函数
	 * 特别说明一个小技巧, 参数 $file 可以直接是配置文件的内容, 还可以是仅仅是文件名,
	 * 如果只是文件名会自动查找 XS_LIB_ROOT/../app/$file.ini
	 * @param string $file 要加载的项目配置文件
	 */
	public function __construct($indexHost, $searchHost)
	{
        $this->_config['server.index']  = $indexHost;
        $this->_config['server.search'] = $searchHost;
		self::$_lastXS = $this;
	}
    /**
     * initIndex 
     * 
     * @param string $indexName
     * 
     * @access public
     * 
     * @return mixed
     */
    public function initIndex(string $indexName) 
    {
        $this->setName($indexName);
        if (isset($this->_index[$indexName])) {
            return $this->_index[$indexName];
        } else {
            $this->loadIniFile(config('scout.schema.'. $indexName));
            $adds = array();
            $conn = isset($this->_config['server.index']) ? $this->_config['server.index'] : 8383;
            if (($pos = strpos($conn, ';')) !== false) {
                $adds = explode(';', substr($conn, $pos + 1));
                $conn = substr($conn, 0, $pos);
            }
            $this->_index[$indexName] = new \XSIndex($conn, $this);
            $this->_index[$indexName]->setProject($searchName);
            $this->_index[$indexName]->setTimeout(0);
            foreach ($adds as $conn) {
                $conn = trim($conn);
                if ($conn !== '') {
                    $this->_index[$indexName]->addServer($conn)->setTimeout(0);
                }
            }
        }

        return $this->_index[$indexName];
    }

	/**
	 * 析构函数
	 * 由于对象交叉引用, 如需提前销毁对象, 请强制调用该函数
	 */
	public function __destruct()
	{
		$this->_index = null;
		$this->_search = null;
	}

	/**
	 * 获取最新的 XS 实例
	 * @return XS 最近创建的 XS　对象
	 */
	public static function getLastXS()
	{
		return self::$_lastXS;
	}

	/**
	 * 获取当前在用的字段方案
	 * 通用于搜索结果文档和修改、添加的索引文档
	 * @return XSFieldScheme 当前字段方案
	 */
	public function getScheme()
	{
		return $this->_scheme;
	}

	/**
	 * 设置当前在用的字段方案
	 * @param XSFieldScheme $fs 一个有效的字段方案对象
	 * @throw XSException 无效方案则直接抛出异常
	 */
	//public function setScheme(XSFieldScheme $fs)
	//{
	//	$fs->checkValid(true);
	//	$this->_scheme = $fs;
	//	if ($this->_search !== null) {
	//		$this->_search->markResetScheme();
	//	}
	//}

	/**
	 * 还原字段方案为项目绑定方案
	 */
	//public function restoreScheme()
	//{
	//	if ($this->_scheme !== $this->_bindScheme) {
	//		$this->_scheme = $this->_bindScheme;
	//		if ($this->_search !== null) {
	//			$this->_search->markResetScheme(true);
	//		}
	//	}
	//}

	/**
	 * @return array 获取配置原始数据
	 */
	public function getConfig()
	{
		return $this->_config;
	}

	/**
	 * 获取当前项目名称
	 * @return string 当前项目名称
	 */
	public function getName()
	{
		return $this->_config['project.name'];
	}

	/**
	 * 修改当前项目名称
	 * 注意，必须在 {@link getSearch} 和 {@link getIndex} 前调用才能起作用
	 * @param string $name 项目名称
	 */
	public function setName($name)
	{
		$this->_config['project.name'] = $name;
	}

	/**
	 * 获取项目的默认字符集
	 * @return string 默认字符集(已大写)
	 */
	public function getDefaultCharset()
	{
		return isset($this->_config['project.default_charset']) ?
			strtoupper($this->_config['project.default_charset']) : 'UTF-8';
	}

	/**
	 * 改变项目的默认字符集
	 * @param string $charset 修改后的字符集
	 */
	public function setDefaultCharset($charset)
	{
		$this->_config['project.default_charset'] = strtoupper($charset);
	}

	/**
	 * 获取搜索操作对象
	 * @return XSSearch 搜索操作对象
	 */
	public function initSearch($searchName)
	{
        $this->setName($searchName);
		if (isset($this->_search[$searchName])) {
            return $this->_search[$searchName];
        } else {
            $this->loadIniFile(config('scout.schema.'. $indexName));
			$conns = array();
			if (!isset($this->_config['server.search'])) {
				$conns[] = 8384;
			} else {
				foreach (explode(';', $this->_config['server.search']) as $conn) {
					$conn = trim($conn);
					if ($conn !== '') {
						$conns[] = $conn;
					}
				}
			}
			if (count($conns) > 1) {
				shuffle($conns);
			}
			for ($i = 0; $i < count($conns); $i++) {
				try {
					$this->_search[$searchName] = new XSSearch($conns[$i], $this);
                    $this->_search[$searchName]->setProject($searchName);
					$this->_search[$searchName]->setCharset($this->getDefaultCharset());
					return $this->_search[$searchName];
				} catch (XSException $e) {
					if (($i + 1) === count($conns)) {
						throw $e;
					}
				}
			}
		}

		return $this->_search[$searchName];
	}

	/**
	 * 创建 scws 分词连接
	 * @return XSServer 分词服务器
	 */
	public function getScwsServer()
	{
		if ($this->_scws === null) {
			$conn = isset($this->_config['server.search']) ? $this->_config['server.search'] : 8384;
			$this->_scws = new XSServer($conn, $this);
		}
		return $this->_scws;
	}

	/**
	 * 获取当前主键字段
	 * @return XSFieldMeta 类型为 ID 的字段
	 * @see XSFieldScheme::getFieldId
	 */
	public function getFieldId()
	{
		return $this->_scheme->getFieldId();
	}

	/**
	 * 获取当前标题字段
	 * @return XSFieldMeta 类型为 TITLE 的字段
	 * @see XSFieldScheme::getFieldTitle
	 */
	public function getFieldTitle()
	{
		return $this->_scheme->getFieldTitle();
	}

	/**
	 * 获取当前内容字段
	 * @return XSFieldMeta 类型为 BODY 的字段
	 * @see XSFieldScheme::getFieldBody
	 */
	public function getFieldBody()
	{
		return $this->_scheme->getFieldBody();
	}

	/**
	 * 获取项目字段元数据
	 * @param mixed $name 字段名称(string) 或字段序号(vno, int)
	 * @param bool $throw 当字段不存在时是否抛出异常, 默认为 true
	 * @return XSFieldMeta 字段元数据对象
	 * @throw XSException 当字段不存在并且参数 throw 为 true 时抛出异常
	 * @see XSFieldScheme::getField
	 */
	public function getField($name, $throw = true)
	{
		return $this->_scheme->getField($name, $throw);
	}

	/**
	 * 获取项目所有字段结构设置
	 * @return XSFieldMeta[]
	 */
	public function getAllFields()
	{
		return $this->_scheme->getAllFields();
	}

	/**
	 * 智能加载类库文件
	 * 要求以 Name.class.php 命名并与本文件存放在同一目录, 如: XSTokenizerXxx.class.php
	 * @param string $name 类的名称
	 */
	public static function autoload($name)
	{
		$file = XS_LIB_ROOT . '/' . $name . '.class.php';
		if (file_exists($file)) {
			require_once $file;
		}
	}

	/**
	 * 字符集转换
	 * 要求安装有 mbstring, iconv 中的一种
	 * @param mixed $data 需要转换的数据, 支持 string 和 array, 数组会自动递归转换
	 * @param string $to 转换后的字符集
	 * @param string $from 转换前的字符集
	 * @return mixed 转换后的数据
	 * @throw XSEXception 如果没有合适的转换函数抛出异常
	 */
	public static function convert($data, $to, $from)
	{
		// need not convert
		if ($to == $from) {
			return $data;
		}
		// array traverse
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				$data[$key] = self::convert($value, $to, $from);
			}
			return $data;
		}
		// string contain 8bit characters
		if (is_string($data) && preg_match('/[\x81-\xfe]/', $data)) {
			// mbstring, iconv, throw ...
			if (function_exists('mb_convert_encoding')) {
				return mb_convert_encoding($data, $to, $from);
			} elseif (function_exists('iconv')) {
				return iconv($from, $to . '//TRANSLIT', $data);
			} else {
				throw new XSException('Cann\'t find the mbstring or iconv extension to convert encoding');
			}
		}
		return $data;
	}

	/**
	 * 计算经纬度距离
	 * @param float $lon1 原点经度
	 * @param float $lat1 原点纬度
	 * @param float $lon2 目标点经度
	 * @param float $lat2 目标点纬度
	 * @return float 两点大致距离，单位：米
	 */
	public static function geoDistance($lon1, $lat1, $lon2, $lat2)
	{
		$dx = $lon1 - $lon2;
		$dy = $lat1 - $lat2;
		$b = ($lat1 + $lat2) / 2;
		$lx = 6367000.0 * deg2rad($dx) * cos(deg2rad($b));
		$ly = 6367000.0 * deg2rad($dy);
		return sqrt($lx * $lx + $ly * $ly);
	}

	/**
	 * 解析INI配置文件
	 * 由于 PHP 自带的 parse_ini_file 存在一些不兼容，故自行简易实现
	 * @param string $data 文件内容
	 * @return array 解析后的结果
	 */
	private function parseIniData($data)
	{
		$ret = array();
		$cur = &$ret;
		$lines = explode("\n", $data);
		foreach ($lines as $line) {
			if ($line === '' || $line[0] == ';' || $line[0] == '#') {
				continue;
			}
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			if ($line[0] === '[' && substr($line, -1, 1) === ']') {
				$sec = substr($line, 1, -1);
				$ret[$sec] = array();
				$cur = &$ret[$sec];
				continue;
			}
			if (($pos = strpos($line, '=')) === false) {
				continue;
			}
			$key = trim(substr($line, 0, $pos));
			$value = trim(substr($line, $pos + 1), " '\t\"");
			$cur[$key] = $value;
		}
		return $ret;
	}

	/**
	 * 加载项目配置文件
	 * @param string $file 配置文件路径
	 * @throw XSException 出错时抛出异常
	 * @see XSFieldMeta::fromConfig
	 */
	private function loadIniFile($file)
    {
        // parse ini file
        $key = 'xunsearch_'. md5($file);
        $mtime = filemtime($file);
        if (($data = Cache ::get($key)) !== null) {
            if ($data['mtime'] != $mtime) {
                $data = false;
            }else {
                $this->_config = $data['config'];
            }
        } 
        if (!$data) {
            $data['config'] = $this->_config = $this->parseIniData($file);
            $data['mtime']  = $mtime; 
            if ($this->_config === false) {
                throw new XSException('Failed to parse project config file/string: \'' . substr($file, 0, 10) . '...\'');
            }

            Cache :: put ($key, $data, 86400);
        }

        // create the scheme object
        $scheme = new XSFieldScheme;
        foreach ($this->_config as $key => $value) {
            if (is_array($value)) {
                $scheme->addField($key, $value);
            }
        }
        $scheme->checkValid(true);

        // load default config
        //if (!isset($this->_config['project.name'])) {
        //	$this->_config['project.name'] = basename($file, '.ini');
        //}

        //// save to cache
        $this->_scheme = $this->_bindScheme = $scheme;
    }
}
