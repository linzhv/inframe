<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 09/10/2017
 * Time: 22:05
 */
declare(strict_types=1);


namespace inframe\core\cache;

/**
 * Class File 文件缓存
 * @package inframe\core\cache
 */
class File
{
    protected $_config = [
        'path' => IN_PATH_RUNTIME . 'cache/',
    ];

    protected $path = '';

    /**
     * File constructor.
     * @param array $config 配置
     */
    public function __construct(array $config = [])
    {
        $config and $this->_config = array_merge($this->_config, $config);
        $this->path = $this->_config['path'];
        if (!is_dir($this->path)) mkdir($this->path, 0700, true);
    }

    /**
     * 判断文件是否存在
     * @param string $id 缓存ID
     * @return bool
     */
    public function has($id)
    {
        return is_file($this->path . md5($id));
    }

    /**
     * 设置缓存
     * @param string $id 缓存ID
     * @param mixed $data 缓存数据
     * @param int $ttl 缓存期间
     * @return bool
     */
    public function set($id, $data, $ttl = IN_ONE_HOUR)
    {
        $contents = [
            'id' => $id,
            'time' => time(),
            'ttl' => $ttl,
            'data' => $data
        ];
        $id = md5($id);
        if (file_put_contents($file = $this->path . $id, serialize($contents))) {
            return true;
        }
        return false;
    }

    /**
     * 获取缓存
     * @param string $id 缓存ID
     * @param mixed $replace 缓存不存在时默认返回的值,默认为null
     * @return mixed
     */
    public function get($id, $replace = null)
    {
        $id = md5($id);
        if (!is_file($file = $this->path . $id)) {
            return $replace;
        }
        $data = file_get_contents($file);
        if (false === $data) {
            return $replace;
        }
        $data = unserialize($data);

        if ($data['ttl'] > 0 && time() > $data['time'] + $data['ttl']) {
            unlink($file);
            return $replace;
        }

        return $data['data'];
    }

    /**
     * 清除缓存
     * @param string $id 缓存ID
     * @return bool
     */
    public function clean($id)
    {
        $id = md5($id);
        return is_file($file = $this->path . $id) ? unlink($file) : false;
    }

    /**
     * 清空全部缓存
     * @deprecated
     * @return bool
     */
    public function cleanAll()
    {
        return self::rmdir($this->path, false, true);
    }


    /**
     * @from ci
     * Delete Files
     *
     * Deletes all files contained in the supplied directory path.
     * Files must be writable or owned by the system in order to be deleted.
     * If the second parameter is set to TRUE, any directories contained
     * within the supplied base directory will be nuked as well.
     *
     * @param    string $path File path
     * @param    bool $del_dir Whether to delete any directories found in the path
     * @param    int $_level Current directory depth level (default: 0; internal use only)
     * @return    bool
     */
    public static function rmdir($path, $del_dir = FALSE, $_level = 0)
    {
        // Trim the trailing slash
        $path = rtrim($path, '/\\');
        if (is_file($path)) {
            return unlink($path);
        }

        if (!$current_dir = opendir($path)) {
            return FALSE;
        }

        while (false !== ($filename = readdir($current_dir))) {
            if ($filename !== '.' && $filename !== '..') {
                $filepath = $path . DIRECTORY_SEPARATOR . $filename;
                if (is_dir($filepath) && $filename[0] !== '.' && !is_link($filepath)) {
                    self::rmdir($filepath, $del_dir, $_level + 1);
                }
            }
        }

        closedir($current_dir);

        return ($del_dir === TRUE && $_level > 0) ? rmdir($path) : TRUE;
    }

}