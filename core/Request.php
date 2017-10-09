<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 2017/10/9
 * Time: 10:16
 */

declare(strict_types=1);
namespace inframe\core;

/**
 * Class Request 客户端请求类
 * @package inframe\core
 */
final class Request
{
    /**
     * 浏览器类型
     */
    const AGENT_IE = 'ie';
    const AGENT_FIRFOX = 'firefox';
    const AGENT_EDGE = 'edge';
    const AGENT_CHROME = 'chrome';
    const AGENT_OPERA = 'opera';
    const AGENT_SAFARI = 'safari';
    const AGENT_UNKNOWN = 'unknown';

    const LANG_ZH = 'zh';
    const LANG_ZH_CN = 'zh_CN';
    const LANG_ZH_TW = 'zh_TW';
    const LANG_ZH_HK = 'zh_HK';

    const LANG_EN = 'en';
    const LANG_EN_US = 'en_US';


    /**
     * Cross Site Scripting(跨站脚本攻击)
     *
     * remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
     * this prevents some character re-spacing such as <java\0script>
     * note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
     *
     * @param string|array $val
     * @return string|array
     */
    public static function removeXss($val)
    {
        $val = preg_replace('/([\x00-\x08,\x0b-\x0c,\x0e-\x19])/', '', $val);

        // straight replacements, the user should never need these since they're normal characters
        // this prevents like <IMG SRC=@avascript:alert('XSS')>
        $search = 'abcdefghijklmnopqrstuvwxyz';
        $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $search .= '1234567890!@#$%^&*()';
        $search .= '~`";:?+/={}[]-_|\'\\';
        for ($i = 0; $i < strlen($search); $i++) {
            // ;? matches the ;, which is optional
            // 0{0,7} matches any padded zeros, which are optional and go up to 8 chars

            // @ @ search for the hex values
            $val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val); // with a ;
            // @ @ 0{0,7} matches '0' zero to seven times
            $val = preg_replace('/(&#0{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val); // with a ;
        }

        // now the only remaining whitespace attacks are \t, \n, and \r
        $ra1 = array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');
        $ra2 = array('onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');
        $ra = array_merge($ra1, $ra2);

        $found = true; // keep replacing as long as the previous round replaced something
        while ($found == true) {
            $val_before = $val;
            for ($i = 0; $i < sizeof($ra); $i++) {
                $pattern = '/';
                for ($j = 0; $j < strlen($ra[$i]); $j++) {
                    if ($j > 0) {
                        $pattern .= '(';
                        $pattern .= '(&#[xX]0{0,8}([9ab]);)';
                        $pattern .= '|';
                        $pattern .= '|(&#0{0,8}([9|10|13]);)';
                        $pattern .= ')*';
                    }
                    $pattern .= $ra[$i][$j];
                }
                $pattern .= '/i';
                $replacement = substr($ra[$i], 0, 2) . '<x>' . substr($ra[$i], 2); // add in <> to nerf the tag
                $val = preg_replace($pattern, $replacement, $val); // filter out the hex tags
                if ($val_before == $val) {
                    // no replacements were made, so exit the loop
                    $found = false;
                }
            }
        }
        return $val;
    }

    /**
     * 获取请求参数
     * @param string $key
     * @param bool $xssdef
     * @param int $from 参数来源
     * @return string|int
     */
    public static function input($key, $xssdef = true, $from = IN_FROM_REQUEST)
    {
        switch ($from) {
            case IN_FROM_REQUEST:
                $arguments = $_REQUEST;
                break;
            case IN_FROM_GET:
                $arguments = $_GET;
                break;
            case IN_FROM_POST:
                $arguments = $_POST;
                break;
            case IN_FROM_INPUT:
                parse_str(file_get_contents('php://input'), $arguments);
                break;
            default:
                $arguments = array_merge($_GET, $_POST);//POST覆盖GET
                break;
        }
        if (($val = isset($arguments[$key]) ? $arguments[$key] : '') and $xssdef) {
            $xssdef and $val = self::removeXss($val);
        }
        return $val;
    }


