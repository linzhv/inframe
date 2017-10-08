<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 07/10/2017
 * Time: 09:57
 */

namespace inframe\core;

use inframe\Component;
use inframe\Developer;
use inframe\RouteInterface;
use inframe\RoutePacket;

/**
 * Class Router 路由类
 *
 * 用于解析URL获取执行的类名和方法名称以及根据给定控制器和方法生成URL
 *
 *
 * @package inframe\core
 */
class Router extends Component implements RouteInterface
{

    protected function getStaticConfig(): array
    {
        return [

            //------------------------
            //For URL route
            //------------------------
            'route_on' => true,//总开关,是否对URI地址进行路由
            'static_route_on' => true,
            //静态路由规则
            'static_route_rules' => [],
            'wildcard_route_on' => false,
            //通配符路由规则,具体参考CodeIgniter
            'wildcard_route_rules' => [],
            'regular_route_on' => true,
            //正则表达式 规则
            'regular_route_rules' => [],

            //------------------------
            //For URL parser
            //------------------------
            //API模式，直接使用$_GET
            'api_mode_on' => false,
            //API模式 对应的$_GET变量名称
            'api_modules_variable' => '_m',
            'api_controller_variable' => '_c',
            'api_action_variable' => '_a',

            //普通模式
            'masquerade_tail' => '.html',
            //*** 必须保证操作与控制器之间的符号将是$_SERVER['PATH_INFO']字符串中第一个出现的,为了更好地显示URL，参数一般通过POST传递
            //特别注意的是若使用了问号，则后面的字符串将被认为是请求参数
            'ap_bridge' => '.',

        ];
    }


    public function parse(string $url): RoutePacket
    {
        $config = $this->getConfig();

        //路由开启的情况下进行路由解析
        if ($config['route_on']) {
            //静态路由
            if ($config['static_route_on'] and $static = $config['static_route_rules']) {
                if (isset($static[$url])) {
                    $location = $static[$url];

                    if (is_string($location)) {
                        if (strpos($location, 'http') === 0) {
                            $_REQUEST and $location .= (strpos($location, '?') ? '&' : '?') . http_build_query($_REQUEST);
                            Response::redirect($location);
                        } else {
                            $location = self::parsePath($location);
                            $location[] = [];
                        }
                    }
                    return $location;
                }
            }
            //规则路由
            if ($config['wildcard_route_on'] and $wildcard = $config['wildcard_route_rules']) {
                foreach ($wildcard as $pattern => $rule) {
                    // Convert wildcards to RegEx（from CI）
                    //any对应非/的任何字符,num对应数字 ,id表示identify，即标识符(字母开头，只有下划线和字母)
                    $pattern = str_replace(['[any]', '[num]', '[id]'], ['([\w\d_]+)', '([0-9]+)', '(\w[\w_\d]?)'], $pattern);//$pattern = preg_replace('/\[.+?\]/','([^/\[\]]+)',$pattern);//非贪婪匹配
                    $result = self::matchRegular($pattern, $url, $rule);
                    if (is_array($result)) {
                    } elseif (is_string($result)) {
                        $result = self::parsePath($result);
                    } else {
                        continue;
                    }
                    return new RoutePacket($result['m'] ?? '', $result['c'] ?? '', $result['a'] ?? '', $result['p'] ?? []);
                }
            }
            //正则路由
            if ($config['regular_route_on'] and $regular = $config['regular_route_rules']) {
                foreach ($regular as $pattern => $rule) {
                    $result = self::matchRegular($pattern, $url, $rule);
                    if (null !== $result) return new RoutePacket($result['m'] ?? '', $result['c'] ?? '', $result['a'] ?? '', $result['p'] ?? []);

                }
            }
        }

        $result = [];

        //进行URL解析
        if ($config['api_mode_on']) {
            //API模式下

            Developer::status('fetchurl_in_apimode_begin');

            $m = $config['api_modules_variable'];
            $c = $config['api_controller_variable'];
            $a = $config['api_action_variable'];

            //获取模块名称
            isset($_GET[$m]) and $result['m'] = $_GET[$m];
            //获取控制器名称
            isset($_GET[$c]) and $result['c'] = $_GET[$c];
            //获取操作名称，类方法不区分大小写
            isset($_GET[$a]) and $result['a'] = $_GET[$a];
            //参数为剩余的变量
            unset($_GET[$m], $_GET[$c], $_GET[$a]);
            $result['p'] = $_GET;

            Developer::status('fetchurl_in_topspeed_end');
        } else {
            # 普通的pathinfo模式
            //检查、寻找和解析URI路由 'route_on'
            //普通模式下解析URI地址

            Developer::status('parseurl_in_common_begin');
            $ap = $config['ap_bridge'];
            if (strpos($url, $tail = (string)$config['masquerade_tail']) !== false) {
                if (substr($url, -strlen($tail)) === $tail) {
                    $len = strlen($url) - strlen($tail);
                    $url = $len ? substr($url, 0, $len) : '';
                }
            }

            //-- 解析PATHINFO --//
            //截取参数段param与定位段local
            $papos = strpos($url, $ap);
            $mcapart = null;
            $pparts = '';
            if (false === $papos) {
                $mcapart = trim($url, '/');//不存在参数则认定PATH_INFO全部是MCA的部分，否则得到结果substr($url,0,0)即空字符串
            } else {
                $mcapart = trim(substr($url, 0, $papos), '/');
                $pparts = substr($url, $papos + strlen($ap));
            }

            //-- 解析MCA部分 --//
            //逆向检查CA是否存在衔接
            $mcaparsed = self::parseMCA($mcapart);

            $result['m'] = $mcaparsed['m'];
            empty($mcaparsed['c']) or $result['c'] = $mcaparsed['c'];
            empty($mcaparsed['a']) or $result['a'] = $mcaparsed['a'];

            //-- 解析参数部分 --//
            $result['p'] = self::fetchKeyValuePair($pparts);
            Developer::status('parseurl_in_common_end');
        }

        if (empty($result['m'])) {
            $result['m'] = '';
        } else {
            $result['m'] = implode('/', (array)$result['m']);
        }
        empty($result['c']) and $result['c'] = '';
        empty($result['a']) and $result['a'] = '';

        return new RoutePacket($result['m'], $result['c'], $result['a'], $result['p']);
    }

