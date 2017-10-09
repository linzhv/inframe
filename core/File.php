<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 09/10/2017
 * Time: 21:43
 */
declare(strict_types=1);


namespace inframe\core;

use inframe\throws\storage\FileNotFoundException;

class File
{

    # 文件类型
    const IS_EMPTY = 0; # 文件不存在
    const IS_FILE = 1; # 是文件
    const IS_DIR = 2; # 是目录

    const ACCESS_NO_CHECK = 0;
    const READ_ACCESS = 1;
    const WRITE_ACCESS = 2;

    /**
     * 如果目标文件已存在，将会被覆盖
     * @param string $source
     * @param string $dest
     * @return bool
     */
    public static function copy($source, $dest)
    {
        if (is_file($source)) {
            self::makeParentDir($dest);
            return copy($source, $dest);
        } elseif (is_dir($source)) {
            $result = false;
            if (!$dest or $source == substr($dest, 0, strlen($source))) return false;//防止父文件夹拷贝到子文件夹，无限递归
            if (is_file($source)) {
                if ($dest[strlen($dest) - 1] == '/') {
                    $__dest = $dest . '/' . basename($source);
                } else {
                    $__dest = $dest;
                }
                $result = copy($source, $__dest);
                chmod($__dest, 0777);
            } elseif (is_dir($source)) {
                if ($dest[strlen($dest) - 1] == '/') {
                    $dest = $dest . basename($source);
                }
                if (!is_dir($dest)) {
                    mkdir($dest, 0777);
                }
                if (!$dh = opendir($source)) return false;
                while (($file = readdir($dh)) !== false) {
                    if ($file != '.' && $file != '..') {
                        if (!is_dir($source . '/' . $file)) {
                            $__dest = $dest . '/' . $file;
                        } else {
                            $__dest = $dest . '/' . $file;
                        }
                        $result = self::copy($source . '/' . $file, $__dest);
                    }
                }
                closedir($dh);
            }
            return $result;
        } else {
            return false;
        }
    }



//----------------------------------------------------------------------------------------------------------------------
//------------------------------------ 读取 -----------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------------------------------

    /**
     * 读取文件夹内容，并返回一个数组(不包含'.'和'..')
     * array(
     *      //文件名称(相对于带读取的目录而言) => 文件内容
     *      'filename' => 'file full path',
     * );
     * @param string $dirpath
     * @param bool $recursion
     * @param callable|null $callback
     * @param bool $_isouter 辅助参数,用于判断是外部调用还是内部的
     * @return array
     * @throws FileNotFoundException 目录不存在时抛出
     */
    public static function readdir($dirpath, $recursion = false, callable $callback = null, $_isouter = true)
    {
        static $relative_path = null;
        if ($_isouter) {
            if (!is_dir($dirpath)) throw new FileNotFoundException($dirpath);
            $relative_path = realpath($dirpath);
        }
        $files = [];
        $dirpath = realpath($dirpath);
        $handler = opendir($dirpath);
        while (($filename = readdir($handler))) {//未读到最后一个文件时候返回false,否则返回文件名称
            if ($filename === '.' or $filename === '..') continue;
            $path = $dirpath . DIRECTORY_SEPARATOR . $filename;//子文件的完整路径
            $index = substr($path, strlen($relative_path));
            if ($callback) {
                if (false === ($result = call_user_func_array($callback, [$path, $index]))) continue;
                switch (gettype($result)) {
                    case 'boolean':
                        continue;//筛选
                    case IN_TYPE_ARRAY:
                        $index = $result[0];
                        $path = $result[1];
                        break;
                    case IN_TYPE_STR:
                        $index = $result;
                        break;
                }
            }
            $files[$index] = $path;
            if ($recursion and is_dir($path)) {
                $files = array_merge($files, self::readdir($path, $recursion, $callback, false));//递归,不清空
            }
        }
        closedir($handler);//关闭目录指针
        return $files;
    }

