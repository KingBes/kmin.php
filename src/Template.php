<?php

declare(strict_types=1);

namespace Kmin;

use \Exception;
use Throwable;
use \Dom\HTMLDocument;

/**
 * 模板引擎
 */
class Template
{
    /**
     * 模板变量
     *
     * @var array
     */
    protected array $data = [];

    /**
     * 配置参数
     *
     * @var array
     */
    protected array $config = [
        'view_path' => '', // 视图路径
        'cache_path' => '', // 缓存路径
        'cache_time' => 0, // 缓存时间
        'view_prefix' => 'km-', // 模板变量前缀
        'view_suffix' => 'php', // 视图文件后缀
    ];

    /**
     * 表达式替换规则
     *
     * @var array
     */
    protected array $exprReplace = [
        ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'heq', 'nheq', 'and', 'or'],
        ['==', '!=', '>', '>=', '<', '<=', '===', '!==', '&&', '||']
    ];

    /**
     * 模板js变量替换规则
     *
     * @var array
     */
    protected array $tplVars = [
        'kmStr' => '/kmStr\(([^\)]+)\)/',
        'kmNum' => '/kmNum\(([^\)]+)\)/',
        'kmVar' => '/kmVar\(([^\)]+)\)/',
        'kmBool' => '/kmBool\(([^\)]+)\)/',
        'kmJson' => '/kmJson\(([^\)]+)\)/'
    ];

    /**
     * 模板模板标签规则
     *
     * @var array
     */
    protected array $tplTags = [
        'kmIf' => '/\{\#if\s+([^\}]+)\}/',
        'kmElseIf' => '/\{\#else\s+if\s+([^\}]+)\}/',
        'kmElse' => '/\{\#else\}/',
        'kmEndIf' => '/\{\/if\}/',
        'kmFor' => '/\{\#for\s+([^\}]+)\}/',
        'kmEndFor' => '/\{\/for\}/',
        'kmEach' => '/\{\#each\s+([^\}]+)\s+as\s+([^\}]+)\}/',
        'kmEndEach' => '/\{\/each\}/',
        'kmVal' => '/\{\{([^\}]+)\}\}/',
        'kmHtml' => '/\{\#html\s+([^\}]+)\}/',
        'kmEvent' => '/\@([a-z]+)="([\w$]+)"/'
    ];

    /**
     * 构造函数
     *
     * @param array $config 配置参数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        // 确保路径以目录分隔符结尾
        foreach (['view_path', 'cache_path'] as $path) {
            if (!empty($this->config[$path]) && substr($this->config[$path], -1) !== DIRECTORY_SEPARATOR) {
                $this->config[$path] .= DIRECTORY_SEPARATOR;
            }
        }
        // 创建缓存目录（如果不存在）
        if (!empty($this->config['cache_path']) && !is_dir($this->config['cache_path'])) {
            mkdir($this->config['cache_path'], 0755, true);
        }
    }

    /**
     * 赋值
     *
     * @param array $vars 变量数组
     * @return static
     */
    public function assign(array $vars = []): static
    {
        $this->data = array_merge($this->data, $vars);
        return $this;
    }

    /**
     * 替换表达式符号
     *
     * @param string $string 字符串
     * @return string 字符串
     */
    protected function replaceExpr(string $string): string
    {
        $str = str_replace($this->exprReplace[0], $this->exprReplace[1], $string);
        return $str;
    }

    /**
     * 解析js模板变量
     *
     * @param string $tpl 模板内容
     * @return string 解析后的内容
     */
    protected function parseVars(string $tpl): string
    {
        foreach ($this->tplVars as $key => $pattern) {
            $tpl = preg_replace_callback($pattern, [$this, $key], $tpl);
        }
        return $tpl;
    }

    /**
     * 解析模板标签
     *
     * @param string $tpl 模板内容
     * @return string 解析后的内容
     */
    protected function parseTags(string $tpl): string
    {
        foreach ($this->tplTags as $key => $pattern) {
            $tpl = preg_replace_callback($pattern, [$this, $key], $tpl);
        }
        $tpl = str_replace("/", "\/", $tpl);
        return $tpl;
    }

    /**
     * 解析模板
     *
     * @param string $tplFile 模板文件路径
     * @return string
     */
    protected function parseTpl(string $tplFile): string
    {
        // 模板内容
        $tpl = file_get_contents($tplFile);
        if (preg_match(
            '/^\s*<template>.*?<\/template>\s*<script>.*?<\/script>\s*<style>.*?<\/style>\s*$/s',
            $tpl
        ) === false) {
            throw new \Exception("模板文件格式错误,必须是<template><script><style>标签包裹的内容");
        }
        // 获取模板文件名
        $filename = pathinfo($tplFile, PATHINFO_FILENAME);
        // 解析模板文件
        $dom = HTMLDocument::createFromString($tpl, LIBXML_NOERROR);
        preg_match('/<template\b[^>]*>(.*?)<\/template>/is', $tpl, $matches);
        $template = $matches[1]; // 获取模板内容
        $template = $this->parseTags($template); // 解析模板标签
        $style = $dom->querySelector('style')->innerHTML; // 获取样式内容
        $js = $dom->querySelector('script')->innerHTML; // 获取脚本内容
        // 解析脚本内容
        $varName = str_replace("-", "_", $this->config['view_prefix'] . $filename);
        $js = $this->parseVars($js); // 解析模板变量
        $js = preg_replace(
            '/\s*export\s+default\s+function\(\)\s+{/',
            "\nconst {$varName} = function () {
    this.css = function() { return `{$style}`; }
    this.render = function() { let kmTpl = `{$template}`; return kmTpl;}",
            $js
        );
        // 实例化
        $js .= "regComp('{$this->config['view_prefix']}{$filename}', {$varName});";
        // 判断入口模板文件是否存在
        if (file_exists($this->config['view_path'] . 'main.' . $this->config['view_suffix'])) {
            // 入口模板文件
            $mainFile = file_get_contents($this->config['view_path'] . 'main.' . $this->config['view_suffix']);
            // 解析入口模板文件
            $main = HTMLDocument::createFromString($mainFile, LIBXML_NOERROR);
            // 检查是否存在kmin.js
            $kminJs = $main->querySelectorAll('script[src="/kmin/kmin.js"]');
            if ($kminJs->length < 0) { // 不存在kmin.js就添加
                $kminJs = $main->createElement('script');
                $kminJs->setAttribute('src', '/kmin/kmin.js');
                $main->querySelector('head')->appendChild($kminJs);
            }
            // 添加模板标签到入口模板文件
            $viewTag = $main->createElement("{$this->config['view_prefix']}{$filename}");
            $main->querySelector('body')->appendChild($viewTag);
            // 添加脚本标签到入口模板文件
            $view = $main->createElement('script');
            $view->setAttribute('type', 'module');
            $view->innerHTML = $js;
            $main->querySelector('body')->appendChild($view);
            // 返回解析后的入口模板文件内容
            return $main->saveHTML();
        } else {
            return $js;
        }
    }

    /**
     * 渲染模板
     *
     * @param string $template 模板文件名
     * @param array $vars 模板变量
     * @return string
     */
    public function fetch(string $template, array $vars = []): string
    {
        if ($vars) {
            $this->data = array_merge($this->data, $vars);
        }
        // 模板文件路径
        $tplFile  = $this->config['view_path'] . $template . '.' . $this->config['view_suffix'];
        if (!file_exists($tplFile)) {
            throw new Exception('The template file does not exist:' . $tplFile);
        }
        // 缓存文件路径
        $cacheFile = $this->config['cache_path'] . md5($template) . '.php';
        // 检查是否需要重新编译模板
        if (!$this->isCacheValid($cacheFile, $tplFile)) {
            $content = $this->parseTpl($tplFile);
            file_put_contents($cacheFile, $content);
        }
        extract($this->data, EXTR_SKIP);
        ob_start();
        try {
            include $cacheFile;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }

    protected function kmStr($matches)
    {
        return "'<?php echo htmlspecialchars({$matches[1]}, ENT_QUOTES, 'UTF-8'); ?>'";
    }

    protected function kmNum($matches)
    {
        return "<?php echo (float){$matches[1]}; ?>";
    }

    protected function kmVar($matches)
    {
        return "<?php echo htmlspecialchars({$matches[1]}, ENT_QUOTES, 'UTF-8'); ?>";
    }

    protected function kmBool($matches)
    {
        return "<?php echo {$matches[1]} ? 'true' : 'false'; ?>";
    }

    protected function kmJson($matches)
    {
        return "JSON.parse('<?php echo json_encode({$matches[1]}, JSON_UNESCAPED_UNICODE); ?>')";
    }

    protected function kmIf($matches)
    {
        return "`;if(" . $this->replaceExpr($matches[1]) . "){kmTpl+=`";
    }

    protected function kmElseIf($matches)
    {
        return "`;}else if(" . $this->replaceExpr($matches[1]) . "){kmTpl+=`";
    }

    protected function kmElse()
    {
        return "`;}else{kmTpl+=`";
    }

    protected function kmEndIf()
    {
        return "`;}kmTpl+=`";
    }

    protected function kmFor($matches)
    {
        return "`;for(" . $this->replaceExpr($matches[1]) . ") {kmTpl+=`";
    }

    protected function kmEndFor()
    {
        return "`;}kmTpl+=`";
    }

    protected function kmEach($matches)
    {
        return "`;" . $matches[1] . ".forEach((" . $matches[2] . ") => {kmTpl+=`";
    }

    protected function kmEndEach()
    {
        return "`;});kmTpl+=`";
    }

    protected function kmVal($matches)
    {
        return "\${this.kmHtml(" . $matches[1] . ")}";
    }

    protected function kmHtml($matches)
    {
        return "\${this.kmHtml(" . $matches[1] . ",false)}";
    }

    protected function kmEvent($matches)
    {
        return 'data-event="' . $matches[1] . ',' . $matches[2] . '"';
    }

    /**
     * 检查目录是否相等
     *
     * @param string $dir1 目录1
     * @param string $dir2 目录2
     * @return boolean
     */
    protected function isEqDir(string $dir1, string $dir2): bool
    {
        $dir1 = $this->fileReplace($dir1);
        $dir2 = $this->fileReplace($dir2);
        if (str_ends_with($dir1, DIRECTORY_SEPARATOR)) {
            // 删除最后一个目录分隔符
            $dir1 = rtrim($dir1, DIRECTORY_SEPARATOR);
        }
        if (str_ends_with($dir2, DIRECTORY_SEPARATOR)) {
            // 删除最后一个目录分隔符
            $dir2 = rtrim($dir2, DIRECTORY_SEPARATOR);
        }
        return $dir1 === $dir2;
    }

    /**
     * 替换文件路径中的分隔符
     *
     * @param string $path 文件路径
     * @return string
     */
    protected function fileReplace(string $path): string
    {
        return str_replace(
            ['\\', '/', '\\\\', '//'],
            DIRECTORY_SEPARATOR,
            $path
        );
    }

    /**
     * 检查缓存是否有效
     *
     * @param string $cacheFile 缓存文件路径
     * @param string $tplFile 模板文件路径
     * @return bool
     */
    protected function isCacheValid(string $cacheFile, string $tplFile): bool
    {

        // 缓存文件不存在
        if (!file_exists($cacheFile)) {
            return false;
        }

        // 检查模板文件是否被修改
        if (filemtime($tplFile) > filemtime($cacheFile)) {
            return false;
        }

        // 判断入口模板是否存在
        $mainFile = $this->config['view_path'] . 'main.' . $this->config['view_suffix'];
        if (file_exists($mainFile)) {
            // 检查入口模板是否被修改
            if (filemtime($mainFile) > filemtime($cacheFile)) {
                return false;
            }
        }

        // 检查缓存是否过期
        if (
            $this->config['cache_time'] > 0
            && time() - filemtime($cacheFile) > $this->config['cache_time']
        ) {
            return false;
        }
        return true;
    }
}