    public function build(RoutePacket $routePacket): string
    {
        return '';
    }

    /**
     * 解析"模块、控制器、操作"
     * @param $mcapart
     * @param string $mm 模块之间的连接符号
     * @param string $mc 模块控制器之间的连接
     * @param string $ca 控制器和操作之间的连接符号
     * @return array ['m' => '模块', 'c' => '控制器', 'a' => '操作']
     */
    public static function parseMCA($mcapart, $mm = '/', $mc = '/', $ca = '/')
    {
        $parsed = ['m' => null, 'c' => null, 'a' => null];
        $mcapart = trim($mcapart, ' /');

        $capos = strrpos($mcapart, $ca);
        if (false === $capos) {
            //找不到控制器与操作之间分隔符（一定不存在控制器）
            //先判断位置部分是否为空字符串来决定是否有操作名称
            if (strlen($mcapart)) {
                //位置字段全部是字符串的部分
                $parsed['a'] = $mcapart;
            } //没有操作部分，MCA全部使用默认的
        } else {
            $parsed['a'] = substr($mcapart, $capos + strlen($ca));

            //CA存在衔接符 则说明一定存在控制器
            $mcalen = strlen($mcapart);
            $mcpart = substr($mcapart, 0, $capos - $mcalen);//去除了action的部分

            if (strlen($mcapart)) {
                $mcpos = strrpos($mcpart, $mc);
                if (false === $mcpos) {
                    //不存在模块
                    if (strlen($mcpart)) {
                        //全部是控制器的部分
                        $parsed['c'] = $mcpart;
                    }   //没有控制器部分，则使用默认的
                } else {
                    //截取控制器的部分
                    $parsed['c'] = substr($mcpart, $mcpos + strlen($mc));

                    //既然存在MC衔接符 说明一定存在模块
                    $mpart = substr($mcpart, 0, $mcpos - strlen($mcpart));//以下的全是模块部分的字符串
                    if (strlen($mpart)) {
                        if (false === strpos($mpart, $mm)) {
                            $parsed['m'] = [$mpart];
                        } else {
                            $parsed['m'] = explode($mm, $mpart);
                        }
                    }  //一般存在衔接符的情况下不为空,但也考虑下特殊情况
                }
            }//一般存在衔接符的情况下不为空,但也考虑下特殊情况
        }
        isset($parsed['m']) or $parsed['m'] = [];
        isset($parsed['c']) or $parsed['c'] = '';
        return $parsed;
    }