    /**
     * 读取文件,参数参考read方法
     * @param string $filepath 文件路径
     * @param string $file_encoding
     * @param string $readout_encoding
     * @param int $maxlen Maximum length of data read. The default of php is to read until end of file is reached. But I limit to 4 MB
     * @return string 读取可访问并且未受保护（runtime/rt文件夹下的）的文件时返回文件内容，否则返回0表示无法获取（读取可访问且未受保护的文件夹内容）
     */
    public static function read($filepath, $file_encoding = IN_CHARSET_UTF8, $readout_encoding = IN_CHARSET_UTF8, $maxlen = 4094304)
    {
        if (!is_file($filepath) or !is_readable($filepath)) {
            return '';
        }
        $content = file_get_contents($filepath, false, null, 0, $maxlen);//限制大小为2M
        if (false === $content) return '';//false on failure
        if (null === $file_encoding or $file_encoding === $readout_encoding) {
            return $content;//return the raw content or what the read is what the need
        } else {
            $readoutEncode = "{$readout_encoding}//IGNORE";
            if (is_string($file_encoding) and false === strpos($file_encoding, ',')) {
                return iconv($file_encoding, $readoutEncode, $content);
            }
            return mb_convert_encoding($content, $readoutEncode, $file_encoding);
        }
    }

    /**
     * 确定文件或者目录是否存在
     * 相当于 is_file() or is_dir()
     * @param string $filepath 文件路径
     * @param int $auth 文件权限检测
     * @return int 0表示目录不存在或者无法访问,1表示是目录 2表示是文件
     */
    public static function has($filepath, $auth = 0)
    {
        $type = is_dir($filepath) ? self::IS_DIR : (is_file($filepath) ? self::IS_FILE : self::IS_EMPTY);
        if ($auth & self::READ_ACCESS and !is_readable($filepath)) {
            return self::IS_EMPTY;
        }
        if ($auth & self::WRITE_ACCESS and !is_writable($filepath)) {
            return self::IS_EMPTY;
        }
        return $type;
    }

    /**
     * 返回文件内容上次的修改时间
     *
     * 注意：
     *  windows下同下如果一个文件夹下级文件夹下一个文件发生了修改，那个这个下级文件文件夹的修改时间会发生变化，但是这个文件夹的修改时间不会发生变化
     *
     * @param string $filepath 文件路径
     * @param int $mtime 修改时间
     * @return int|bool 如果是修改时间的操作返回的bool;如果是获取修改时间,则返回Unix时间戳;
     */
    public static function mtime($filepath, $mtime = null)
    {
        if (null !== $mtime) {
            //设置时间,需要写的权限
            return touch($filepath, $mtime);
        } else {
            return file_exists($filepath) ? filemtime($filepath) : self::IS_EMPTY;
        }
    }

    /**
     * 获取文件按大小
     * 注：即便filesize加了@也无法防止系统的报错
     * @param string $filepath 文件路径
     * @return int|false|null 按照字节计算的单位,返回false表示是文件夹
     */
    public static function size($filepath)
    {
        if (is_file($filepath)) {
            return filesize($filepath);
        } elseif (is_dir($filepath)) {
            $sizeResult = 0;
            $handle = opendir($filepath);
            while (false !== ($FolderOrFile = readdir($handle))) {
                if ($FolderOrFile != '.' && $FolderOrFile != '..') {
                    $file = "$filepath/$FolderOrFile";
                    $sizeResult += is_dir($file) ? self::size($file) : filesize($file);
                }
            }
            closedir($handle);
            return $sizeResult;
        } else {
            return self::IS_EMPTY;//文件无法访问
        }
    }

//----------------------------------------------------------------------------------------------------------------------
//------------------------------------ 写入 -----------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------------------------------
    /**
     * 创建文件夹
     * @param string $dir 文件夹路径
     * @param int $mode 文件夹权限
     * @return bool 文件夹已经存在的时候返回false,目标文件夹在受保护的范围之外也会返回false,成功创建返回true
     */
    public static function mkdir($dir, $mode = 0766)
    {
        if (is_dir($dir)) return chmod($dir, intval($mode, 8));;//文件夹已经存在
        return mkdir($dir, intval($mode, 8), true);
    }

    /**
     * 修改文件权限
     * @param string $path 文件路径
     * @param int $mode 文件权限(八进制)
     * @return bool 文件不存在或者修改失败时返回false
     */
    private static function chmod($path, $mode)
    {
        if (is_file($path)) {
            return chmod($path, intval($mode, 8));
        } elseif (is_dir($path)) {
            $mode = intval($mode, 8);
            if (!$dh = opendir($path)) return false;
            while (($file = readdir($dh)) !== false) {
                if ($file === '.' or $file === '..') continue;
                $fullpath = $path . DIRECTORY_SEPARATOR . $file;
                chmod($fullpath, $mode);
                return self::chmod($fullpath, $mode);
            }
            closedir($dh);
            return chmod($path, $mode);
        } else {
            return false;
        }
    }