    /**
     * 获取浏览器类型
     * @return string
     */
    public static function getBrowser()
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {    //当浏览器没有发送访问者的信息的时候
            return 'unknow';
        }
        if ($agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '') {
            if (strpos($agent = strtolower($agent), 'msie') !== false || strpos($agent, 'rv:11.0')) //ie11判断
                return self::AGENT_IE;
            elseif (strpos($agent, 'edge') !== false)
                return self::AGENT_EDGE;
            elseif (strpos($agent, 'firefox') !== false)
                return self::AGENT_FIRFOX;
            elseif (strpos($agent, 'chrome') !== false)
                return self::AGENT_CHROME;
            elseif (strpos($agent, 'opera') !== false)
                return self::AGENT_OPERA;
            elseif ((strpos($agent, 'chrome') == false) and strpos($agent, 'safari') !== false)
                return self::AGENT_SAFARI;
        }
        return self::AGENT_UNKNOWN;
    }


    /**
     * get language from client
     * @param string $default
     * @return string
     */
    public static function getLang($default = self::LANG_EN)
    {
        if (preg_match('/^([a-z\-]+)/i', isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '', $matches) and !empty($matches[1])) {
            $lang = str_replace('-', '_', $matches[1]);
            $prev = substr($lang, 0, 2);
            switch ($prev) {
                # 中文简体以外统一繁体(台湾,香港)
                case self::LANG_ZH:
                    if ($lang === self::LANG_ZH) {
                        $lang = self::LANG_ZH_CN;
                    } elseif ($lang !== self::LANG_ZH_CN) {
                        $lang = self::LANG_ZH_TW;# HK和SGP默认为TW
                    }
                    break;
                # 其他语言不区分只区分类别(美英 === 英英)
                case self::LANG_EN:
                default:
                    $lang = $prev;
                    break;
            }
            return $lang;
        } else {
            return $default;
        }
    }


    /**
     * 获取浏览器版本(主版本号)
     * @return string
     */
    public static function getBrowserVer()
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {    //当浏览器没有发送访问者的信息的时候
            return self::AGENT_UNKNOWN;
        }
        $agent = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/MSIE\s(\d+)\..*/i', $agent, $regs))
            return $regs[1];
        elseif (preg_match('/Edge\/(\d+)\..*/i', $agent, $regs))
            return $regs[1];
        elseif (preg_match('/FireFox\/(\d+)\..*/i', $agent, $regs))
            return $regs[1];
        elseif (preg_match('/Opera[\s|\/](\d+)\..*/i', $agent, $regs))
            return $regs[1];
        elseif (preg_match('/Chrome\/(\d+)\..*/i', $agent, $regs))
            return $regs[1];
        elseif ((strpos($agent, 'Chrome') == false) and preg_match('/Safari\/(\d+)\..*$/i', $agent, $regs))
            return $regs[1];
        else
            return self::AGENT_UNKNOWN;
    }


    /**
     * 获取客户端IP
     *
     * 可能是以下的这些值(取决于访问主机名称):
     *  ① ::1(localhost)
     *  ② 127.0.0.1
     *  ③ 192.168.1.101
     *
     * @return string
     */
    public static function getIP()
    {
        static $realip = '';
        if (!$realip) {
            if (isset($_SERVER)) {
                if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                } else if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                    $realip = $_SERVER['HTTP_CLIENT_IP'];
                } else {
                    $realip = $_SERVER['REMOTE_ADDR'];
                }
            } else {
                if (getenv('HTTP_X_FORWARDED_FOR')) {
                    $realip = getenv('HTTP_X_FORWARDED_FOR');
                } else if (getenv('HTTP_CLIENT_IP')) {
                    $realip = getenv('HTTP_CLIENT_IP');
                } else {
                    $realip = getenv('REMOTE_ADDR');
                }
            }
        }
        return $realip;
    }

    /**
     * 确定客户端发起的请求是否基于SSL协议
     * @return bool
     */
    public static function isHttps()
    {
        return (isset($_SERVER['HTTPS']) and ('1' == $_SERVER['HTTPS'] or 'on' == strtolower($_SERVER['HTTPS']))) or
            (isset($_SERVER['SERVER_PORT']) and ('443' == $_SERVER['SERVER_PORT']));
    }

    /**
     * 判断是否是手机浏览器
     * @return bool
     */
    public static function isMobile()
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) and preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i',
                strtolower($_SERVER['HTTP_USER_AGENT']))
        ) {
            return true;
        } elseif ((isset($_SERVER['HTTP_ACCEPT'])) and (strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/vnd.wap.xhtml+xml') !== false)) {
            return true;
        }
        return false;
    }
}