    /**
     * 将参数序列装换成参数数组，应用Router模块的配置
     * @param string $params
     * @param string $ppb
     * @param string $pkvb
     * @return array
     */
    public static function fetchKeyValuePair($params, $ppb = '/', $pkvb = '/')
    {//解析字符串成数组
        $pc = [];
        if ($ppb !== $pkvb) {//使用不同的分割符
            $params = trim($params, " {$ppb}{$pkvb}");
            $parampairs = explode($ppb, $params);
            foreach ($parampairs as $val) {
                $pos = strpos($val, $pkvb);
                if (false === $pos) {
                    //非键值对，赋值数字键
                } else {
                    $key = substr($val, 0, $pos);
                    $val = substr($val, $pos + strlen($pkvb));
                    $pc[$key] = $val;
                }
            }
        } else {//使用相同的分隔符
            $params = trim($params, " {$ppb}");
            $elements = explode($ppb, $params);
            $count = count($elements);
            for ($i = 0; $i < $count; $i += 2) {
                if (isset($elements[$i + 1])) {
                    $pc[$elements[$i]] = $elements[$i + 1];
                }  //单个将被投入匿名参数,先废弃
            }
        }
        return $pc;
    }

    /**
     * 使用正则表达式匹配uri
     * @param string $pattern 路由规则
     * @param string $url 传递进来的URL字符串
     * @param array|string|callable $rule 路由导向结果
     * @return array|string|null
     */
    public static function matchRegular($pattern, $url, $rule)
    {
        $result = null;
        // do the RegEx match? use '#' to ignore '/'
        if (preg_match('#^' . $pattern . '$#', $url, $matches)) {
            if (is_array($rule)) {
                $len = count($matches);
                for ($i = 1; $i < $len; $i++) {
                    $key = '$' . $i;
                    if (isset($rule['$' . $i])) {
                        $v = (string)$rule[$key];
                        if (strpos($v, '.')) {
                            $a = explode('.', $v);
                            empty($rule[$a[0]]) and $rule[$a[0]] = [];
                            $rule[$a[0]][$a[1]] = $matches[$i];
                        } else {
                            $rule[$v] = $matches[$i];
                        }
                    } else {
                        empty($rule['o']) and $rule['o'] = [];
                        $rule['o'][] = $matches[$i];
                    }
                    unset($rule[$key]);
                }
                $result = $rule;
            } elseif (is_string($rule)) {
                $result = preg_replace('#^' . $pattern . '$#', $rule, $url);//参数一代表的正则表达式从参数三的字符串中寻找匹配并替换到参数二代表的字符串中
            } elseif (is_callable($rule)) {
                array_shift($matches);
                // Execute the callback using the values in matches as its parameters.
                $result = call_user_func_array($rule, $matches);//参数二是完整的匹配
                if ($result === true) {
                    //返回true表示直接完成
                    exit();
                } elseif (!is_string($result) and !is_array($result)) {
                    //要求结果必须返回string或者数组
                    return null;
                }
            }
        }
        return $result;
    }


    /**
     * 解析路径 "模块s/控制器/操作"
     * @param string $path
     * @return array [module,controller,action]
     */
    public static function parsePath($path)
    {
        $pos = strpos($path, '?');
        if ($pos !== false) {
            parse_str(substr($path, $pos, -1), $query);
            $path = substr($path, 0, $pos);
        }
        $path = explode('/', trim($path, '/'));
        $action = array_pop($path);
        $controller = array_pop($path);
        $modules = implode('/', $path);
        return [$modules, isset($controller) ? $controller : '', isset($action) ? $action : ''];
    }
}