    /**
     * 设定文件的访问和修改时间
     * 注意的是:内置函数touch在文件不存在的情况下会创建新的文件,此时创建时间可能大于修改时间和访问时间
     *         但是如果是在上层目录不存在的情况下
     * @param string $filepath 文件路径
     * @param int $mtime 文件修改时间
     * @param int $atime 文件访问时间，如果未设置，则值设置为mtime相同的值
     * @return bool 是否成功 ,访问在保护范围内或者修改失败都会返回false
     */
    public static function touch($filepath, $mtime = null, $atime = null)
    {
        if (!self::makeParentDir($filepath)) {
            return false;
        }
        return touch($filepath, isset($mtime) ? $mtime : IN_NOW, isset($atime) ? $atime : IN_NOW);
    }

    /**
     * 删除文件
     * 删除目录时必须保证该目录为空,or set parameter 2 as true
     * @param string $filepath 文件或者目录的路径
     * @param bool $recursion 删除的目标是目录时,若目录下存在文件,是否进行递归删除,默认为false
     * @return bool
     */
    public static function unlink($filepath, $recursion = false)
    {
        if (is_file($filepath)) {
            return unlink($filepath);
        } elseif (is_dir($filepath)) {
            return self::rmdir($filepath, $recursion);
        }
        return false; //file do not exist
    }

    /**
     * @param string $filepath
     * @param string $content
     * @param string $write_encode Encode of the text to write
     * @param string $text_encode encode of content,it will be 'UTF-8' while scruipt file is encode with 'UTF-8',but sometime it's not expect
     * @return bool
     */
    public static function write($filepath, $content, $write_encode = IN_CHARSET_UTF8, $text_encode = IN_CHARSET_UTF8)
    {
        self::makeParentDir($filepath);
        //文本编码检测
        $write_encode !== $text_encode and $content = iconv($text_encode, "{$write_encode}//IGNORE", $content);
        //文件写入
        return file_put_contents($filepath, $content) > 0;
    }

    /**
     * 将指定内容追加到文件中
     * 文件不存在时直接写入
     * @param string $filepath 文件路径
     * @param string $content 要写入的文件内容
     * @param string $write_encode 写入文件时的编码
     * @param string $text_encode 文本本身的编码格式,默认使用UTF-8的编码格式
     * @return bool 文件打开或者关闭失败也会返回false
     */
    public static function append($filepath, $content, $write_encode = IN_CHARSET_UTF8, $text_encode = IN_CHARSET_UTF8)
    {
        //编码处理
        $write_encode !== $text_encode and $content = iconv($text_encode, "{$write_encode}//IGNORE", $content);
        //文件不存在时
        if (!is_file($filepath)) {
            if (self::makeParentDir($filepath)) {
                return file_put_contents($filepath, $content) > 0;
            }
        } else {
            if (false !== ($handler = fopen($filepath, 'a+'))) {
                //关闭文件
                $rst = fwrite($handler, $content); //出现错误时返回false
                return fclose($handler) ? $rst > 0 : false;
            }
        }
        return false;
    }

    /**
     * 文件父目录检测
     * 不存在时创建，不可读写时
     * @param string $path the path must be encode with file system
     * @param int $auth
     * @return bool
     */
    public static function makeParentDir($path, $auth = 0766)
    {
        $path = dirname($path);
        if (!is_dir($path)) return mkdir($path, $auth, true);
        if (!is_writeable($path) or !is_readable($path)) return self::chmod($path, $auth);
        return true;
    }

    /**
     * 删除文件夹
     * 注意:@ rmdir($dirpath); 也无法阻止报错
     * @param string $dir 文件夹名路径
     * @param bool $recursion 是否递归删除
     * @return bool 非文件夹或者打开文件夹失败都会返回false,参数二设置成false但是目标文件夹下有文件也会返回false
     */
    public static function rmdir($dir, $recursion = false)
    {
        if (is_dir($dir) and ($dir_handle = opendir($dir))) {
            while (false !== ($file = readdir($dir_handle))) {
                if ($file === '.' or $file === '..') continue;
                if (false === $recursion) {//存在其他文件或者目录,非true时循环删除
                    closedir($dir_handle);
                    return false;
                }
                $file = "{$dir}/{$file}";//$dir = SR_IS_WIN?str_replace('\\','/',"{$dir}/{$file}"):"{$dir}/{$file}"; //windows

                if (is_file($file)) {
                    if (false === unlink($file)) return false;
                } elseif (is_dir($file)) {
                    if (false === self::rmdir($dir, true)) return false;
                }
            }
            closedir($dir_handle);
            return rmdir($dir);
        }
        return false;
    }
}