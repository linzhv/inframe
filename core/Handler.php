<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 07/10/2017
 * Time: 09:58
 */

declare(strict_types=1);

namespace inframe\core;

use inframe\Kits;

/**
 * Class Handler 错误／异常／终止处理器
 * @package inframe\core
 */
class Handler
{

    public static function handleThrowable(string $message, string $classnm, string $file, int $line, array $traces)
    {
        Response::cleanOutput();
        $infos = [
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'class' => $classnm,
        ];

        # 记录错误日志
        Log::getLogger('throwable')->fatal($infos);

        if (IN_IS_CLI) {
            var_dump($infos);
        } elseif (IN_IS_AJAX) {
            Response::export([
                'status' => -1,
                'message' => IN_DEBUG_MODE_ON ? var_export($infos, true) : 'an error occur',
            ]);
        } else {
            if (IN_DEBUG_MODE_ON) {
                // Display error message
                echo '<style>
				#lite_throwable_display{ font-size: 14px;font-family: "Consolas", "Bitstream Vera Sans Mono", "Courier New", Courier, monospace}
				#lite_throwable_display h2{color: #F20000}
				#lite_throwable_display p{padding-left: 20px}
				#lite_throwable_display ul li{margin-bottom: 10px}
				#lite_throwable_display a{font-size: 12px; color: #000000}
				#lite_throwable_display .psTrace, #lite_throwable_display .psArgs{display: none}
				#lite_throwable_display pre{border: 1px solid #236B04; background-color: #EAFEE1; padding: 5px;  width: 99%; overflow-x: auto; margin-bottom: 30px;}
				#lite_throwable_display .psArgs pre{background-color: #F1FDFE;}
				#lite_throwable_display pre .selected{color: #F20000; font-weight: bold;}
			</style>';
                echo '<div id="lite_throwable_display">';
                echo '<h2>[' . $classnm . ']</h2>';
                echo self::_buildContent($message, $file, $line);

                echo self::_buildFileBlock($file, $line);

                // Display debug backtrace
                echo '<ul>' . self::_buildTrace($traces) . '</ul></div>';
            } else {
                die('<div align="center">404</div>');
            }
        }
        die;
    }

    /**
     * @param array $traces
     * @return string
     */
    private static function _buildTrace(array $traces)
    {
        $html = '';
        foreach ($traces as $id => $trace) {
            # Error和Exception获取的trace是不一样的，Exception包含自身提示信息的部分，但是Error少了这一部分，所以把下面的代码注释
//            if(!$id) continue;
            $relative_file = (isset($trace['file'])) ? ltrim(str_replace(array(IN_PATH_BASE, '\\'), array('', '/'), $trace['file']), '/') : '';
            $current_line = (isset($trace['line'])) ? $trace['line'] : '';
            $html .= '<li>';
            $html .= '<b>' . ((isset($trace['class'])) ? $trace['class'] : '') . ((isset($trace['type'])) ? $trace['type'] : '') . $trace['function'] . '</b>';
            $html .= " - <a style='font-size: 12px;  cursor:pointer; color: blue;' onclick='document.getElementById(\"psTrace_{$id}\").style.display = (document.getElementById(\"psTrace_{$id}\").style.display != \"block\") ? \"block\" : \"none\"; return false'>[line {$current_line} - {$relative_file}]</a>";

            if (isset($trace['args']) && count($trace['args'])) {
                $html .= ' - <a style="font-size: 12px;  cursor:pointer; color: blue;" onclick="document.getElementById(\'psArgs_' . $id . '\').style.display = (document.getElementById(\'psArgs_' . $id . '\').style.display != \'block\') ? \'block\' : \'none\'; return false">[' . count($trace['args']) . ' Arguments]</a>';
            }

            if ($relative_file) {
                $html .= self::_buildFileBlock($trace['file'], $trace['line'], $id);
            }
            if (isset($trace['args']) && count($trace['args'])) {
                $html .= self::_buildArgsBlock($trace['args'], $id);
            }
            $html .= '</li>';
        }
        return $html;
    }


    /**
     * Display lines around current line
     *
     * @param string $file
     * @param int $line
     * @param string $id
     * @return string
     */
    private static function _buildFileBlock($file, $line, $id = null)
    {
        $lines = file($file);
        $offset = $line - 6;
        $total = 11;
        if ($offset < 0) {
            $total += $offset;
            $offset = 0;
        }
        $lines = array_slice($lines, $offset, $total);
        ++$offset;

        $html = '<div class="psTrace" id="psTrace_' . $id . '" ' . ((is_null($id) ? 'style="display: block"' : '')) . '><pre>';
        foreach ($lines as $k => $l) {
            $string = ($offset + $k) . '. ' . htmlspecialchars($l);
            if ($offset + $k == $line) {
                $html .= '<span class="selected">' . $string . '</span>';
            } else {
                $html .= $string;
            }
        }
        return $html . '</pre></div>';
    }


    /**
     * Display arguments list of traced function
     *
     * @param array $args List of arguments
     * @param string $id ID of argument
     * @return string
     */
    private static function _buildArgsBlock($args, $id)
    {
        $html = '<div class="psArgs" id="psArgs_' . $id . '"><pre>';
        foreach ($args as $arg => $value) {
            $html .= '<b>args [' . Kits::filter((string)$arg) . "]</b>\n";
            $html .= Kits::filter(print_r($value, true));
            $html .= "\n";
        }
        return $html . '</pre>';
    }

    /**
     * Return the content of the Exception
     * @param string $message
     * @param string $file
     * @param int $line
     * @return string content of the exception
     */
    private static function _buildContent($message, $file, $line)
    {
        $format = '<p><b style="color: mediumslateblue">%s</b><br/><i>at line </i><b>%d</b><i> in file </i><b style="color: darkorchid">%s</b></p>';
        return sprintf($format, $message, $line, ltrim(str_replace(array(IN_PATH_BASE, '\\'), array('', '/'), $file), '/'));
    }

    /**
     * 脚本终止时
     * @return void
     */
    public static function handleShutdown()
    {
    }

}